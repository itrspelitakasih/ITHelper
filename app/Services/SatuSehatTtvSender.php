<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use App\Support\TtvTypes;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatTtvSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager)
    {
    }

    public function sendMany(string $type, array $keys): array
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
                $results['sent'][$key] = $this->sendOne($connection, $setting, $type, $key);
            } catch (Throwable $exception) {
                $results['failed'][$key] = $exception->getMessage();
            }
        }

        return $results;
    }

    private function sendOne(Connection $connection, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        $definition = TtvTypes::get($type);
        [$noRawat, $date, $time, $status] = array_pad(explode('|', $key, 4), 4, null);
        if (! $noRawat || ! $date || ! $time || ! in_array($status, ['Ralan', 'Ranap'], true)) {
            throw new RuntimeException('Kunci Observation TTV tidak valid.');
        }

        $source = $status === 'Ralan' ? 'pemeriksaan_ralan' : 'pemeriksaan_ranap';
        $row = $connection->table($source)
            ->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$source}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', "{$source}.nip")
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin($definition['table'], function ($join) use ($definition, $source, $status) {
                $join->on("{$definition['table']}.no_rawat", '=', "{$source}.no_rawat")
                    ->on("{$definition['table']}.tgl_perawatan", '=', "{$source}.tgl_perawatan")
                    ->on("{$definition['table']}.jam_rawat", '=', "{$source}.jam_rawat")
                    ->where("{$definition['table']}.status", $status);
            })
            ->where("{$source}.no_rawat", $noRawat)
            ->where("{$source}.tgl_perawatan", $date)
            ->where("{$source}.jam_rawat", $time)
            ->select([
                "{$source}.{$definition['column']} as value", 'pasien.no_ktp', 'pasien.nm_pasien',
                'pegawai.no_ktp as ktp_praktisi', 'satu_sehat_encounter.id_encounter',
                "{$definition['table']}.id_observation",
            ])
            ->first();

        if (! $row || blank($row->value)) {
            throw new RuntimeException('Data TTV tidak ditemukan.');
        }
        if (filled($row->id_observation)) {
            throw new RuntimeException('Observation TTV sudah pernah terkirim.');
        }
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->ktp_praktisi, 'ID Encounter' => $row->id_encounter] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }

        $payload = $this->payload(
            $definition,
            $row,
            $status,
            $this->findFhirId($setting, 'Patient', $row->no_ktp),
            $this->findFhirId($setting, 'Practitioner', $row->ktp_praktisi),
            Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String()
        );
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)
            ->post(rtrim($setting->satusehat_fhir_url, '/').'/Observation', $payload);

        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Observation TTV.');
        }

        $id = $response->json('id');
        $connection->table($definition['table'])->updateOrInsert(
            ['no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam_rawat' => $time, 'status' => $status],
            ['id_observation' => $id]
        );

        return $id;
    }

    private function payload(array $definition, object $row, string $status, string $patientId, string $practitionerId, string $effective): array
    {
        $payload = [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                'code' => ($definition['concept'] ?? false) ? 'exam' : 'vital-signs',
                'display' => ($definition['concept'] ?? false) ? 'Exam' : 'Vital Signs',
            ]]]],
            'code' => ['coding' => [[
                'system' => $definition['system'] ?? 'http://loinc.org',
                'code' => $definition['code'],
                'display' => $definition['display'],
            ]]],
            'subject' => ['reference' => "Patient/{$patientId}"],
            'performer' => [['reference' => "Practitioner/{$practitionerId}"]],
            'encounter' => [
                'reference' => "Encounter/{$row->id_encounter}",
                'display' => "Pemeriksaan Fisik {$definition['label']} di {$status}, Pasien {$row->nm_pasien}",
            ],
            'effectiveDateTime' => $effective,
        ];

        if ($definition['blood_pressure'] ?? false) {
            [$systolic, $diastolic] = array_pad(explode('/', str_replace(',', '.', $row->value), 2), 2, 0);
            $payload['code']['text'] = 'Blood pressure systolic & diastolic';
            $payload['component'] = [
                $this->pressureComponent('8480-6', 'Systolic blood pressure', $systolic),
                $this->pressureComponent('8462-4', 'Diastolic blood pressure', $diastolic),
            ];
        } elseif ($definition['concept'] ?? false) {
            $payload['valueCodeableConcept'] = ['text' => str_replace(
                ['Compos Mentis', 'Somnolence', 'Sopor', 'Coma'],
                ['Alert', 'Voice', 'Pain', 'Unresponsive'],
                $row->value
            )];
        } else {
            $payload['valueQuantity'] = [
                'value' => (float) str_replace(',', '.', $row->value),
                'system' => 'http://unitsofmeasure.org',
                'code' => $definition['unit_code'],
            ];
            if ($definition['unit']) {
                $payload['valueQuantity']['unit'] = $definition['unit'];
            }
        }

        return $payload;
    }

    private function pressureComponent(string $code, string $display, mixed $value): array
    {
        return [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $code, 'display' => $display]]],
            'valueQuantity' => ['value' => (float) $value, 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]'],
        ];
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
