<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatDiagnosticReportSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class DiagnosticReportController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $definition = $this->definition($type);
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,pending,sent'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'pending';
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');
        $reports = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $definition, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'reports');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $query) => $query->whereNull('id_diagnosticreport')->orWhere('id_diagnosticreport', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('id_diagnosticreport')->where('id_diagnosticreport', '<>', '')->count(),
            ];
            $query = $connection->query()->fromSub($base, 'reports');
            if ($status === 'pending') {
                $query->where(fn (Builder $query) => $query->whereNull('id_diagnosticreport')->orWhere('id_diagnosticreport', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('id_diagnosticreport')->where('id_diagnosticreport', '<>', '');
            }
            $reports = $query->orderByDesc('tgl_hasil')->orderByDesc('jam_hasil')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = "Database eksternal atau tabel Diagnostic Report {$definition['label']} belum dapat diakses. Periksa konfigurasi database SIMRS.";
        }

        return view('pages.satusehat.diagnostic-reports.index', [
            'title' => "Diagnostic Report {$definition['label']} SATUSEHAT",
            'type' => $type,
            'definition' => $definition,
            'reports' => $reports,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatDiagnosticReportSender $sender, string $type)
    {
        $definition = $this->definition($type);
        $data = $request->validate([
            'diagnostic_reports' => ['required', 'array', 'min:1', 'max:20'],
            'diagnostic_reports.*' => ['required', 'string', 'max:120'],
        ], ['diagnostic_reports.required' => "Pilih minimal satu Diagnostic Report {$definition['label']} yang akan dikirim."]);

        try {
            $results = $sender->sendMany($type, $data['diagnostic_reports']);

            return back()->with('success', count($results['sent'])." Diagnostic Report {$definition['label']} berhasil dikirim.")
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $connection, array $d, string $from, string $to, string $search): Builder
    {
        $query = $connection->table('reg_periksa')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join($d['request'], "{$d['request']}.no_rawat", '=', 'reg_periksa.no_rawat')
            ->join($d['detail'], "{$d['detail']}.noorder", '=', "{$d['request']}.noorder")
            ->join($d['procedure'], "{$d['procedure']}.{$d['procedure_key']}", '=', "{$d['detail']}.{$d['procedure_key']}")
            ->join($d['mapping'], "{$d['mapping']}.{$d['mapping_key']}", '=', "{$d['procedure']}.{$d['mapping_key']}")
            ->join($d['service'], function ($join) use ($d) {
                $join->on("{$d['service']}.noorder", '=', "{$d['detail']}.noorder")
                    ->on("{$d['service']}.kd_jenis_prw", '=', "{$d['detail']}.kd_jenis_prw");
                if ($d['has_template']) {
                    $join->on("{$d['service']}.id_template", '=', "{$d['detail']}.id_template");
                }
            })
            ->join($d['specimen'], function ($join) use ($d) {
                $join->on("{$d['specimen']}.noorder", '=', "{$d['service']}.noorder")
                    ->on("{$d['specimen']}.kd_jenis_prw", '=', "{$d['service']}.kd_jenis_prw");
                if ($d['has_template']) {
                    $join->on("{$d['specimen']}.id_template", '=', "{$d['service']}.id_template");
                }
            })
            ->join($d['exam'], function ($join) use ($d) {
                $join->on("{$d['exam']}.no_rawat", '=', "{$d['request']}.no_rawat")
                    ->on("{$d['exam']}.tgl_periksa", '=', "{$d['request']}.tgl_hasil")
                    ->on("{$d['exam']}.jam", '=', "{$d['request']}.jam_hasil")
                    ->on("{$d['exam']}.dokter_perujuk", '=', "{$d['request']}.dokter_perujuk");
            })
            ->join($d['conclusion_table'], function ($join) use ($d) {
                $join->on("{$d['conclusion_table']}.no_rawat", '=', "{$d['exam']}.no_rawat")
                    ->on("{$d['conclusion_table']}.tgl_periksa", '=', "{$d['exam']}.tgl_periksa")
                    ->on("{$d['conclusion_table']}.jam", '=', "{$d['exam']}.jam");
            })
            ->join($d['observation'], function ($join) use ($d) {
                $join->on("{$d['observation']}.noorder", '=', "{$d['specimen']}.noorder")
                    ->on("{$d['observation']}.kd_jenis_prw", '=', "{$d['specimen']}.kd_jenis_prw");
                if ($d['has_template']) {
                    $join->on("{$d['observation']}.id_template", '=', "{$d['specimen']}.id_template");
                }
            })
            ->leftJoin($d['report'], function ($join) use ($d) {
                $join->on("{$d['report']}.noorder", '=', "{$d['service']}.noorder")
                    ->on("{$d['report']}.kd_jenis_prw", '=', "{$d['service']}.kd_jenis_prw");
                if ($d['has_template']) {
                    $join->on("{$d['report']}.id_template", '=', "{$d['service']}.id_template");
                }
            })
            ->join('pegawai', "{$d['exam']}.kd_dokter", '=', 'pegawai.nik')
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $query) use ($search, $d) {
                $like = "%{$search}%";
                $query->where(fn (Builder $query) => $query
                    ->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('pasien.no_ktp', 'like', $like)
                    ->orWhere('pegawai.nama', 'like', $like)->orWhere("{$d['procedure']}.{$d['procedure_name']}", 'like', $like)
                    ->orWhere("{$d['mapping']}.code", 'like', $like)->orWhere("{$d['request']}.noorder", 'like', $like));
            });

        return $query->select([
            'reg_periksa.no_rawat', 'reg_periksa.no_rkm_medis', 'pasien.nm_pasien', 'pasien.no_ktp',
            'pegawai.nama as nama_dokter', 'pegawai.no_ktp as ktp_dokter', 'satu_sehat_encounter.id_encounter',
            "{$d['request']}.noorder", "{$d['request']}.tgl_hasil", "{$d['request']}.jam_hasil", "{$d['request']}.diagnosa_klinis",
            "{$d['procedure']}.{$d['procedure_name']} as pemeriksaan", "{$d['mapping']}.code", "{$d['mapping']}.system", "{$d['mapping']}.display",
            "{$d['service']}.id_servicerequest", "{$d['detail']}.kd_jenis_prw", "{$d['specimen']}.id_specimen",
            "{$d['observation']}.id_observation", "{$d['report']}.id_diagnosticreport",
            "{$d['conclusion_table']}.{$d['conclusion_column']} as conclusion",
        ])->when($d['has_template'], fn (Builder $query) => $query->addSelect("{$d['detail']}.id_template"));
    }

    private function definition(string $type): array
    {
        $definitions = [
            'lab' => ['label' => 'Lab PK', 'has_template' => true, 'request' => 'permintaan_lab', 'detail' => 'permintaan_detail_permintaan_lab', 'procedure' => 'template_laboratorium', 'procedure_key' => 'id_template', 'procedure_name' => 'Pemeriksaan', 'mapping' => 'satu_sehat_mapping_lab', 'mapping_key' => 'id_template', 'service' => 'satu_sehat_servicerequest_lab', 'specimen' => 'satu_sehat_specimen_lab', 'observation' => 'satu_sehat_observation_lab', 'report' => 'satu_sehat_diagnosticreport_lab', 'exam' => 'periksa_lab', 'conclusion_table' => 'saran_kesan_lab', 'conclusion_column' => 'kesan'],
            'radiology' => ['label' => 'Radiologi', 'has_template' => false, 'request' => 'permintaan_radiologi', 'detail' => 'permintaan_pemeriksaan_radiologi', 'procedure' => 'jns_perawatan_radiologi', 'procedure_key' => 'kd_jenis_prw', 'procedure_name' => 'nm_perawatan', 'mapping' => 'satu_sehat_mapping_radiologi', 'mapping_key' => 'kd_jenis_prw', 'service' => 'satu_sehat_servicerequest_radiologi', 'specimen' => 'satu_sehat_specimen_radiologi', 'observation' => 'satu_sehat_observation_radiologi', 'report' => 'satu_sehat_diagnosticreport_radiologi', 'exam' => 'periksa_radiologi', 'conclusion_table' => 'hasil_radiologi', 'conclusion_column' => 'hasil'],
        ];
        abort_unless(isset($definitions[$type]), 404);

        return $definitions[$type];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
