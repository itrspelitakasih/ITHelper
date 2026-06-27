<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ExternalDatabaseManager;
use Throwable;

class RawatJalanController extends Controller
{
    public function registrasi(Request $request, ExternalDatabaseManager $databaseManager)
    {
        return $this->renderView($request, $databaseManager, 'registrasi');
    }

    public function igd(Request $request, ExternalDatabaseManager $databaseManager)
    {
        return $this->renderView($request, $databaseManager, 'igd');
    }

    private function renderView(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');
        
        $registrations = $this->emptyPaginator($request);
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $query = $connection->table('reg_periksa')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('pegawai', 'pegawai.nik', '=', 'reg_periksa.kd_dokter')
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                ->where('reg_periksa.status_lanjut', 'Ralan')
                ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to]);

            if ($type === 'igd') {
                $query->whereIn('reg_periksa.kd_poli', ['UGD', 'IGD']);
            } else {
                $query->whereNotIn('reg_periksa.kd_poli', ['UGD', 'IGD']);
            }

            if ($search !== '') {
                $like = "%{$search}%";
                $query->where(function (Builder $q) use ($like) {
                    $q->where('reg_periksa.no_rawat', 'like', $like)
                        ->orWhere('reg_periksa.no_reg', 'like', $like)
                        ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                        ->orWhere('pasien.nm_pasien', 'like', $like)
                        ->orWhere('pegawai.nama', 'like', $like)
                        ->orWhere('poliklinik.nm_poli', 'like', $like)
                        ->orWhere('penjab.png_jawab', 'like', $like)
                        ->orWhere('reg_periksa.stts', 'like', $like);
                });
            }

            $registrations = $query->select([
                'reg_periksa.no_reg',
                'reg_periksa.no_rawat',
                'reg_periksa.tgl_registrasi',
                'reg_periksa.jam_reg',
                'reg_periksa.no_rkm_medis',
                'pasien.nm_pasien',
                'pasien.jk',
                'reg_periksa.umurdaftar',
                'reg_periksa.sttsumur',
                'poliklinik.nm_poli',
                'pegawai.nama as nama_dokter',
                'penjab.png_jawab',
                'reg_periksa.p_jawab',
                'reg_periksa.almt_pj',
                'reg_periksa.hubunganpj',
                'reg_periksa.biaya_reg',
                'reg_periksa.stts',
                'reg_periksa.stts_daftar',
                'reg_periksa.status_bayar',
                'pasien.no_tlp',
            ])
            ->orderByDesc('reg_periksa.tgl_registrasi')
            ->orderByDesc('reg_periksa.jam_reg')
            ->paginate(20)
            ->withQueryString();

        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Database Eksternal.';
        }

        $title = $type === 'igd' ? 'Registrasi IGD' : 'Registrasi Rawat Jalan';

        return view('pages.rawat-jalan.index', [
            'title' => $title,
            'type' => $type,
            'registrations' => $registrations,
            'connectionError' => $connectionError,
            'filters' => compact('from', 'to', 'search'),
        ]);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
