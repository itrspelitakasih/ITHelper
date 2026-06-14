<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatMedicationSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public function sendMany(string $type, array $keys): array
    {
        $setting = $this->databaseManager->setting();
        if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
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

    private function sendOne(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        if ($type === 'medication') return $this->sendMedication($db, $setting, $key);
        if ($type === 'dispense') return $this->sendDispense($db, $setting, $key);
        if (in_array($type, ['request', 'statement'], true)) return $this->sendPrescription($db, $setting, $type, $key);
        throw new RuntimeException('Jenis Medication tidak valid.');
    }

    private function sendMedication(Connection $db, ExternalDatabaseSetting $setting, string $code): string
    {
        $row = $db->table('satu_sehat_mapping_obat as map')->join('databarang as barang', 'barang.kode_brng', '=', 'map.kode_brng')
            ->leftJoin('satu_sehat_medication as sent', 'sent.kode_brng', '=', 'map.kode_brng')->where('map.kode_brng', $code)
            ->select('map.*', 'barang.status', 'sent.id_medication')->first();
        if (! $row) throw new RuntimeException('Mapping obat tidak ditemukan.');
        if (filled($row->id_medication)) throw new RuntimeException('Medication sudah pernah terkirim.');
        $payload = [
            'resourceType' => 'Medication',
            'meta' => ['profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication']],
            'identifier' => [['system' => "http://sys-ids.kemkes.go.id/medication/{$setting->satusehat_organization_id}", 'use' => 'official', 'value' => $code]],
            'code' => ['coding' => [['system' => $row->obat_system, 'code' => $row->obat_code, 'display' => $row->obat_display]]],
            'status' => (string) $row->status === '1' ? 'active' : 'inactive',
            'form' => ['coding' => [['system' => $row->form_system, 'code' => $row->form_code, 'display' => $row->form_display]]],
            'extension' => [['url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType', 'valueCodeableConcept' => ['coding' => [['system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-type', 'code' => 'NC', 'display' => 'Non-compound']]]]],
        ];
        $id = $this->post($setting, 'Medication', $payload);
        $db->table('satu_sehat_medication')->updateOrInsert(['kode_brng' => $code], ['id_medication' => $id]);
        return $id;
    }

    private function sendPrescription(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        [$noResep, $code, $noRacik] = array_pad(explode('|', $key, 3), 3, '');
        if (! $noResep || ! $code) throw new RuntimeException('Kunci resep tidak valid.');
        $compound = filled($noRacik);
        $detail = $compound ? 'resep_dokter_racikan_detail' : 'resep_dokter';
        $output = "satu_sehat_medication{$type}".($compound ? '_racikan' : '');
        $query = $db->table('resep_obat')->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'resep_obat.no_rawat')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')->join('pegawai', 'pegawai.nik', '=', 'resep_obat.kd_dokter')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        if ($compound) {
            $query->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep', '=', 'resep_obat.no_resep')
                ->join($detail, fn ($join) => $join->on("{$detail}.no_resep", '=', 'resep_dokter_racikan.no_resep')->on("{$detail}.no_racik", '=', 'resep_dokter_racikan.no_racik'))
                ->where("{$detail}.no_racik", $noRacik);
        } else {
            $query->join($detail, "{$detail}.no_resep", '=', 'resep_obat.no_resep');
        }
        $row = $query->join('satu_sehat_mapping_obat as map', 'map.kode_brng', '=', "{$detail}.kode_brng")
            ->join('satu_sehat_medication as med', 'med.kode_brng', '=', 'map.kode_brng')
            ->leftJoin("{$output} as sent", function ($join) use ($detail, $compound) {
                $join->on('sent.no_resep', '=', "{$detail}.no_resep")->on('sent.kode_brng', '=', "{$detail}.kode_brng");
                if ($compound) $join->on('sent.no_racik', '=', "{$detail}.no_racik");
            })->where('resep_obat.no_resep', $noResep)->where("{$detail}.kode_brng", $code)
            ->selectRaw("resep_obat.*, reg_periksa.status_lanjut, pasien.no_ktp, pasien.nm_pasien, pegawai.no_ktp as ktp_praktisi, pegawai.nama as nama_praktisi, satu_sehat_encounter.id_encounter, map.*, med.id_medication, {$detail}.jml, ".($compound ? 'resep_dokter_racikan.aturan_pakai' : "{$detail}.aturan_pakai")." as aturan_pakai, sent.id_medication{$type} as sent_id")->first();
        if (! $row) throw new RuntimeException('Data resep tidak ditemukan atau Medication belum terkirim.');
        if (filled($row->sent_id)) throw new RuntimeException('Data sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->ktp_praktisi, 'ID Encounter' => $row->id_encounter, 'ID Medication' => $row->id_medication] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        [$dose, $frequency] = $this->signa($row->aturan_pakai);
        $patient = $this->findFhirId($setting, 'Patient', $row->no_ktp);
        $identifier = $noResep.($compound ? "-{$noRacik}" : '');
        $common = [
            'status' => 'completed',
            'category' => ['coding' => [['system' => $type === 'request' ? 'http://terminology.hl7.org/CodeSystem/medicationrequest-category' : 'http://terminology.hl7.org/CodeSystem/medication-statement-category', 'code' => $row->status_lanjut === 'Ralan' ? 'outpatient' : 'inpatient', 'display' => $row->status_lanjut === 'Ralan' ? 'Outpatient' : 'Inpatient']]],
            'medicationReference' => ['reference' => "Medication/{$row->id_medication}", 'display' => $row->obat_display],
            'subject' => ['reference' => "Patient/{$patient}", 'display' => $row->nm_pasien],
        ];
        if ($type === 'request') {
            $payload = array_merge(['resourceType' => 'MedicationRequest', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/prescription/{$setting->satusehat_organization_id}", 'use' => 'official', 'value' => $identifier], ['system' => "http://sys-ids.kemkes.go.id/prescription-item/{$setting->satusehat_organization_id}", 'use' => 'official', 'value' => $code]], 'intent' => 'order'], $common, [
                'encounter' => ['reference' => "Encounter/{$row->id_encounter}"],
                'authoredOn' => $this->dateTime($row->tgl_peresepan, $row->jam_peresepan),
                'requester' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->ktp_praktisi), 'display' => $row->nama_praktisi],
                'dosageInstruction' => [$this->dosage($row, $dose, $frequency)],
                'dispenseRequest' => ['quantity' => ['value' => (float) $row->jml, 'unit' => $row->denominator_code, 'system' => $row->denominator_system, 'code' => $row->denominator_code]],
            ]);
        } else {
            $payload = array_merge(['resourceType' => 'MedicationStatement', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/medicationstatement/{$setting->satusehat_organization_id}", 'use' => 'official', 'value' => "{$noResep}-{$code}".($compound ? "-{$noRacik}" : '')]]], $common, [
                'dateAsserted' => $this->dateTime($row->tgl_penyerahan, $row->jam_penyerahan),
                'informationSource' => ['reference' => "Patient/{$patient}", 'display' => $row->nm_pasien],
                'context' => ['reference' => "Encounter/{$row->id_encounter}"],
                'dosage' => [$this->dosage($row, $dose, $frequency)],
                'note' => [['text' => 'Pasien sudah memahami aturan pakai yang dijelaskan oleh petugas & Obat sudah diserahkan ke pasien']],
            ]);
        }
        $id = $this->post($setting, $type === 'request' ? 'MedicationRequest' : 'MedicationStatement', $payload);
        $where = ['no_resep' => $noResep, 'kode_brng' => $code];
        if ($compound) $where['no_racik'] = $noRacik;
        $db->table($output)->updateOrInsert($where, ["id_medication{$type}" => $id]);
        return $id;
    }

    private function sendDispense(Connection $db, ExternalDatabaseSetting $setting, string $key): string
    {
        [$noRawat, $date, $time, $code, $batch, $invoice] = array_pad(explode('|', $key, 6), 6, '');
        if (! $noRawat || ! $date || ! $time || ! $code) throw new RuntimeException('Kunci Medication Dispense tidak valid.');
        $row = $db->table('detail_pemberian_obat as detail')->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'detail.no_rawat')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')->join('resep_obat', function ($join) {
                $join->on('resep_obat.no_rawat', '=', 'detail.no_rawat')->on('resep_obat.tgl_perawatan', '=', 'detail.tgl_perawatan')->on('resep_obat.jam', '=', 'detail.jam');
            })->join('pegawai', 'pegawai.nik', '=', 'resep_obat.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'detail.no_rawat')
            ->join('aturan_pakai as aturan', function ($join) {
                $join->on('aturan.no_rawat', '=', 'detail.no_rawat')->on('aturan.tgl_perawatan', '=', 'detail.tgl_perawatan')->on('aturan.jam', '=', 'detail.jam')->on('aturan.kode_brng', '=', 'detail.kode_brng');
            })->join('satu_sehat_mapping_obat as map', 'map.kode_brng', '=', 'detail.kode_brng')->join('satu_sehat_medication as med', 'med.kode_brng', '=', 'detail.kode_brng')
            ->join('satu_sehat_mapping_lokasi_depo_farmasi as lokasi', 'lokasi.kd_bangsal', '=', 'detail.kd_bangsal')
            ->leftJoin('satu_sehat_medicationdispense as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'detail.no_rawat')->on('sent.tgl_perawatan', '=', 'detail.tgl_perawatan')->on('sent.jam', '=', 'detail.jam')->on('sent.kode_brng', '=', 'detail.kode_brng')->on('sent.no_batch', '=', 'detail.no_batch')->on('sent.no_faktur', '=', 'detail.no_faktur');
            })->where(['detail.no_rawat' => $noRawat, 'detail.tgl_perawatan' => $date, 'detail.jam' => $time, 'detail.kode_brng' => $code, 'detail.no_batch' => $batch, 'detail.no_faktur' => $invoice])
            ->selectRaw('detail.*, pasien.no_ktp, pasien.nm_pasien, pegawai.no_ktp as ktp_praktisi, pegawai.nama as nama_praktisi, satu_sehat_encounter.id_encounter, aturan.aturan, map.*, med.id_medication, lokasi.id_lokasi_satusehat, sent.id_medicationdispanse')->first();
        if (! $row) throw new RuntimeException('Data Medication Dispense tidak ditemukan.');
        if (filled($row->id_medicationdispanse)) throw new RuntimeException('Medication Dispense sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->ktp_praktisi, 'ID Encounter' => $row->id_encounter, 'ID Medication' => $row->id_medication, 'ID lokasi depo' => $row->id_lokasi_satusehat] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        [$dose, $frequency] = $this->signa($row->aturan);
        $payload = [
            'resourceType' => 'MedicationDispense',
            'identifier' => [['system' => "http://sys-ids.kemkes.go.id/medicationdispense/{$setting->satusehat_organization_id}", 'use' => 'official', 'value' => "{$noRawat}-{$code}-{$date}-{$time}"]],
            'status' => 'completed',
            'category' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/medicationdispense-category', 'code' => $row->status === 'Ralan' ? 'outpatient' : 'inpatient', 'display' => $row->status === 'Ralan' ? 'Outpatient' : 'Inpatient']]],
            'medicationReference' => ['reference' => "Medication/{$row->id_medication}", 'display' => $row->obat_display],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp), 'display' => $row->nm_pasien],
            'context' => ['reference' => "Encounter/{$row->id_encounter}"],
            'performer' => [['actor' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->ktp_praktisi), 'display' => $row->nama_praktisi]]],
            'location' => ['reference' => "Location/{$row->id_lokasi_satusehat}"],
            'quantity' => ['value' => (float) $row->jml, 'unit' => $row->denominator_code, 'system' => $row->denominator_system, 'code' => $row->denominator_code],
            'whenHandedOver' => $this->dateTime($date, $time),
            'dosageInstruction' => [$this->dosage($row, $dose, $frequency, 'aturan')],
        ];
        $id = $this->post($setting, 'MedicationDispense', $payload);
        $db->table('satu_sehat_medicationdispense')->updateOrInsert(['no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam' => $time, 'kode_brng' => $code, 'no_batch' => $batch, 'no_faktur' => $invoice], ['id_medicationdispanse' => $id]);
        return $id;
    }

    private function dosage(object $row, float $dose, int $frequency, string $instruction = 'aturan_pakai'): array
    {
        return ['text' => $row->{$instruction}, 'patientInstruction' => $row->{$instruction}, 'timing' => ['repeat' => ['frequency' => $frequency, 'period' => 1, 'periodUnit' => 'd']], 'route' => ['coding' => [['system' => $row->route_system, 'code' => $row->route_code, 'display' => $row->route_display]]], 'doseAndRate' => [['doseQuantity' => ['value' => $dose, 'unit' => $row->denominator_code, 'system' => $row->denominator_system, 'code' => $row->denominator_code]]]];
    }

    private function signa(?string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/', strtolower($text ?? ''), $matches);
        return [(float) str_replace(',', '.', $matches[0][0] ?? 1), max(1, (int) ($matches[0][1] ?? 1))];
    }

    private function dateTime(string $date, string $time): string
    {
        return Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String();
    }

    private function post(ExternalDatabaseSetting $setting, string $resource, array $payload): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", $payload);
        $id = $response->json('id');
        if ($response->failed() || blank($id)) throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? "SATUSEHAT menolak {$resource}.");
        return $id;
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id');
        if ($response->failed() || blank($id)) throw new RuntimeException("{$resource} dengan NIK {$nik} tidak ditemukan di SATUSEHAT.");
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
