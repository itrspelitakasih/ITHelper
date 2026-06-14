<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatTtvSender;
use App\Support\TtvTypes;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class TtvController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'in:'.implode(',', array_keys(TtvTypes::all()))],
            'status' => ['nullable', 'in:all,pending,sent'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $type = $filters['type'] ?? 'suhu';
        $status = $filters['status'] ?? 'pending';
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');
        $observations = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $type, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'ttv');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $query) => $query->whereNull('id_observation')->orWhere('id_observation', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('id_observation')->where('id_observation', '<>', '')->count(),
            ];

            $query = $connection->query()->fromSub($base, 'ttv');
            if ($status === 'pending') {
                $query->where(fn (Builder $query) => $query->whereNull('id_observation')->orWhere('id_observation', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('id_observation')->where('id_observation', '<>', '');
            }

            $observations = $query->orderByDesc('tgl_perawatan')->orderByDesc('jam_rawat')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal atau tabel TTV belum dapat diakses. Periksa konfigurasi database SIMRS.';
        }

        return view('pages.satusehat.ttv.index', [
            'title' => 'Observation TTV SATUSEHAT',
            'types' => TtvTypes::all(),
            'observations' => $observations,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('type', 'status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatTtvSender $sender)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(TtvTypes::all()))],
            'observations' => ['required', 'array', 'min:1', 'max:20'],
            'observations.*' => ['required', 'string', 'max:100'],
        ], ['observations.required' => 'Pilih minimal satu Observation TTV yang akan dikirim.']);

        try {
            $results = $sender->sendMany($data['type'], $data['observations']);

            return back()
                ->with('success', count($results['sent']).' Observation TTV berhasil dikirim.')
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $connection, string $type, string $from, string $to, string $search): Builder
    {
        $definition = TtvTypes::get($type);
        $ralan = $this->sourceQuery($connection, $definition, 'pemeriksaan_ralan', 'Ralan', $from, $to, $search);
        $ranap = $this->sourceQuery($connection, $definition, 'pemeriksaan_ranap', 'Ranap', $from, $to, $search);

        return $ralan->unionAll($ranap);
    }

    private function sourceQuery(Connection $connection, array $definition, string $source, string $sourceStatus, string $from, string $to, string $search): Builder
    {
        return $connection->table('reg_periksa')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join($source, "{$source}.no_rawat", '=', 'reg_periksa.no_rawat')
            ->join('pegawai', "{$source}.nip", '=', 'pegawai.nik')
            ->leftJoin($definition['table'], function ($join) use ($definition, $source, $sourceStatus) {
                $join->on("{$definition['table']}.no_rawat", '=', "{$source}.no_rawat")
                    ->on("{$definition['table']}.tgl_perawatan", '=', "{$source}.tgl_perawatan")
                    ->on("{$definition['table']}.jam_rawat", '=', "{$source}.jam_rawat")
                    ->where("{$definition['table']}.status", $sourceStatus);
            })
            ->where("{$source}.{$definition['column']}", '<>', '')
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = "%{$search}%";
                $query->where(fn (Builder $query) => $query
                    ->where('reg_periksa.no_rawat', 'like', $like)
                    ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)
                    ->orWhere('pasien.no_ktp', 'like', $like)
                    ->orWhere('pegawai.nama', 'like', $like)
                    ->orWhere('pegawai.no_ktp', 'like', $like));
            })
            ->select([
                'reg_periksa.no_rawat', 'reg_periksa.no_rkm_medis', 'reg_periksa.stts',
                'pasien.nm_pasien', 'pasien.no_ktp', 'satu_sehat_encounter.id_encounter',
                'pegawai.nama as nama_praktisi', 'pegawai.no_ktp as ktp_praktisi',
                "{$source}.tgl_perawatan", "{$source}.jam_rawat",
                "{$source}.{$definition['column']} as value",
                "{$definition['table']}.id_observation",
            ])
            ->selectRaw('? as source_status', [$sourceStatus]);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
