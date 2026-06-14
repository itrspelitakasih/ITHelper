<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatEncounterSender;
use Throwable;

class EncounterController extends Controller
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
        $encounters = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $baseQuery = $this->baseQuery($connection->table('reg_periksa'), $from, $to, $search);

            $summaryQuery = $connection->query()
                ->fromSub((clone $baseQuery)->select([
                    'reg_periksa.no_rawat',
                    'satu_sehat_encounter.id_encounter',
                ]), 'encounters');

            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)
                    ->where(fn (Builder $query) => $query
                        ->whereNull('id_encounter')
                        ->orWhere('id_encounter', ''))
                    ->count(),
                'sent' => (clone $summaryQuery)
                    ->whereNotNull('id_encounter')
                    ->where('id_encounter', '<>', '')
                    ->count(),
            ];

            $query = $baseQuery->select([
                'reg_periksa.tgl_registrasi',
                'reg_periksa.jam_reg',
                'reg_periksa.no_rawat',
                'reg_periksa.no_rkm_medis',
                'reg_periksa.kd_dokter',
                'reg_periksa.kd_poli',
                'reg_periksa.stts',
                'reg_periksa.status_lanjut',
                'pasien.nm_pasien',
                'pasien.no_ktp',
                'pegawai.nama as nama_dokter',
                'pegawai.no_ktp as ktp_dokter',
                'poliklinik.nm_poli',
                'satu_sehat_mapping_lokasi_ralan.id_lokasi_satusehat',
                'satu_sehat_encounter.id_encounter',
            ]);

            if ($status === 'pending') {
                $query->where(fn (Builder $query) => $query
                    ->whereNull('satu_sehat_encounter.id_encounter')
                    ->orWhere('satu_sehat_encounter.id_encounter', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('satu_sehat_encounter.id_encounter')
                    ->where('satu_sehat_encounter.id_encounter', '<>', '');
            }

            $encounters = $query
                ->orderByDesc('reg_periksa.tgl_registrasi')
                ->orderByDesc('reg_periksa.jam_reg')
                ->paginate(20)
                ->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Database Eksternal.';
        }

        return view('pages.satusehat.encounters.index', [
            'title' => 'Encounter SATUSEHAT',
            'encounters' => $encounters,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatEncounterSender $sender)
    {
        $data = $request->validate([
            'encounters' => ['required', 'array', 'min:1', 'max:20'],
            'encounters.*' => ['required', 'string', 'max:30'],
        ], [
            'encounters.required' => 'Pilih minimal satu encounter yang akan dikirim.',
        ]);

        try {
            $results = $sender->sendMany($data['encounters']);
            $message = count($results['sent']).' encounter berhasil dikirim.';

            return back()
                ->with('success', $message)
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Builder $query, string $from, string $to, string $search): Builder
    {
        return $query
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')
            ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
            ->join(
                'satu_sehat_mapping_lokasi_ralan',
                'satu_sehat_mapping_lokasi_ralan.kd_poli',
                '=',
                'poliklinik.kd_poli'
            )
            ->leftJoin('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->where('reg_periksa.status_bayar', 'Sudah Bayar')
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = "%{$search}%";

                $query->where(function (Builder $query) use ($like) {
                    $query->where('reg_periksa.no_rawat', 'like', $like)
                        ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                        ->orWhere('pasien.nm_pasien', 'like', $like)
                        ->orWhere('pasien.no_ktp', 'like', $like)
                        ->orWhere('pegawai.nama', 'like', $like)
                        ->orWhere('poliklinik.nm_poli', 'like', $like)
                        ->orWhere('reg_periksa.stts', 'like', $like)
                        ->orWhere('reg_periksa.status_lanjut', 'like', $like);
                });
            });
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
