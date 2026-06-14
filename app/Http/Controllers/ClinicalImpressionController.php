<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatClinicalImpressionSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class ClinicalImpressionController extends Controller
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
        $clinicalImpressions = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'clinical_impressions');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $query) => $query->whereNull('id_clinicalimpression')->orWhere('id_clinicalimpression', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('id_clinicalimpression')->where('id_clinicalimpression', '<>', '')->count(),
            ];

            $query = $connection->query()->fromSub($base, 'clinical_impressions');
            if ($status === 'pending') {
                $query->where(fn (Builder $query) => $query->whereNull('id_clinicalimpression')->orWhere('id_clinicalimpression', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('id_clinicalimpression')->where('id_clinicalimpression', '<>', '');
            }

            $clinicalImpressions = $query->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal atau tabel Clinical Impression belum dapat diakses. Periksa konfigurasi database SIMRS.';
        }

        return view('pages.satusehat.clinical-impressions.index', [
            'title' => 'Clinical Impression SATUSEHAT',
            'clinicalImpressions' => $clinicalImpressions,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatClinicalImpressionSender $sender)
    {
        $data = $request->validate([
            'clinical_impressions' => ['required', 'array', 'min:1', 'max:20'],
            'clinical_impressions.*' => ['required', 'string', 'max:120'],
        ], ['clinical_impressions.required' => 'Pilih minimal satu Clinical Impression yang akan dikirim.']);

        try {
            $results = $sender->sendMany($data['clinical_impressions']);

            return back()
                ->with('success', count($results['sent']).' Clinical Impression berhasil dikirim.')
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $connection, string $from, string $to, string $search): Builder
    {
        return $this->sourceQuery($connection, 'pemeriksaan_ralan', 'Ralan', $from, $to, $search)
            ->unionAll($this->sourceQuery($connection, 'pemeriksaan_ranap', 'Ranap', $from, $to, $search));
    }

    private function sourceQuery(Connection $connection, string $source, string $sourceStatus, string $from, string $to, string $search): Builder
    {
        return $connection->table('reg_periksa')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('satu_sehat_condition', function ($join) use ($sourceStatus) {
                $join->on('satu_sehat_condition.no_rawat', '=', 'reg_periksa.no_rawat')
                    ->where('satu_sehat_condition.status', $sourceStatus);
            })
            ->join('penyakit', 'penyakit.kd_penyakit', '=', 'satu_sehat_condition.kd_penyakit')
            ->join($source, "{$source}.no_rawat", '=', 'reg_periksa.no_rawat')
            ->join('pegawai', "{$source}.nip", '=', 'pegawai.nik')
            ->leftJoin('satu_sehat_clinicalimpression', function ($join) use ($source, $sourceStatus) {
                $join->on('satu_sehat_clinicalimpression.no_rawat', '=', "{$source}.no_rawat")
                    ->on('satu_sehat_clinicalimpression.tgl_perawatan', '=', "{$source}.tgl_perawatan")
                    ->on('satu_sehat_clinicalimpression.jam_rawat', '=', "{$source}.jam_rawat")
                    ->where('satu_sehat_clinicalimpression.status', $sourceStatus);
            })
            ->where("{$source}.penilaian", '<>', '')
            ->whereNotNull('satu_sehat_condition.id_condition')
            ->where('satu_sehat_condition.id_condition', '<>', '')
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $query) use ($search, $source) {
                $like = "%{$search}%";
                $query->where(fn (Builder $query) => $query
                    ->where('reg_periksa.no_rawat', 'like', $like)
                    ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)
                    ->orWhere('pasien.no_ktp', 'like', $like)
                    ->orWhere('pegawai.nama', 'like', $like)
                    ->orWhere('pegawai.no_ktp', 'like', $like)
                    ->orWhere('satu_sehat_condition.kd_penyakit', 'like', $like)
                    ->orWhere('penyakit.nm_penyakit', 'like', $like)
                    ->orWhere("{$source}.penilaian", 'like', $like));
            })
            ->select([
                'reg_periksa.tgl_registrasi', 'reg_periksa.jam_reg', 'reg_periksa.no_rawat', 'reg_periksa.no_rkm_medis', 'reg_periksa.stts',
                'pasien.nm_pasien', 'pasien.no_ktp', 'satu_sehat_encounter.id_encounter',
                'pegawai.nama as nama_praktisi', 'pegawai.no_ktp as ktp_praktisi',
                "{$source}.tgl_perawatan", "{$source}.jam_rawat", "{$source}.keluhan", "{$source}.pemeriksaan", "{$source}.penilaian",
                'satu_sehat_condition.kd_penyakit', 'penyakit.nm_penyakit', 'satu_sehat_condition.id_condition',
                'satu_sehat_clinicalimpression.id_clinicalimpression',
            ])
            ->selectRaw('? as source_status', [$sourceStatus]);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
