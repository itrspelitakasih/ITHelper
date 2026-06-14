<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatClinicalResourceSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ClinicalResourceController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $group, string $type)
    {
        $definition = $this->definition($group, $type);
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,pending,sent'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'], 'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'pending';
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');
        $rows = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $group, $type, $definition, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'resources');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('sent_id')->where('sent_id', '<>', '')->count(),
            ];
            $query = $connection->query()->fromSub($base, 'resources');
            if ($status === 'pending') $query->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''));
            elseif ($status === 'sent') $query->whereNotNull('sent_id')->where('sent_id', '<>', '');
            $rows = $query->orderByDesc('event_date')->orderByDesc('event_time')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = "Database eksternal atau tabel {$definition['label']} belum dapat diakses. Periksa konfigurasi dan struktur database SIMRS.";
        }

        return view('pages.satusehat.clinical-resources.index', compact('group', 'type', 'definition', 'rows', 'summary', 'connectionError') + [
            'title' => "{$definition['label']} SATUSEHAT", 'tabs' => $this->tabs($group), 'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatClinicalResourceSender $sender, string $group, string $type)
    {
        $definition = $this->definition($group, $type);
        $data = $request->validate(['resources' => ['required', 'array', 'min:1', 'max:20'], 'resources.*' => ['required', 'string', 'max:220']],
            ['resources.required' => "Pilih minimal satu {$definition['label']} yang akan dikirim."]);
        try {
            $results = $sender->sendMany($group, $type, $data['resources']);
            return back()->with('success', count($results['sent'])." {$definition['label']} berhasil dikirim.")->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $db, string $group, string $type, array $d, string $from, string $to, string $search): Builder
    {
        if ($group === 'rme') return $this->rmeQuery($db, $type, $from, $to, $search);
        if ($group === 'risk-assessments') return $this->riskQuery($db, $from, $to, $search);
        return $this->diagnosticQuery($db, $d, $group, $from, $to, $search);
    }

    private function rmeQuery(Connection $db, string $type, string $from, string $to, string $search): Builder
    {
        $resume = $type === 'rawat-inap' ? 'resume_pasien_ranap' : 'resume_pasien';
        $output = $type === 'rawat-inap' ? 'satu_sehat_rme_rawat_inap' : 'satu_sehat_rme_rawat_jalan';
        return $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($resume, "{$resume}.no_rawat", '=', 'reg_periksa.no_rawat')->join('dokter', 'dokter.kd_dokter', '=', "{$resume}.kd_dokter")
            ->join('pegawai', 'pegawai.nik', '=', 'dokter.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin($output, "{$output}.no_rawat", '=', 'reg_periksa.no_rawat')->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search, $resume) {
                $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('dokter.nm_dokter', 'like', $like)->orWhere("{$resume}.diagnosa_utama", 'like', $like));
            })->selectRaw("reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, reg_periksa.tgl_registrasi as event_date, reg_periksa.jam_reg as event_time, dokter.nm_dokter as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$output}.id_composition as sent_id, {$resume}.diagnosa_utama as detail_name, {$resume}.keluhan_utama as detail_text, '' as noorder, '' as procedure_code, '' as template_id, ? as source_status", [$type === 'rawat-inap' ? 'Rawat Inap' : 'Rawat Jalan']);
    }

    private function riskQuery(Connection $db, string $from, string $to, string $search): Builder
    {
        return $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('penilaian_awal_keperawatan_ralan as risk', 'risk.no_rawat', '=', 'reg_periksa.no_rawat')
            ->leftJoin('satu_sehat_riskassessment as sent', 'sent.no_rawat', '=', 'reg_periksa.no_rawat')->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('risk.hasil', 'like', $like));
            })->selectRaw("reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, reg_periksa.tgl_registrasi as event_date, reg_periksa.jam_reg as event_time, pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, sent.id_riskassessment as sent_id, risk.hasil as detail_name, CONCAT('Tidak seimbang: ',risk.berjalan_a,', Alat bantu: ',risk.berjalan_b,', Menopang: ',risk.berjalan_c) as detail_text, '' as noorder, '' as procedure_code, '' as template_id, 'Risiko Jatuh' as source_status");
    }

    private function diagnosticQuery(Connection $db, array $d, string $group, string $from, string $to, string $search): Builder
    {
        $query = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($d['request'], "{$d['request']}.no_rawat", '=', 'reg_periksa.no_rawat')->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure'], "{$d['procedure']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure']}.{$d['mapping_key']}");
        if ($group === 'service-requests') {
            $query->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        } else {
            $query->join($d['service'], function ($join) use ($d) {
                $join->on("{$d['service']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['service']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['service']}.id_template", '=', "{$d['detail']}.id_template");
            });
        }
        $query->leftJoin($d['output'], function ($join) use ($d) {
            $join->on("{$d['output']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['output']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
            if ($d['has_template']) $join->on("{$d['output']}.id_template", '=', "{$d['detail']}.id_template");
        })->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])->when($search !== '', function (Builder $q) use ($search, $d) {
            $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('pasien.nm_pasien', 'like', $like)
                ->orWhere("{$d['request']}.noorder", 'like', $like)->orWhere("{$d['procedure']}.{$d['procedure_name']}", 'like', $like));
        });
        $date = $group === 'service-requests' ? 'tgl_permintaan' : 'tgl_sampel';
        $time = $group === 'service-requests' ? 'jam_permintaan' : 'jam_sampel';
        $sent = $group === 'service-requests' ? 'id_servicerequest' : 'id_specimen';
        $extra = $group === 'service-requests'
            ? "pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$d['request']}.diagnosa_klinis as detail_text"
            : "'' as practitioner_name, '' as practitioner_nik, {$d['service']}.id_servicerequest as id_encounter, {$d['mapping']}.sampel_display as detail_text";
        return $query->selectRaw("reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, {$d['request']}.{$date} as event_date, {$d['request']}.{$time} as event_time, {$extra}, {$d['output']}.{$sent} as sent_id, {$d['procedure']}.{$d['procedure_name']} as detail_name, {$d['request']}.noorder, {$d['detail']}.kd_jenis_prw as procedure_code, ".($d['has_template'] ? "{$d['detail']}.id_template" : "''")." as template_id, ? as source_status", [$d['tab']]);
    }

    private function definition(string $group, string $type): array
    {
        $common = [
            'lab-mb' => ['tab' => 'Lab MB', 'has_template' => true, 'request' => 'permintaan_labmb', 'detail' => 'permintaan_detail_permintaan_labmb', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template'],
            'lab-pk' => ['tab' => 'Lab PK', 'has_template' => true, 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template'],
            'radiology' => ['tab' => 'Radiologi', 'has_template' => false, 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'procedure_name' => 'nm_perawatan', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw'],
        ];
        $definitions = match ($group) {
            'rme' => ['rawat-inap' => ['label' => 'RME Rawat Inap'], 'rawat-jalan' => ['label' => 'RME Rawat Jalan']],
            'risk-assessments' => ['risk' => ['label' => 'Risk Assessment']],
            'service-requests' => collect($common)->map(fn ($d, $key) => $d + ['label' => "Service Request {$d['tab']}", 'output' => ['lab-mb' => 'satu_sehat_servicerequest_lab_mb', 'lab-pk' => 'satu_sehat_servicerequest_lab', 'radiology' => 'satu_sehat_servicerequest_radiologi'][$key]])->all(),
            'specimens' => collect($common)->map(fn ($d, $key) => $d + ['label' => "Specimen {$d['tab']}", 'service' => ['lab-mb' => 'satu_sehat_servicerequest_lab_mb', 'lab-pk' => 'satu_sehat_servicerequest_lab', 'radiology' => 'satu_sehat_servicerequest_radiologi'][$key], 'output' => ['lab-mb' => 'satu_sehat_specimen_lab_mb', 'lab-pk' => 'satu_sehat_specimen_lab', 'radiology' => 'satu_sehat_specimen_radiologi'][$key]])->all(),
            default => [],
        };
        abort_unless(isset($definitions[$type]), 404);
        return $definitions[$type];
    }

    private function tabs(string $group): array
    {
        return match ($group) {
            'rme' => ['rawat-inap' => 'Rawat Inap', 'rawat-jalan' => 'Rawat Jalan'],
            'risk-assessments' => ['risk' => 'Risk Assessment'],
            default => ['lab-mb' => 'Lab MB', 'lab-pk' => 'Lab PK', 'radiology' => 'Radiologi'],
        };
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
