<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatClinicalResourceSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public function sendMany(string $group, string $type, array $keys): array
    {
        $setting = $this->databaseManager->setting();
        if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        $this->ensureApiConfigured($setting);
        $db = $this->databaseManager->connection();
        $results = ['sent' => [], 'failed' => []];
        foreach (array_unique($keys) as $key) {
            try {
                $results['sent'][$key] = match ($group) {
                    'rme' => $this->sendRme($db, $setting, $type, $key),
                    'risk-assessments' => $this->sendRisk($db, $setting, $key),
                    'service-requests' => $this->sendServiceRequest($db, $setting, $type, $key),
                    'specimens' => $this->sendSpecimen($db, $setting, $type, $key),
                    default => throw new RuntimeException('Jenis resource tidak valid.'),
                };
            } catch (Throwable $exception) {
                $results['failed'][$key] = $exception->getMessage();
            }
        }
        return $results;
    }

    private function sendRme(Connection $db, ExternalDatabaseSetting $setting, string $type, string $noRawat): string
    {
        $resume = $type === 'rawat-inap' ? 'resume_pasien_ranap' : 'resume_pasien';
        $output = $type === 'rawat-inap' ? 'satu_sehat_rme_rawat_inap' : 'satu_sehat_rme_rawat_jalan';
        $row = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($resume, "{$resume}.no_rawat", '=', 'reg_periksa.no_rawat')->join('dokter', 'dokter.kd_dokter', '=', "{$resume}.kd_dokter")
            ->join('pegawai', 'pegawai.nik', '=', 'dokter.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin($output, "{$output}.no_rawat", '=', 'reg_periksa.no_rawat')->where('reg_periksa.no_rawat', $noRawat)
            ->selectRaw("reg_periksa.*, pasien.nm_pasien, pasien.no_ktp, dokter.nm_dokter, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$output}.id_composition, {$resume}.*")->first();
        if (! $row) throw new RuntimeException('Data resume medis tidak ditemukan.');
        if (filled($row->id_composition)) throw new RuntimeException('RME sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'NIK dokter' => $row->practitioner_nik, 'ID Encounter' => $row->id_encounter] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        $patient = $this->findFhirId($setting, 'Patient', $row->no_ktp);
        $doctor = $this->findFhirId($setting, 'Practitioner', $row->practitioner_nik);
        $sections = collect([
            'Keluhan Utama' => $row->keluhan_utama ?? null, 'Diagnosa Utama' => $row->diagnosa_utama ?? null,
            'Jalannya Penyakit' => $row->jalannya_penyakit ?? null, 'Pemeriksaan Penunjang' => $row->pemeriksaan_penunjang ?? null,
            'Hasil Laboratorium' => $row->hasil_laborat ?? null, 'Obat Pulang' => $row->obat_pulang ?? null,
        ])->filter(fn ($value) => filled($value))->map(fn ($value, $title) => ['title' => $title, 'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml">'.e($value).'</div>']])->values()->all();
        $payload = [
            'resourceType' => 'Composition', 'identifier' => ['system' => "http://sys-ids.kemkes.go.id/composition/{$setting->satusehat_organization_id}", 'value' => str_replace('/', '', $noRawat)],
            'status' => 'final', 'type' => ['coding' => [['system' => 'http://loinc.org', 'code' => '18842-5', 'display' => 'Discharge summary']]],
            'subject' => ['reference' => "Patient/{$patient}", 'display' => $row->nm_pasien], 'encounter' => ['reference' => "Encounter/{$row->id_encounter}"],
            'date' => $this->dateTime($row->tgl_registrasi, $row->jam_reg), 'author' => [['reference' => "Practitioner/{$doctor}", 'display' => $row->nm_dokter]],
            'title' => $type === 'rawat-inap' ? 'Resume Medis Rawat Inap' : 'Resume Medis Rawat Jalan', 'section' => $sections,
        ];
        $id = $this->post($setting, 'Composition', $payload);
        $db->table($output)->updateOrInsert(['no_rawat' => $noRawat], ['id_composition' => $id]);
        return $id;
    }

    private function sendRisk(Connection $db, ExternalDatabaseSetting $setting, string $noRawat): string
    {
        $row = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('penilaian_awal_keperawatan_ralan as risk', 'risk.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin('satu_sehat_riskassessment as sent', 'sent.no_rawat', '=', 'reg_periksa.no_rawat')->where('reg_periksa.no_rawat', $noRawat)
            ->selectRaw('reg_periksa.*, pasien.nm_pasien, pasien.no_ktp, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, risk.hasil, risk.berjalan_a, risk.berjalan_b, risk.berjalan_c, sent.id_riskassessment')->first();
        if (! $row) throw new RuntimeException('Data Risk Assessment tidak ditemukan.');
        if (filled($row->id_riskassessment)) throw new RuntimeException('Risk Assessment sudah pernah terkirim.');
        $risk = str_contains(strtolower($row->hasil ?? ''), 'tinggi') ? ['high', 'High likelihood'] : (str_contains(strtolower($row->hasil ?? ''), 'sedang') ? ['moderate', 'Moderate likelihood'] : ['low', 'Low likelihood']);
        $payload = [
            'resourceType' => 'RiskAssessment', 'status' => 'final', 'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '129839007', 'display' => 'At risk for falls']]],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp), 'display' => $row->nm_pasien],
            'encounter' => ['reference' => "Encounter/{$row->id_encounter}"], 'occurrenceDateTime' => $this->dateTime($row->tgl_registrasi, $row->jam_reg),
            'performer' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->practitioner_nik)],
            'prediction' => [['outcome' => ['text' => 'Risiko Jatuh'], 'qualitativeRisk' => ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/risk-probability', 'code' => $risk[0], 'display' => $risk[1]]]], 'rationale' => "{$row->hasil}. Tidak seimbang/sempoyongan: {$row->berjalan_a}, Jalan dengan alat bantu: {$row->berjalan_b}, Menopang saat duduk/berdiri: {$row->berjalan_c}"]],
        ];
        $id = $this->post($setting, 'RiskAssessment', $payload);
        $db->table('satu_sehat_riskassessment')->updateOrInsert(['no_rawat' => $noRawat], ['id_riskassessment' => $id]);
        return $id;
    }

    private function sendServiceRequest(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        $d = $this->definition($type, 'service');
        [$order, $procedure, $template] = $this->parseKey($d, $key);
        $row = $this->diagnosticRow($db, $d, $order, $procedure, $template, true);
        if (! $row) throw new RuntimeException('Data Service Request tidak ditemukan.');
        if (filled($row->sent_id)) throw new RuntimeException('Service Request sudah pernah terkirim.');
        $payload = [
            'resourceType' => 'ServiceRequest', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/servicerequest/{$setting->satusehat_organization_id}", 'value' => "{$order}.".($d['has_template'] ? $template : $procedure)]],
            'status' => 'active', 'intent' => 'order', 'category' => [['coding' => [['system' => 'http://snomed.info/sct', 'code' => $type === 'radiology' ? '363679005' : '108252007', 'display' => $type === 'radiology' ? 'Imaging' : 'Laboratory procedure']]]],
            'code' => ['coding' => [['system' => $row->mapping_system, 'code' => $row->mapping_code, 'display' => $row->mapping_display]], 'text' => $row->examination],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp)], 'encounter' => ['reference' => "Encounter/{$row->id_encounter}"],
            'authoredOn' => $this->dateTime($row->event_date, $row->event_time), 'requester' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->practitioner_nik), 'display' => $row->practitioner_name],
            'performer' => [['reference' => "Organization/{$setting->satusehat_organization_id}"]], 'reasonCode' => [['text' => $row->diagnosis ?: 'Pemeriksaan penunjang']],
        ];
        $id = $this->post($setting, 'ServiceRequest', $payload);
        $where = ['noorder' => $order, 'kd_jenis_prw' => $procedure]; if ($d['has_template']) $where['id_template'] = $template;
        $db->table($d['output'])->updateOrInsert($where, ['id_servicerequest' => $id]);
        return $id;
    }

    private function sendSpecimen(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        $d = $this->definition($type, 'specimen');
        [$order, $procedure, $template] = $this->parseKey($d, $key);
        $row = $this->diagnosticRow($db, $d, $order, $procedure, $template, false);
        if (! $row) throw new RuntimeException('Data Specimen tidak ditemukan atau Service Request belum terkirim.');
        if (filled($row->sent_id)) throw new RuntimeException('Specimen sudah pernah terkirim.');
        $payload = [
            'resourceType' => 'Specimen', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/specimen/{$setting->satusehat_organization_id}", 'value' => "{$order}.".($d['has_template'] ? $template : $procedure)]],
            'status' => 'available', 'type' => ['coding' => [['system' => $row->sample_system, 'code' => $row->sample_code, 'display' => $row->sample_display]]],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp), 'display' => $row->nm_pasien],
            'request' => [['reference' => "ServiceRequest/{$row->id_servicerequest}"]], 'receivedTime' => $this->dateTime($row->event_date, $row->event_time),
        ];
        $id = $this->post($setting, 'Specimen', $payload);
        $where = ['noorder' => $order, 'kd_jenis_prw' => $procedure]; if ($d['has_template']) $where['id_template'] = $template;
        $db->table($d['output'])->updateOrInsert($where, ['id_specimen' => $id]);
        return $id;
    }

    private function diagnosticRow(Connection $db, array $d, string $order, string $procedure, string $template, bool $service): ?object
    {
        $q = $db->table($d['request'])->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$d['request']}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure'], "{$d['procedure']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure']}.{$d['mapping_key']}");
        if ($service) {
            $q->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        } else {
            $q->join($d['service'], function ($join) use ($d) {
                $join->on("{$d['service']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['service']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['service']}.id_template", '=', "{$d['detail']}.id_template");
            });
        }
        $q->leftJoin($d['output'], function ($join) use ($d) {
            $join->on("{$d['output']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['output']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
            if ($d['has_template']) $join->on("{$d['output']}.id_template", '=', "{$d['detail']}.id_template");
        })->where("{$d['request']}.noorder", $order)->where("{$d['detail']}.kd_jenis_prw", $procedure);
        if ($d['has_template']) $q->where("{$d['detail']}.id_template", $template);
        $sent = $service ? 'id_servicerequest' : 'id_specimen'; $date = $service ? 'tgl_permintaan' : 'tgl_sampel'; $time = $service ? 'jam_permintaan' : 'jam_sampel';
        $extra = $service
            ? "pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$d['request']}.diagnosa_klinis as diagnosis, {$d['mapping']}.system as mapping_system, {$d['mapping']}.code as mapping_code, {$d['mapping']}.display as mapping_display, '' as sample_system, '' as sample_code, '' as sample_display, '' as id_servicerequest"
            : "'' as practitioner_name, '' as practitioner_nik, '' as id_encounter, '' as diagnosis, '' as mapping_system, '' as mapping_code, '' as mapping_display, {$d['mapping']}.sampel_system as sample_system, {$d['mapping']}.sampel_code as sample_code, {$d['mapping']}.sampel_display as sample_display, {$d['service']}.id_servicerequest";
        return $q->selectRaw("reg_periksa.no_rawat, pasien.nm_pasien, pasien.no_ktp, {$d['request']}.{$date} as event_date, {$d['request']}.{$time} as event_time, {$d['procedure']}.{$d['procedure_name']} as examination, {$d['output']}.{$sent} as sent_id, {$extra}")->first();
    }

    private function definition(string $type, string $resource): array
    {
        $base = [
            'lab-mb' => ['has_template' => true, 'request' => 'permintaan_labmb', 'detail' => 'permintaan_detail_permintaan_labmb', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'service' => 'satu_sehat_servicerequest_lab_mb'],
            'lab-pk' => ['has_template' => true, 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'service' => 'satu_sehat_servicerequest_lab'],
            'radiology' => ['has_template' => false, 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'procedure_name' => 'nm_perawatan', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw', 'service' => 'satu_sehat_servicerequest_radiologi'],
        ][$type] ?? throw new RuntimeException('Jenis pemeriksaan tidak valid.');
        $base['output'] = $resource === 'service' ? $base['service'] : ['lab-mb' => 'satu_sehat_specimen_lab_mb', 'lab-pk' => 'satu_sehat_specimen_lab', 'radiology' => 'satu_sehat_specimen_radiologi'][$type];
        return $base;
    }

    private function parseKey(array $d, string $key): array
    {
        [$order, $procedure, $template] = array_pad(explode('|', $key, 3), 3, '');
        if (! $order || ! $procedure || ($d['has_template'] && ! $template)) throw new RuntimeException('Kunci data tidak valid.');
        return [$order, $procedure, $template];
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
        if (blank($nik)) throw new RuntimeException("NIK {$resource} belum tersedia.");
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

    private function dateTime(string $date, string $time): string
    {
        return Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String();
    }

    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'Organization ID' => $setting->satusehat_organization_id, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
    }
}
