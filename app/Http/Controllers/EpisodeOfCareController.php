<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatEpisodeOfCareSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class EpisodeOfCareController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager)
    {
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
            $db = $databaseManager->connection();
            SatuSehatEpisodeOfCareSender::ensureOutputTable($db);
            $base = $this->baseQuery($db, $from, $to, $search);
            $summaryQuery = $db->query()->fromSub(clone $base, 'episodes');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('sent_id')->where('sent_id', '<>', '')->count(),
            ];
            $query = $db->query()->fromSub($base, 'episodes');
            if ($status === 'pending') $query->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''));
            elseif ($status === 'sent') $query->whereNotNull('sent_id')->where('sent_id', '<>', '');
            $rows = $query->orderByDesc('discharge_date')->orderByDesc('discharge_time')->paginate(20)->withQueryString();
        } catch (Throwable) {
            $connectionError = 'Database eksternal atau tabel Episode of Care belum dapat diakses. Periksa konfigurasi dan struktur database SIMRS.';
        }

        return view('pages.satusehat.episode-of-care.index', [
            'title' => 'Episode of Care SATUSEHAT', 'rows' => $rows, 'summary' => $summary,
            'connectionError' => $connectionError, 'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatEpisodeOfCareSender $sender)
    {
        $data = $request->validate([
            'episodes' => ['required', 'array', 'min:1', 'max:20'], 'episodes.*' => ['required', 'string', 'max:100'],
        ], ['episodes.required' => 'Pilih minimal satu Episode of Care yang akan dikirim.']);
        try {
            $results = $sender->sendMany($data['episodes']);
            return back()->with('success', count($results['sent']).' Episode of Care berhasil dikirim.')->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $db, string $from, string $to, string $search): Builder
    {
        return $this->sourceQuery($db, 'pemeriksaan_ralan', 'tgl_perawatan', 'jam_rawat', 'Ralan', $from, $to, $search)
            ->unionAll($this->sourceQuery($db, 'kamar_inap', 'tgl_keluar', 'jam_keluar', 'Ranap', $from, $to, $search));
    }

    private function sourceQuery(Connection $db, string $source, string $date, string $time, string $careType, string $from, string $to, string $search): Builder
    {
        return $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join($source, "{$source}.no_rawat", '=', 'reg_periksa.no_rawat')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('diagnosa_pasien', 'diagnosa_pasien.no_rawat', '=', 'reg_periksa.no_rawat')->join('penyakit', 'penyakit.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')
            ->leftJoin('satu_sehat_episode_of_care as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'diagnosa_pasien.no_rawat')->on('sent.kd_penyakit', '=', 'diagnosa_pasien.kd_penyakit')->on('sent.status', '=', 'diagnosa_pasien.status');
            })->whereBetween("{$source}.{$date}", [$from, $to])->where('diagnosa_pasien.kd_penyakit', 'like', '%O%')
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('diagnosa_pasien.kd_penyakit', 'like', $like)->orWhere('penyakit.nm_penyakit', 'like', $like));
            })->selectRaw("reg_periksa.tgl_registrasi, reg_periksa.jam_reg, reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, pasien.no_ktp, reg_periksa.stts, reg_periksa.status_lanjut, {$source}.{$date} as discharge_date, {$source}.{$time} as discharge_time, satu_sehat_encounter.id_encounter, diagnosa_pasien.kd_penyakit, penyakit.nm_penyakit, diagnosa_pasien.status as diagnosis_status, sent.id_episode_of_care as sent_id, ? as care_type", [$careType]);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
