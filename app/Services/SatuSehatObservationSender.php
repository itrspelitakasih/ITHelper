<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatObservationSender
{
    private ?string $token = null;

    public function __construct(private readonly ExternalDatabaseManager $databaseManager, private readonly SatuSehatTtvSender $ttvSender) {}

    public function sendMany(string $type, array $keys, ?string $ttvType = null): array
    {
        if ($type === 'ttv') return $this->ttvSender->sendMany($ttvType ?? 'suhu', $keys);
        $setting = $this->databaseManager->setting();
        if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        $this->ensureApiConfigured($setting);
        $db = $this->databaseManager->connection();
        $results = ['sent' => [], 'failed' => []];
        foreach (array_unique($keys) as $key) {
            try { $results['sent'][$key] = $this->sendOne($db, $setting, $type, $key); }
            catch (Throwable $exception) { $results['failed'][$key] = $exception->getMessage(); }
        }
        return $results;
    }

    private function sendOne(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        $d = $this->definition($type);
        [$noorder, $procedure, $template] = array_pad(explode('|', $key, 3), 3, '');
        if (! $noorder || ! $procedure || ($d['has_template'] && ! $template)) throw new RuntimeException('Kunci Observation tidak valid.');
        $query = $db->table($d['request'])->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$d['request']}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure_table'], "{$d['procedure_table']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure_table']}.{$d['mapping_key']}")
            ->join($d['specimen'], function ($join) use ($d) {
                $join->on("{$d['specimen']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['specimen']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['specimen']}.id_template", '=', "{$d['detail']}.id_template");
            })->join($d['exam'], function ($join) use ($d) {
                $join->on("{$d['exam']}.no_rawat", '=', "{$d['request']}.no_rawat")->on("{$d['exam']}.tgl_periksa", '=', "{$d['request']}.tgl_hasil")->on("{$d['exam']}.jam", '=', "{$d['request']}.jam_hasil")->on("{$d['exam']}.dokter_perujuk", '=', "{$d['request']}.dokter_perujuk");
            })->join($d['result'], function ($join) use ($d) {
                $join->on("{$d['result']}.no_rawat", '=', "{$d['exam']}.no_rawat")->on("{$d['result']}.tgl_periksa", '=', "{$d['exam']}.tgl_periksa")->on("{$d['result']}.jam", '=', "{$d['exam']}.jam");
                if ($d['has_template']) {
                    $join->on("{$d['result']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw")->on("{$d['result']}.id_template", '=', "{$d['detail']}.id_template");
                }
            })->join('pegawai', 'pegawai.nik', '=', "{$d['exam']}.kd_dokter")->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin($d['output'], function ($join) use ($d) {
                $join->on("{$d['output']}.noorder", '=', "{$d['specimen']}.noorder")->on("{$d['output']}.kd_jenis_prw", '=', "{$d['specimen']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['output']}.id_template", '=', "{$d['specimen']}.id_template");
            })->where("{$d['request']}.noorder", $noorder)->where("{$d['detail']}.kd_jenis_prw", $procedure);
        if ($d['has_template']) $query->where("{$d['detail']}.id_template", $template);
        $row = $query->selectRaw("reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, {$d['request']}.tgl_hasil, {$d['request']}.jam_hasil, {$d['procedure_table']}.{$d['procedure_name']} as examination, {$d['mapping']}.code, {$d['mapping']}.system, {$d['mapping']}.display, {$d['result']}.{$d['result_column']} as result_value, {$d['specimen']}.id_specimen, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$d['output']}.id_observation")->first();
        if (! $row) throw new RuntimeException('Data Observation tidak ditemukan.');
        if (filled($row->id_observation)) throw new RuntimeException('Observation sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->practitioner_nik, 'ID Encounter' => $row->id_encounter, 'ID Specimen' => $row->id_specimen] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/').'/Observation', [
            'resourceType' => 'Observation', 'identifier' => [['system' => "http://sys-ids.kemkes.go.id/observation/{$setting->satusehat_organization_id}", 'value' => "{$noorder}.".($d['has_template'] ? $template : $procedure)]],
            'status' => 'final', 'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => $d['category'], 'display' => $d['category_display']]]]],
            'code' => ['coding' => [['system' => $row->system, 'code' => $row->code, 'display' => $row->display]]],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp)],
            'performer' => [['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->practitioner_nik)]],
            'encounter' => ['reference' => "Encounter/{$row->id_encounter}", 'display' => "Hasil Pemeriksaan {$row->examination} No.Rawat {$row->no_rawat}, Pasien {$row->nm_pasien}"],
            'specimen' => ['reference' => "Specimen/{$row->id_specimen}"],
            'effectiveDateTime' => Carbon::parse("{$row->tgl_hasil} {$row->jam_hasil}", 'Asia/Jakarta')->toIso8601String(),
            'valueString' => preg_replace('/\R/u', '<br>', str_replace("\t", ' ', $row->result_value ?? '')),
        ]);
        $id = $response->json('id');
        if ($response->failed() || blank($id)) throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Observation.');
        $where = ['noorder' => $noorder, 'kd_jenis_prw' => $procedure];
        if ($d['has_template']) $where['id_template'] = $template;
        $db->table($d['output'])->updateOrInsert($where, ['id_observation' => $id]);
        return $id;
    }

    private function definition(string $type): array
    {
        $definitions = [
            'lab-mb' => ['has_template' => true, 'request' => 'permintaan_labmb', 'detail' => 'permintaan_detail_permintaan_labmb', 'procedure_table' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'specimen' => 'satu_sehat_specimen_lab_mb', 'exam' => 'periksa_lab', 'result' => 'detail_periksa_lab', 'result_column' => 'nilai', 'output' => 'satu_sehat_observation_lab_mb', 'category' => 'laboratory', 'category_display' => 'Laboratory'],
            'lab-pk' => ['has_template' => true, 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure_table' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'specimen' => 'satu_sehat_specimen_lab', 'exam' => 'periksa_lab', 'result' => 'detail_periksa_lab', 'result_column' => 'nilai', 'output' => 'satu_sehat_observation_lab', 'category' => 'laboratory', 'category_display' => 'Laboratory'],
            'radiology' => ['has_template' => false, 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure_table' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'procedure_name' => 'nm_perawatan', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw', 'specimen' => 'satu_sehat_specimen_radiologi', 'exam' => 'periksa_radiologi', 'result' => 'hasil_radiologi', 'result_column' => 'hasil', 'output' => 'satu_sehat_observation_radiologi', 'category' => 'imaging', 'category_display' => 'Imaging'],
        ];
        return $definitions[$type] ?? throw new RuntimeException('Jenis Observation tidak valid.');
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id'); if ($response->failed() || blank($id)) throw new RuntimeException("{$resource} dengan NIK {$nik} tidak ditemukan di SATUSEHAT."); return $id;
    }

    private function token(ExternalDatabaseSetting $setting): string
    {
        if ($this->token) return $this->token;
        $response = Http::asForm()->acceptJson()->timeout(30)->post(rtrim($setting->satusehat_auth_url, '/').'/accesstoken?grant_type=client_credentials', ['client_id' => $setting->satusehat_client_id, 'client_secret' => $setting->satusehat_client_secret]);
        $this->token = $response->json('access_token'); if ($response->failed() || blank($this->token)) throw new RuntimeException('Autentikasi SATUSEHAT gagal.'); return $this->token;
    }

    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'Organization ID' => $setting->satusehat_organization_id, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
    }
}
