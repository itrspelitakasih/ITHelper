<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatProcedureSender;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ProcedureController extends Controller
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
        $procedures = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection->table('reg_periksa'), $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub((clone $base)->select([
                'prosedur_pasien.no_rawat', 'prosedur_pasien.kode', 'prosedur_pasien.status', 'satu_sehat_procedure.id_procedure',
            ]), 'procedures');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('id_procedure')->orWhere('id_procedure', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('id_procedure')->where('id_procedure', '<>', '')->count(),
            ];
            $query = $base->select([
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'reg_periksa.no_rawat', 'reg_periksa.no_rkm_medis',
                'reg_periksa.stts', 'reg_periksa.status_lanjut', 'pasien.nm_pasien', 'pasien.no_ktp',
                'satu_sehat_encounter.id_encounter', 'prosedur_pasien.kode', 'prosedur_pasien.status as status_prosedur',
                'prosedur_pasien.prioritas', 'prosedur_pasien.jumlah', 'icd9.deskripsi_panjang', 'satu_sehat_procedure.id_procedure',
            ]);
            if ($status === 'pending') {
                $query->where(fn (Builder $q) => $q->whereNull('satu_sehat_procedure.id_procedure')->orWhere('satu_sehat_procedure.id_procedure', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('satu_sehat_procedure.id_procedure')->where('satu_sehat_procedure.id_procedure', '<>', '');
            }
            $procedures = $query->orderByDesc('reg_periksa.tgl_registrasi')->orderByDesc('reg_periksa.jam_reg')->orderBy('prosedur_pasien.prioritas')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal atau tabel Procedure belum dapat diakses. Periksa konfigurasi database SIMRS.';
        }

        return view('pages.satusehat.procedures.index', [
            'title' => 'Procedure SATUSEHAT', 'procedures' => $procedures, 'summary' => $summary,
            'connectionError' => $connectionError, 'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatProcedureSender $sender)
    {
        $data = $request->validate([
            'procedures' => ['required', 'array', 'min:1', 'max:20'],
            'procedures.*' => ['required', 'string', 'max:100'],
        ], ['procedures.required' => 'Pilih minimal satu Procedure yang akan dikirim.']);
        try {
            $results = $sender->sendMany($data['procedures']);
            return back()->with('success', count($results['sent']).' Procedure berhasil dikirim.')->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Builder $query, string $from, string $to, string $search): Builder
    {
        return $query->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('prosedur_pasien', 'prosedur_pasien.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('icd9', 'icd9.kode', '=', 'prosedur_pasien.kode')
            ->leftJoin('satu_sehat_procedure', function ($join) {
                $join->on('satu_sehat_procedure.no_rawat', '=', 'prosedur_pasien.no_rawat')
                    ->on('satu_sehat_procedure.kode', '=', 'prosedur_pasien.kode')
                    ->on('satu_sehat_procedure.status', '=', 'prosedur_pasien.status');
            })
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%";
                $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('pasien.no_ktp', 'like', $like)
                    ->orWhere('prosedur_pasien.kode', 'like', $like)->orWhere('icd9.deskripsi_panjang', 'like', $like)
                    ->orWhere('reg_periksa.stts', 'like', $like)->orWhere('reg_periksa.status_lanjut', 'like', $like));
            });
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
