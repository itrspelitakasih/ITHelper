<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatDiagnosticReportSender
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
        $d = $this->definition($type);
        [$noorder, $procedureCode, $templateId] = array_pad(explode('|', $key, 3), 3, null);
        if (! $noorder || ! $procedureCode || ($d['has_template'] && ! $templateId)) {
            throw new RuntimeException('Kunci Diagnostic Report tidak valid.');
        }
        $query = $connection->table($d['request'])
            ->join('reg_periksa', 'reg_periksa.no_rawat', '=', "{$d['request']}.no_rawat")
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure'], "{$d['procedure']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure']}.{$d['mapping_key']}")
            ->join($d['service'], fn ($join) => $this->joinKeys($join, $d, $d['service'], $d['detail']))
            ->join($d['specimen'], fn ($join) => $this->joinKeys($join, $d, $d['specimen'], $d['service']))
            ->join($d['observation'], fn ($join) => $this->joinKeys($join, $d, $d['observation'], $d['specimen']))
            ->join($d['exam'], function ($join) use ($d) {
                $join->on("{$d['exam']}.no_rawat", '=', "{$d['request']}.no_rawat")->on("{$d['exam']}.tgl_periksa", '=', "{$d['request']}.tgl_hasil")
                    ->on("{$d['exam']}.jam", '=', "{$d['request']}.jam_hasil")->on("{$d['exam']}.dokter_perujuk", '=', "{$d['request']}.dokter_perujuk");
            })
            ->join($d['conclusion_table'], function ($join) use ($d) {
                $join->on("{$d['conclusion_table']}.no_rawat", '=', "{$d['exam']}.no_rawat")->on("{$d['conclusion_table']}.tgl_periksa", '=', "{$d['exam']}.tgl_periksa")->on("{$d['conclusion_table']}.jam", '=', "{$d['exam']}.jam");
            })
            ->join('pegawai', 'pegawai.nik', '=', "{$d['exam']}.kd_dokter")
            ->leftJoin($d['report'], fn ($join) => $this->joinKeys($join, $d, $d['report'], $d['service']))
            ->where("{$d['request']}.noorder", $noorder)->where("{$d['detail']}.kd_jenis_prw", $procedureCode);
        if ($d['has_template']) {
            $query->where("{$d['detail']}.id_template", $templateId);
        }
        $row = $query->select([
            'pasien.no_ktp', 'satu_sehat_encounter.id_encounter', 'pegawai.no_ktp as ktp_dokter',
            "{$d['request']}.tgl_hasil", "{$d['request']}.jam_hasil", "{$d['mapping']}.code", "{$d['mapping']}.system", "{$d['mapping']}.display",
            "{$d['service']}.id_servicerequest", "{$d['specimen']}.id_specimen", "{$d['observation']}.id_observation",
            "{$d['report']}.id_diagnosticreport", "{$d['conclusion_table']}.{$d['conclusion_column']} as conclusion",
        ])->first();
        if (! $row) {
            throw new RuntimeException('Data Diagnostic Report tidak ditemukan.');
        }
        if (filled($row->id_diagnosticreport)) {
            throw new RuntimeException('Diagnostic Report sudah pernah terkirim.');
        }
        foreach (['NIK pasien' => $row->no_ktp, 'NIK dokter' => $row->ktp_dokter, 'ID Encounter' => $row->id_encounter, 'ID ServiceRequest' => $row->id_servicerequest, 'ID Specimen' => $row->id_specimen, 'ID Observation' => $row->id_observation] as $label => $value) {
            if (blank($value)) {
                throw new RuntimeException("{$label} belum tersedia.");
            }
        }
        $effective = Carbon::parse("{$row->tgl_hasil} {$row->jam_hasil}", 'Asia/Jakarta')->toIso8601String();
        $identifierDetail = $d['has_template'] ? $templateId : $procedureCode;
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/').'/DiagnosticReport', [
            'resourceType' => 'DiagnosticReport',
            'identifier' => [['system' => "http://sys-ids.kemkes.go.id/diagnostic/{$setting->satusehat_organization_id}/{$d['identifier']}", 'use' => 'official', 'value' => "{$noorder}.{$identifierDetail}"]],
            'status' => 'final',
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0074', 'code' => $d['category'], 'display' => $d['display']]]]],
            'code' => ['coding' => [['code' => $row->code, 'display' => $row->display, 'system' => $row->system]]],
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp)],
            'encounter' => ['reference' => "Encounter/{$row->id_encounter}"],
            'effectiveDateTime' => $effective, 'issued' => $effective,
            'performer' => [['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->ktp_dokter)]],
            'specimen' => [['reference' => "Specimen/{$row->id_specimen}"]],
            'result' => [['reference' => "Observation/{$row->id_observation}"]],
            'basedOn' => [['reference' => "ServiceRequest/{$row->id_servicerequest}"]],
            'conclusion' => preg_replace('/\R/u', '<br>', str_replace("\t", ' ', $row->conclusion ?? '')),
        ]);
        if ($response->failed() || blank($response->json('id'))) {
            throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak Diagnostic Report.');
        }
        $where = ['noorder' => $noorder, 'kd_jenis_prw' => $procedureCode];
        if ($d['has_template']) {
            $where['id_template'] = $templateId;
        }
        $id = $response->json('id');
        $connection->table($d['report'])->updateOrInsert($where, ['id_diagnosticreport' => $id]);

        return $id;
    }

    private function joinKeys($join, array $d, string $left, string $right): void
    {
        $join->on("{$left}.noorder", '=', "{$right}.noorder")->on("{$left}.kd_jenis_prw", '=', "{$right}.kd_jenis_prw");
        if ($d['has_template']) {
            $join->on("{$left}.id_template", '=', "{$right}.id_template");
        }
    }

    private function definition(string $type): array
    {
        $definitions = [
            'lab' => ['has_template' => true, 'identifier' => 'lab', 'category' => 'LAB', 'display' => 'Laboratory', 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'service' => 'satu_sehat_servicerequest_lab', 'specimen' => 'satu_sehat_specimen_lab', 'observation' => 'satu_sehat_observation_lab', 'report' => 'satu_sehat_diagnosticreport_lab', 'exam' => 'periksa_lab', 'conclusion_table' => 'saran_kesan_lab', 'conclusion_column' => 'kesan'],
            'radiology' => ['has_template' => false, 'identifier' => 'rad', 'category' => 'RAD', 'display' => 'Radiology', 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw', 'service' => 'satu_sehat_servicerequest_radiologi', 'specimen' => 'satu_sehat_specimen_radiologi', 'observation' => 'satu_sehat_observation_radiologi', 'report' => 'satu_sehat_diagnosticreport_radiologi', 'exam' => 'periksa_radiologi', 'conclusion_table' => 'hasil_radiologi', 'conclusion_column' => 'hasil'],
        ];
        if (! isset($definitions[$type])) {
            throw new RuntimeException('Jenis Diagnostic Report tidak valid.');
        }

        return $definitions[$type];
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id');
        if ($response->failed() || blank($id)) {
            throw new RuntimeException("{$resource} dengan NIK {$nik} tidak ditemukan di SATUSEHAT.");
        }

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
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'Organization ID' => $setting->satusehat_organization_id, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) {
            if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
        }
    }
}
