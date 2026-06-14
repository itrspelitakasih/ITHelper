<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatEpisodeOfCareSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public static function ensureOutputTable(Connection $db): void
    {
        $db->statement("CREATE TABLE IF NOT EXISTS satu_sehat_episode_of_care (
            no_rawat varchar(17) NOT NULL, kd_penyakit varchar(12) NOT NULL, status varchar(10) NOT NULL,
            id_episode_of_care varchar(50) DEFAULT NULL, PRIMARY KEY (no_rawat,kd_penyakit,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
    }

    public function sendMany(array $keys): array
    {
        $setting = $this->databaseManager->setting();
        if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        $this->ensureApiConfigured($setting);
        $db = $this->databaseManager->connection();
        self::ensureOutputTable($db);
        $results = ['sent' => [], 'failed' => []];
        foreach (array_unique($keys) as $key) {
            try { $results['sent'][$key] = $this->sendOne($db, $setting, $key); }
            catch (Throwable $exception) { $results['failed'][$key] = $exception->getMessage(); }
        }
        return $results;
    }

    private function sendOne(Connection $db, ExternalDatabaseSetting $setting, string $key): string
    {
        [$noRawat, $disease, $status, $careType] = array_pad(explode('|', $key, 4), 4, '');
        if (! $noRawat || ! $disease || ! $status || ! in_array($careType, ['Ralan', 'Ranap'], true)) throw new RuntimeException('Kunci Episode of Care tidak valid.');
        $source = $careType === 'Ralan' ? 'pemeriksaan_ralan' : 'kamar_inap';
        $date = $careType === 'Ralan' ? 'tgl_perawatan' : 'tgl_keluar';
        $time = $careType === 'Ralan' ? 'jam_rawat' : 'jam_keluar';
        $row = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($source, "{$source}.no_rawat", '=', 'reg_periksa.no_rawat')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('diagnosa_pasien', 'diagnosa_pasien.no_rawat', '=', 'reg_periksa.no_rawat')->join('penyakit', 'penyakit.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')
            ->leftJoin('satu_sehat_episode_of_care as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'diagnosa_pasien.no_rawat')->on('sent.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')->on('sent.status', '=', 'diagnosa_pasien.status');
            })->where(['reg_periksa.no_rawat' => $noRawat, 'diagnosa_pasien.kd_penyakit' => $disease, 'diagnosa_pasien.status' => $status])
            ->selectRaw("reg_periksa.no_rawat, pasien.nm_pasien, pasien.no_ktp, satu_sehat_encounter.id_encounter, {$source}.{$date} as event_date, {$source}.{$time} as event_time, sent.id_episode_of_care")->first();
        if (! $row) throw new RuntimeException('Data Episode of Care tidak ditemukan.');
        if (filled($row->id_episode_of_care)) throw new RuntimeException('Episode of Care sudah pernah terkirim.');
        if (blank($row->no_ktp)) throw new RuntimeException('NIK pasien belum tersedia.');
        if (blank($row->id_encounter)) throw new RuntimeException('ID Encounter belum tersedia.');
        $start = Carbon::parse("{$row->event_date} {$row->event_time}", 'Asia/Jakarta')->toIso8601String();
        $payload = [
            'resourceType' => 'EpisodeOfCare', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/episode-of-care/{$setting->satusehat_organization_id}", 'value' => $noRawat]],
            'status' => 'active', 'statusHistory' => [['status' => 'active', 'period' => ['start' => $start]]],
            'type' => [['coding' => [['system' => 'http://terminology.kemkes.go.id/CodeSystem/episodeofcare-type', 'code' => 'ANC', 'display' => 'Antenatal Care']]]],
            'patient' => ['reference' => 'Patient/'.$this->findPatient($setting, $row->no_ktp), 'display' => $row->nm_pasien],
            'managingOrganization' => ['reference' => "Organization/{$setting->satusehat_organization_id}"], 'period' => ['start' => $start],
        ];
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/').'/EpisodeOfCare', $payload);
        $id = $response->json('id');
        if ($response->failed() || blank($id)) throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Episode of Care.');
        $db->table('satu_sehat_episode_of_care')->updateOrInsert(['no_rawat' => $noRawat, 'kd_penyakit' => $disease, 'status' => $status], ['id_episode_of_care' => $id]);
        return $id;
    }

    private function findPatient(ExternalDatabaseSetting $setting, string $nik): string
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
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'Organization ID' => $setting->satusehat_organization_id, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
    }
}
