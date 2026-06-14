<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatProcedureSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public function sendMany(array $keys): array
    {
        $setting = $this->databaseManager->setting();
        if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        $this->ensureApiConfigured($setting);
        $connection = $this->databaseManager->connection();
        $results = ['sent' => [], 'failed' => []];
        foreach (array_unique($keys) as $key) {
            try { $results['sent'][$key] = $this->sendOne($connection, $setting, $key); }
            catch (Throwable $exception) { $results['failed'][$key] = $exception->getMessage(); }
        }
        return $results;
    }

    private function sendOne(Connection $connection, ExternalDatabaseSetting $setting, string $key): string
    {
        [$noRawat, $code, $status] = array_pad(explode('|', $key, 3), 3, null);
        if (! $noRawat || ! $code || ! in_array($status, ['Ralan', 'Ranap'], true)) throw new RuntimeException('Kunci Procedure tidak valid.');
        $row = $connection->table('prosedur_pasien')->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'prosedur_pasien.no_rawat')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')->join('icd9', 'icd9.kode', '=', 'prosedur_pasien.kode')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin('satu_sehat_procedure', function ($join) {
                $join->on('satu_sehat_procedure.no_rawat', '=', 'prosedur_pasien.no_rawat')->on('satu_sehat_procedure.kode', '=', 'prosedur_pasien.kode')->on('satu_sehat_procedure.status', '=', 'prosedur_pasien.status');
            })->where(['prosedur_pasien.no_rawat' => $noRawat, 'prosedur_pasien.kode' => $code, 'prosedur_pasien.status' => $status])
            ->select(['reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'pasien.nm_pasien', 'pasien.no_ktp', 'icd9.deskripsi_panjang', 'satu_sehat_encounter.id_encounter', 'satu_sehat_procedure.id_procedure'])->first();
        if (! $row) throw new RuntimeException('Data Procedure tidak ditemukan.');
        if (filled($row->id_procedure)) throw new RuntimeException('Procedure sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'ID Encounter' => $row->id_encounter] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        $patientId = $this->findPatientId($setting, $row->no_ktp);
        $performed = Carbon::parse("{$row->tgl_registrasi} {$row->jam_reg}", 'Asia/Jakarta')->toIso8601String();
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/').'/Procedure', [
            'resourceType' => 'Procedure', 'status' => 'completed',
            'category' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '103693007', 'display' => 'Diagnostic procedure']], 'text' => 'Diagnostic procedure'],
            'code' => ['coding' => [['system' => 'http://hl7.org/fhir/sid/icd-9-cm', 'code' => $code, 'display' => $row->deskripsi_panjang]]],
            'subject' => ['reference' => "Patient/{$patientId}", 'display' => $row->nm_pasien],
            'encounter' => ['reference' => "Encounter/{$row->id_encounter}", 'display' => "Prosedur {$row->nm_pasien} selama kunjungan/dirawat dari tanggal {$performed} sampai {$performed}"],
            'performedPeriod' => ['start' => $performed, 'end' => $performed],
        ]);
        $id = $response->json('id');
        if ($response->failed() || blank($id)) throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Procedure.');
        $connection->table('satu_sehat_procedure')->updateOrInsert(['no_rawat' => $noRawat, 'kode' => $code, 'status' => $status], ['id_procedure' => $id]);
        return $id;
    }

    private function findPatientId(ExternalDatabaseSetting $setting, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)->get(rtrim($setting->satusehat_fhir_url, '/').'/Patient', ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id');
        if ($response->failed() || blank($id)) throw new RuntimeException("Patient dengan NIK {$nik} tidak ditemukan di SATUSEHAT.");
        return $id;
    }

    private function token(ExternalDatabaseSetting $setting): string
    {
        if ($this->token) return $this->token;
        $response = Http::asForm()->acceptJson()->timeout(30)->post(rtrim($setting->satusehat_auth_url, '/').'/accesstoken?grant_type=client_credentials', ['client_id' => $setting->satusehat_client_id, 'client_secret' => $setting->satusehat_client_secret]);
        $this->token = $response->json('access_token');
        if ($response->failed() || blank($this->token)) throw new RuntimeException('Autentikasi SATUSEHAT gagal.');
        return $this->token;
    }

    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
    }
}
