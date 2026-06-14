<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatClinicalImpressionSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager)
    {
    }

    public function sendMany(array $keys): array
    {
        $setting = $this->databaseManager->setting();
        if (! $setting) {
            throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        }
        $this->ensureApiConfigured($setting);
        $connection = $this->databaseManager->connection();
        $results = ['sent' => [], 'failed' => []];

        foreach (array_unique($keys) as $key) {
            try {
                $results['sent'][$key] = $this->sendOne($connection, $setting, $key);
            } catch (Throwable $exception) {
                $results['failed'][$key] = $exception->getMessage();
            }
        }

        return $results;
    }

    private function sendOne(Connection $connection, ExternalDatabaseSetting $setting, string $key): string
    {
        [$noRawat, $date, $time, $status, $diseaseCode] = array_pad(explode('|', $key, 5), 5, null);
        if (! $noRawat || ! $date || ! $time || ! $diseaseCode || ! in_array($status, ['Ralan', 'Ranap'], true)) {
            throw new RuntimeException('Kunci Clinical Impression tidak valid.');
        }

        $source = $status === 'Ralan' ? 'pemeriksaan_ralan' : 'pemeriksaan_ranap';
        $row = $connection->table($source)
            ->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$source}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', "{$source}.nip")
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('satu_sehat_condition', function ($join) use ($status) {
                $join->on('satu_sehat_condition.no_rawat', '=', 'reg_periksa.no_rawat')
                    ->where('satu_sehat_condition.status', $status);
            })
            ->join('penyakit', 'penyakit.kd_penyakit', '=', 'satu_sehat_condition.kd_penyakit')
            ->leftJoin('satu_sehat_clinicalimpression', function ($join) use ($source, $status) {
                $join->on('satu_sehat_clinicalimpression.no_rawat', '=', "{$source}.no_rawat")
                    ->on('satu_sehat_clinicalimpression.tgl_perawatan', '=', "{$source}.tgl_perawatan")
                    ->on('satu_sehat_clinicalimpression.jam_rawat', '=', "{$source}.jam_rawat")
                    ->where('satu_sehat_clinicalimpression.status', $status);
            })
            ->where("{$source}.no_rawat", $noRawat)
            ->where("{$source}.tgl_perawatan", $date)
            ->where("{$source}.jam_rawat", $time)
            ->where('satu_sehat_condition.kd_penyakit', $diseaseCode)
            ->select([
                "{$source}.keluhan", "{$source}.pemeriksaan", "{$source}.penilaian",
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'pasien.no_ktp', 'pasien.nm_pasien',
                'pegawai.no_ktp as ktp_praktisi', 'satu_sehat_encounter.id_encounter',
                'satu_sehat_condition.kd_penyakit', 'satu_sehat_condition.id_condition', 'penyakit.nm_penyakit',
                'satu_sehat_clinicalimpression.id_clinicalimpression',
            ])
            ->first();

        if (! $row || blank($row->penilaian)) {
            throw new RuntimeException('Data Clinical Impression tidak ditemukan.');
        }
        if (filled($row->id_clinicalimpression)) {
            throw new RuntimeException('Clinical Impression sudah pernah terkirim.');
        }
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->ktp_praktisi, 'ID Encounter' => $row->id_encounter, 'ID Condition' => $row->id_condition] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }

        $effective = Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String();
        $description = trim(implode(', ', array_filter([$row->keluhan, $row->pemeriksaan], fn ($value) => filled($value))));
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)
            ->post(rtrim($setting->satusehat_fhir_url, '/').'/ClinicalImpression', [
                'resourceType' => 'ClinicalImpression',
                'status' => 'completed',
                'description' => $this->cleanText($description),
                'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp), 'display' => $row->nm_pasien],
                'encounter' => [
                    'reference' => "Encounter/{$row->id_encounter}",
                    'display' => "Kunjungan {$row->nm_pasien} pada tanggal {$row->tgl_registrasi} {$row->jam_reg} dengan nomor kunjungan {$noRawat}",
                ],
                'effectiveDateTime' => $effective,
                'date' => $effective,
                'assessor' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->ktp_praktisi)],
                'summary' => $this->cleanText($row->penilaian),
                'finding' => [[
                    'itemCodeableConcept' => ['coding' => [[
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => $row->kd_penyakit,
                        'display' => $row->nm_penyakit,
                    ]]],
                    'itemReference' => ['reference' => "Condition/{$row->id_condition}"],
                ]],
                'prognosisCodeableConcept' => [['coding' => [[
                    'system' => 'http://terminology.kemkes.go.id/CodeSystem/clinical-term',
                    'code' => 'PR000001',
                    'display' => 'Prognosis',
                ]]]],
            ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Clinical Impression.');
        }

        $id = $response->json('id');
        $connection->table('satu_sehat_clinicalimpression')->updateOrInsert(
            ['no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam_rawat' => $time, 'status' => $status],
            ['id_clinicalimpression' => $id]
        );

        return $id;
    }

    private function cleanText(?string $value): string
    {
        return preg_replace('/\R/u', '<br>', str_replace("\t", ' ', $value ?? ''));
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)
            ->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id');
        if ($response->failed() || blank($id)) {
            throw new RuntimeException("{$resource} dengan NIK {$nik} tidak ditemukan di SATUSEHAT.");
        }

        return $id;
    }

    private function token(ExternalDatabaseSetting $setting): string
    {
        if ($this->token) {
            return $this->token;
        }
        $response = Http::asForm()->acceptJson()->timeout(30)
            ->post(rtrim($setting->satusehat_auth_url, '/').'/accesstoken?grant_type=client_credentials', [
                'client_id' => $setting->satusehat_client_id,
                'client_secret' => $setting->satusehat_client_secret,
            ]);
        $this->token = $response->json('access_token');
        if ($response->failed() || blank($this->token)) {
            throw new RuntimeException('Autentikasi SATUSEHAT gagal.');
        }

        return $this->token;
    }

    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
            }
        }
    }
}
