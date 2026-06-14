<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatConditionSender
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
        [$noRawat, $diseaseCode, $status] = array_pad(explode('|', $key, 3), 3, null);
        if (! $noRawat || ! $diseaseCode || ! in_array($status, ['Ralan', 'Ranap'], true)) {
            throw new RuntimeException('Kunci Condition tidak valid.');
        }

        $condition = $connection->table('diagnosa_pasien')
            ->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'diagnosa_pasien.no_rawat')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('penyakit', 'penyakit.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin('satu_sehat_condition', function ($join) {
                $join->on('satu_sehat_condition.no_rawat', '=', 'diagnosa_pasien.no_rawat')
                    ->on('satu_sehat_condition.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')
                    ->on('satu_sehat_condition.status', '=', 'diagnosa_pasien.status');
            })
            ->where('diagnosa_pasien.no_rawat', $noRawat)
            ->where('diagnosa_pasien.kd_penyakit', $diseaseCode)
            ->where('diagnosa_pasien.status', $status)
            ->select([
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'reg_periksa.no_rawat',
                'pasien.nm_pasien', 'pasien.no_ktp', 'penyakit.nm_penyakit',
                'diagnosa_pasien.kd_penyakit', 'diagnosa_pasien.status',
                'satu_sehat_encounter.id_encounter', 'satu_sehat_condition.id_condition',
            ])
            ->first();

        if (! $condition) {
            throw new RuntimeException('Data Condition tidak ditemukan.');
        }
        if (filled($condition->id_condition)) {
            throw new RuntimeException('Condition sudah pernah terkirim.');
        }
        foreach (['NIK pasien' => $condition->no_ktp, 'ID Encounter' => $condition->id_encounter] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }

        $patientId = $this->findPatientId($setting, $condition->no_ktp);
        $registeredAt = $condition->tgl_registrasi.' '.$condition->jam_reg;
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)
            ->post(rtrim($setting->satusehat_fhir_url, '/').'/Condition', [
                'resourceType' => 'Condition',
                'clinicalStatus' => ['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                    'code' => 'active',
                    'display' => 'Active',
                ]]],
                'category' => [['coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                    'code' => 'encounter-diagnosis',
                    'display' => 'Encounter Diagnosis',
                ]]]],
                'code' => ['coding' => [[
                    'system' => 'http://hl7.org/fhir/sid/icd-10',
                    'code' => $condition->kd_penyakit,
                    'display' => $condition->nm_penyakit,
                ]]],
                'subject' => ['reference' => "Patient/{$patientId}", 'display' => $condition->nm_pasien],
                'encounter' => [
                    'reference' => "Encounter/{$condition->id_encounter}",
                    'display' => "Diagnosa {$condition->nm_pasien} selama kunjungan/dirawat dari tanggal {$registeredAt} sampai {$registeredAt}",
                ],
            ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Condition.');
        }

        $id = $response->json('id');
        $connection->table('satu_sehat_condition')->updateOrInsert(
            ['no_rawat' => $condition->no_rawat, 'kd_penyakit' => $condition->kd_penyakit, 'status' => $condition->status],
            ['id_condition' => $id]
        );

        return $id;
    }

    private function findPatientId(ExternalDatabaseSetting $setting, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)
            ->get(rtrim($setting->satusehat_fhir_url, '/').'/Patient', ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id');
        if ($response->failed() || blank($id)) {
            throw new RuntimeException("Patient dengan NIK {$nik} tidak ditemukan di SATUSEHAT.");
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
