<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatObservationSender;
use App\Support\TtvTypes;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ObservationController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $definition = $this->definition($type);
        $filters = $request->validate([
            'ttv_type' => ['nullable', 'in:'.implode(',', array_keys(TtvTypes::all()))],
            'status' => ['nullable', 'in:all,pending,sent'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $ttvType = $filters['ttv_type'] ?? 'suhu';
        $status = $filters['status'] ?? 'pending';
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');
        $rows = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $type === 'ttv'
                ? $this->ttvQuery($connection, $ttvType, $from, $to, $search)
                : $this->diagnosticQuery($connection, $definition, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'observations');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('sent_id')->where('sent_id', '<>', '')->count(),
            ];
            $query = $connection->query()->fromSub($base, 'observations');
            if ($status === 'pending') {
                $query->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('sent_id')->where('sent_id', '<>', '');
            }
            $rows = $query->orderByDesc('event_date')->orderByDesc('event_time')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = "Database eksternal atau tabel {$definition['label']} belum dapat diakses. Periksa konfigurasi dan struktur database SIMRS.";
        }

        return view('pages.satusehat.observations.index', [
            'title' => "{$definition['label']} SATUSEHAT", 'type' => $type, 'definition' => $definition,
            'ttvTypes' => TtvTypes::all(), 'rows' => $rows, 'summary' => $summary,
            'connectionError' => $connectionError, 'filters' => compact('ttvType', 'status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatObservationSender $sender, string $type)
    {
        $definition = $this->definition($type);
        $data = $request->validate([
            'ttv_type' => ['nullable', 'in:'.implode(',', array_keys(TtvTypes::all()))],
            'observations' => ['required', 'array', 'min:1', 'max:20'],
            'observations.*' => ['required', 'string', 'max:180'],
        ], ['observations.required' => "Pilih minimal satu {$definition['label']} yang akan dikirim."]);
        try {
            $results = $sender->sendMany($type, $data['observations'], $data['ttv_type'] ?? null);
            return back()->with('success', count($results['sent'])." {$definition['label']} berhasil dikirim.")->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function diagnosticQuery(Connection $db, array $d, string $from, string $to, string $search): Builder
    {
        $query = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($d['request'], "{$d['request']}.no_rawat", '=', 'reg_periksa.no_rawat')
            ->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure'], "{$d['procedure']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure']}.{$d['mapping_key']}")
            ->join($d['specimen'], function ($join) use ($d) {
                $join->on("{$d['specimen']}.noorder", '=', "{$d['detail']}.noorder")->on("{$d['specimen']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['specimen']}.id_template", '=', "{$d['detail']}.id_template");
            })
            ->join($d['exam'], function ($join) use ($d) {
                $join->on("{$d['exam']}.no_rawat", '=', "{$d['request']}.no_rawat")->on("{$d['exam']}.tgl_periksa", '=', "{$d['request']}.tgl_hasil")
                    ->on("{$d['exam']}.jam", '=', "{$d['request']}.jam_hasil")->on("{$d['exam']}.dokter_perujuk", '=', "{$d['request']}.dokter_perujuk");
            })
            ->join($d['result'], function ($join) use ($d) {
                $join->on("{$d['result']}.no_rawat", '=', "{$d['exam']}.no_rawat")->on("{$d['result']}.tgl_periksa", '=', "{$d['exam']}.tgl_periksa")->on("{$d['result']}.jam", '=', "{$d['exam']}.jam");
                if ($d['has_template']) {
                    $join->on("{$d['result']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw")->on("{$d['result']}.id_template", '=', "{$d['detail']}.id_template");
                }
            })
            ->leftJoin($d['output'], function ($join) use ($d) {
                $join->on("{$d['output']}.noorder", '=', "{$d['specimen']}.noorder")->on("{$d['output']}.kd_jenis_prw", '=', "{$d['specimen']}.kd_jenis_prw");
                if ($d['has_template']) $join->on("{$d['output']}.id_template", '=', "{$d['specimen']}.id_template");
            })
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pegawai', 'pegawai.nik', '=', "{$d['exam']}.kd_dokter")
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search, $d) {
                $like = "%{$search}%";
                $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere("{$d['procedure']}.{$d['procedure_name']}", 'like', $like)
                    ->orWhere("{$d['request']}.noorder", 'like', $like)->orWhere("{$d['mapping']}.code", 'like', $like));
            });

        return $query->selectRaw("{$d['request']}.noorder, {$d['detail']}.kd_jenis_prw, ".($d['has_template'] ? "{$d['detail']}.id_template" : "''")." as id_template, reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, {$d['request']}.tgl_hasil as event_date, {$d['request']}.jam_hasil as event_time, {$d['procedure']}.{$d['procedure_name']} as examination, {$d['mapping']}.code as mapping_code, {$d['mapping']}.display as mapping_display, {$d['result']}.{$d['result_column']} as result_value, {$d['specimen']}.id_specimen, pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$d['output']}.id_observation as sent_id, '' as source_status");
    }

    private function ttvQuery(Connection $db, string $type, string $from, string $to, string $search): Builder
    {
        $definition = TtvTypes::get($type);
        return $this->ttvSource($db, $definition, 'pemeriksaan_ralan', 'Ralan', $from, $to, $search)
            ->unionAll($this->ttvSource($db, $definition, 'pemeriksaan_ranap', 'Ranap', $from, $to, $search));
    }

    private function ttvSource(Connection $db, array $d, string $source, string $status, string $from, string $to, string $search): Builder
    {
        return $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')->join($source, "{$source}.no_rawat", '=', 'reg_periksa.no_rawat')
            ->join('pegawai', 'pegawai.nik', '=', "{$source}.nip")->leftJoin($d['table'], function ($join) use ($d, $source, $status) {
                $join->on("{$d['table']}.no_rawat", '=', "{$source}.no_rawat")->on("{$d['table']}.tgl_perawatan", '=', "{$source}.tgl_perawatan")
                    ->on("{$d['table']}.jam_rawat", '=', "{$source}.jam_rawat")->where("{$d['table']}.status", $status);
            })->where("{$source}.{$d['column']}", '<>', '')->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('pegawai.nama', 'like', $like));
            })->selectRaw("'' as noorder, '' as kd_jenis_prw, '' as id_template, reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, {$source}.tgl_perawatan as event_date, {$source}.jam_rawat as event_time, ? as examination, '' as mapping_code, '' as mapping_display, {$source}.{$d['column']} as result_value, '' as id_specimen, pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$d['table']}.id_observation as sent_id, ? as source_status", [$d['label'], $status]);
    }

    private function definition(string $type): array
    {
        $definitions = [
            'lab-mb' => ['label' => 'Observation Lab MB', 'has_template' => true, 'request' => 'permintaan_labmb', 'detail' => 'permintaan_detail_permintaan_labmb', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'specimen' => 'satu_sehat_specimen_lab_mb', 'exam' => 'periksa_lab', 'result' => 'detail_periksa_lab', 'result_column' => 'nilai', 'output' => 'satu_sehat_observation_lab_mb', 'category' => 'laboratory', 'category_display' => 'Laboratory'],
            'lab-pk' => ['label' => 'Observation Lab PK', 'has_template' => true, 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'specimen' => 'satu_sehat_specimen_lab', 'exam' => 'periksa_lab', 'result' => 'detail_periksa_lab', 'result_column' => 'nilai', 'output' => 'satu_sehat_observation_lab', 'category' => 'laboratory', 'category_display' => 'Laboratory'],
            'radiology' => ['label' => 'Observation Radiologi', 'has_template' => false, 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'procedure_name' => 'nm_perawatan', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw', 'specimen' => 'satu_sehat_specimen_radiologi', 'exam' => 'periksa_radiologi', 'result' => 'hasil_radiologi', 'result_column' => 'hasil', 'output' => 'satu_sehat_observation_radiologi', 'category' => 'imaging', 'category_display' => 'Imaging'],
            'ttv' => ['label' => 'Observation TTV'],
        ];
        abort_unless(isset($definitions[$type]), 404);
        return $definitions[$type];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
