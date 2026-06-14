<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatCarePlanSender
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
        [$noRawat, $date, $time, $status] = array_pad(explode('|', $key, 4), 4, null);
        if (! $noRawat || ! $date || ! $time || ! in_array($status, ['Ralan', 'Ranap'], true)) {
            throw new RuntimeException('Kunci Care Plan tidak valid.');
        }

        $source = $status === 'Ralan' ? 'pemeriksaan_ralan' : 'pemeriksaan_ranap';
        $row = $connection->table($source)
            ->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$source}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', "{$source}.nip")
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin('satu_sehat_careplan', function ($join) use ($source, $status) {
                $join->on('satu_sehat_careplan.no_rawat', '=', "{$source}.no_rawat")
                    ->on('satu_sehat_careplan.tgl_perawatan', '=', "{$source}.tgl_perawatan")
                    ->on('satu_sehat_careplan.jam_rawat', '=', "{$source}.jam_rawat")
                    ->where('satu_sehat_careplan.status', $status);
            })
            ->where("{$source}.no_rawat", $noRawat)
            ->where("{$source}.tgl_perawatan", $date)
            ->where("{$source}.jam_rawat", $time)
            ->select([
                "{$source}.rtl", 'reg_periksa.tgl_registrasi', 'pasien.no_ktp', 'pasien.nm_pasien',
                'pegawai.no_ktp as ktp_praktisi', 'pegawai.nama as nama_praktisi',
                'satu_sehat_encounter.id_encounter', 'satu_sehat_careplan.id_careplan',
            ])
            ->first();

        if (! $row || blank($row->rtl)) {
            throw new RuntimeException('Data Care Plan tidak ditemukan.');
        }
        if (filled($row->id_careplan)) {
            throw new RuntimeException('Care Plan sudah pernah terkirim.');
        }
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->ktp_praktisi, 'ID Encounter' => $row->id_encounter] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }

        $patientId = $this->findFhirId($setting, 'Patient', $row->no_ktp);
        $practitionerId = $this->findFhirId($setting, 'Practitioner', $row->ktp_praktisi);
        $created = Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String();
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)
            ->post(rtrim($setting->satusehat_fhir_url, '/').'/CarePlan', [
                'resourceType' => 'CarePlan',
                'identifier' => [
                    'system' => 'http://sys-ids.kemkes.go.id/careplan/'.$setting->satusehat_organization_id,
                    'value' => $noRawat,
                ],
                'title' => 'Instruksi Medik dan Keperawatan Pasien',
                'status' => 'active',
                'category' => [['coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => $status === 'Ralan' ? '736271009' : '736353004',
                    'display' => $status === 'Ralan' ? 'Outpatient care plan' : 'Inpatient care plan',
                ]]]],
                'intent' => 'plan',
                'description' => preg_replace('/\R/u', '<br>', str_replace("\t", ' ', $row->rtl)),
                'subject' => ['reference' => "Patient/{$patientId}", 'display' => $row->nm_pasien],
                'encounter' => [
                    'reference' => "Encounter/{$row->id_encounter}",
                    'display' => "Kunjungan {$row->nm_pasien} pada tanggal {$row->tgl_registrasi} dengan nomor kunjungan {$noRawat}",
                ],
                'created' => $created,
                'author' => ['reference' => "Practitioner/{$practitionerId}", 'display' => $row->nama_praktisi],
            ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Care Plan.');
        }

        $id = $response->json('id');
        $connection->table('satu_sehat_careplan')->updateOrInsert(
            ['no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam_rawat' => $time, 'status' => $status],
            ['id_careplan' => $id]
        );

        return $id;
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
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'Organization ID' => $setting->satusehat_organization_id, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
            }
        }
    }
}
