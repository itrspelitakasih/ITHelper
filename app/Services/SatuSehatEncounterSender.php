<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatEncounterSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager)
    {
    }

    public function sendMany(array $noRawatList): array
    {
        $setting = $this->databaseManager->setting();

        if (! $setting) {
            throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        }

        $this->ensureApiConfigured($setting);
        $connection = $this->databaseManager->connection();
        $results = ['sent' => [], 'failed' => []];

        foreach (array_unique($noRawatList) as $noRawat) {
            try {
                $results['sent'][$noRawat] = $this->sendOne($connection, $setting, $noRawat);
            } catch (Throwable $exception) {
                $results['failed'][$noRawat] = $exception->getMessage();
            }
        }

        return $results;
    }

    private function sendOne(Connection $connection, ExternalDatabaseSetting $setting, string $noRawat): string
    {
        $encounter = $connection->table('reg_periksa')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')
            ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
            ->join('satu_sehat_mapping_lokasi_ralan', 'satu_sehat_mapping_lokasi_ralan.kd_poli', '=', 'poliklinik.kd_poli')
            ->leftJoin('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->where('reg_periksa.no_rawat', $noRawat)
            ->where('reg_periksa.status_bayar', 'Sudah Bayar')
            ->select([
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'reg_periksa.no_rawat',
                'reg_periksa.status_lanjut', 'pasien.nm_pasien', 'pasien.no_ktp',
                'pegawai.nama as nama_dokter', 'pegawai.no_ktp as ktp_dokter',
                'poliklinik.nm_poli', 'satu_sehat_mapping_lokasi_ralan.id_lokasi_satusehat',
                'satu_sehat_encounter.id_encounter',
            ])
            ->first();

        if (! $encounter) {
            throw new RuntimeException('Data encounter tidak ditemukan atau belum memenuhi syarat.');
        }

        if (filled($encounter->id_encounter)) {
            throw new RuntimeException('Encounter sudah pernah terkirim.');
        }

        foreach ([
            'NIK pasien' => $encounter->no_ktp,
            'NIK dokter' => $encounter->ktp_dokter,
            'mapping lokasi' => $encounter->id_lokasi_satusehat,
        ] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }

        $patientId = $this->findFhirId($setting, 'Patient', $encounter->no_ktp);
        $practitionerId = $this->findFhirId($setting, 'Practitioner', $encounter->ktp_dokter);
        $startedAt = Carbon::parse(
            $encounter->tgl_registrasi.' '.$encounter->jam_reg,
            'Asia/Jakarta'
        )->toIso8601String();
        $ambulatory = $encounter->status_lanjut === 'Ralan';

        $response = Http::acceptJson()
            ->withToken($this->token($setting))
            ->timeout(45)
            ->post(rtrim($setting->satusehat_fhir_url, '/').'/Encounter', [
                'resourceType' => 'Encounter',
                'status' => 'arrived',
                'class' => [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                    'code' => $ambulatory ? 'AMB' : 'IMP',
                    'display' => $ambulatory ? 'ambulatory' : 'inpatient encounter',
                ],
                'subject' => ['reference' => "Patient/{$patientId}", 'display' => $encounter->nm_pasien],
                'participant' => [[
                    'type' => [['coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                        'code' => 'ATND',
                        'display' => 'attender',
                    ]]]],
                    'individual' => ['reference' => "Practitioner/{$practitionerId}", 'display' => $encounter->nama_dokter],
                ]],
                'period' => ['start' => $startedAt],
                'location' => [[
                    'location' => [
                        'reference' => "Location/{$encounter->id_lokasi_satusehat}",
                        'display' => $encounter->nm_poli,
                    ],
                ]],
                'statusHistory' => [['status' => 'arrived', 'period' => ['start' => $startedAt, 'end' => $startedAt]]],
                'serviceProvider' => ['reference' => "Organization/{$setting->satusehat_organization_id}"],
                'identifier' => [[
                    'system' => "http://sys-ids.kemkes.go.id/encounter/{$setting->satusehat_organization_id}",
                    'value' => $encounter->no_rawat,
                ]],
            ]);

        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($this->apiError($response->json(), 'SATUSEHAT menolak pengiriman Encounter.'));
        }

        $id = $response->json('id');
        $connection->table('satu_sehat_encounter')->updateOrInsert(
            ['no_rawat' => $encounter->no_rawat],
            ['id_encounter' => $id]
        );

        return $id;
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()
            ->withToken($this->token($setting))
            ->timeout(30)
            ->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", [
                'identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}",
            ]);

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

        $url = rtrim($setting->satusehat_auth_url, '/').'/accesstoken';
        $response = Http::asForm()->acceptJson()->timeout(30)->post($url.'?grant_type=client_credentials', [
            'client_id' => $setting->satusehat_client_id,
            'client_secret' => $setting->satusehat_client_secret,
        ]);

        $this->token = $response->json('access_token');

        if ($response->failed() || blank($this->token)) {
            throw new RuntimeException($this->apiError($response->json(), 'Autentikasi SATUSEHAT gagal.'));
        }

        return $this->token;
    }

    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach ([
            'Client ID' => $setting->satusehat_client_id,
            'Client Secret' => $setting->satusehat_client_secret,
            'Organization ID' => $setting->satusehat_organization_id,
            'URL Auth' => $setting->satusehat_auth_url,
            'URL FHIR' => $setting->satusehat_fhir_url,
        ] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
            }
        }
    }

    private function apiError(mixed $body, string $fallback): string
    {
        if (is_array($body)) {
            return $body['issue'][0]['details']['text']
                ?? $body['issue'][0]['diagnostics']
                ?? $body['message']
                ?? $fallback;
        }

        return $fallback;
    }
}
