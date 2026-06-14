<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatConditionSender;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ConditionController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager)
    {
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
        $conditions = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $baseQuery = $this->baseQuery($connection->table('reg_periksa'), $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub((clone $baseQuery)->select([
                'diagnosa_pasien.no_rawat',
                'diagnosa_pasien.kd_penyakit',
                'diagnosa_pasien.status',
                'satu_sehat_condition.id_condition',
            ]), 'conditions');

            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $query) => $query->whereNull('id_condition')->orWhere('id_condition', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('id_condition')->where('id_condition', '<>', '')->count(),
            ];

            $query = $baseQuery->select([
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'reg_periksa.no_rawat',
                'reg_periksa.no_rkm_medis', 'reg_periksa.stts', 'reg_periksa.status_lanjut',
                'pasien.nm_pasien', 'pasien.no_ktp', 'satu_sehat_encounter.id_encounter',
                'diagnosa_pasien.kd_penyakit', 'diagnosa_pasien.status as status_diagnosa',
                'diagnosa_pasien.prioritas', 'penyakit.nm_penyakit', 'satu_sehat_condition.id_condition',
            ]);

            if ($status === 'pending') {
                $query->where(fn (Builder $query) => $query->whereNull('satu_sehat_condition.id_condition')->orWhere('satu_sehat_condition.id_condition', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('satu_sehat_condition.id_condition')->where('satu_sehat_condition.id_condition', '<>', '');
            }

            $conditions = $query->orderByDesc('reg_periksa.tgl_registrasi')
                ->orderByDesc('reg_periksa.jam_reg')
                ->orderBy('diagnosa_pasien.prioritas')
                ->paginate(20)
                ->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal atau tabel Condition belum dapat diakses. Periksa konfigurasi database SIMRS.';
        }

        return view('pages.satusehat.conditions.index', [
            'title' => 'Condition SATUSEHAT',
            'conditions' => $conditions,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatConditionSender $sender)
    {
        $data = $request->validate([
            'conditions' => ['required', 'array', 'min:1', 'max:20'],
            'conditions.*' => ['required', 'string', 'max:100'],
        ], ['conditions.required' => 'Pilih minimal satu Condition yang akan dikirim.']);

        try {
            $results = $sender->sendMany($data['conditions']);

            return back()
                ->with('success', count($results['sent']).' Condition berhasil dikirim.')
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Builder $query, string $from, string $to, string $search): Builder
    {
        return $query
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('diagnosa_pasien', 'diagnosa_pasien.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
            ->leftJoin('satu_sehat_condition', function ($join) {
                $join->on('satu_sehat_condition.no_rawat', '=', 'diagnosa_pasien.no_rawat')
                    ->on('satu_sehat_condition.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')
                    ->on('satu_sehat_condition.status', '=', 'diagnosa_pasien.status');
            })
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = "%{$search}%";
                $query->where(fn (Builder $query) => $query
                    ->where('reg_periksa.no_rawat', 'like', $like)
                    ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)
                    ->orWhere('pasien.no_ktp', 'like', $like)
                    ->orWhere('diagnosa_pasien.kd_penyakit', 'like', $like)
                    ->orWhere('penyakit.nm_penyakit', 'like', $like)
                    ->orWhere('reg_periksa.stts', 'like', $like)
                    ->orWhere('reg_periksa.status_lanjut', 'like', $like));
            });
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
