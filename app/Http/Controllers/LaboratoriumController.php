<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ExternalDatabaseManager;
use Throwable;

class LaboratoriumController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $definition = $this->definition($type);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'in:all,ralan,ranap'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $rows = $this->emptyPaginator($request);
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            if ($type === 'permintaan') {
                $rows = $this->getPermintaanQuery($connection, $from, $to, $status, $search);
            } elseif ($type === 'periksa') {
                $rows = $this->getPeriksaQuery($connection, $from, $to, $search);
            } else {
                $rows = $this->getSimrsQuery($connection, $from, $to, $search);
            }
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Bridging.';
        }

        $externalSetting = \App\Models\ExternalDatabaseSetting::query()->where('is_active', true)->latest()->first();
        $lisUrl = $externalSetting?->lis_url ?: 'http://localhost/lis';

        return view('pages.laboratorium.index', [
            'title' => $definition['label'],
            'type' => $type,
            'definition' => $definition,
            'rows' => $rows,
            'connectionError' => $connectionError,
            'filters' => compact('from', 'to', 'status', 'search'),
            'lisUrl' => rtrim($lisUrl, '/'),
        ]);
    }

    private function getPermintaanQuery($connection, $from, $to, $status, $search)
    {
        $query = $connection->table('permintaan_lab')
            ->join('reg_periksa', 'permintaan_lab.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('dokter', 'permintaan_lab.dokter_perujuk', '=', 'dokter.kd_dokter')
            ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
            ->whereBetween('permintaan_lab.tgl_permintaan', [$from, $to]);

        if ($status === 'ralan') {
            $query->where('permintaan_lab.status', 'ralan')
                  ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli');
        } elseif ($status === 'ranap') {
            $query->where('permintaan_lab.status', 'ranap')
                  ->leftJoin('kamar_inap', function($join) {
                      $join->on('reg_periksa.no_rawat', '=', 'kamar_inap.no_rawat')
                           ->whereRaw("kamar_inap.tgl_masuk = (select max(tgl_masuk) from kamar_inap where kamar_inap.no_rawat = reg_periksa.no_rawat)");
                  })
                  ->leftJoin('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                  ->leftJoin('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal');
        } else {
            $query->leftJoin('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                  ->leftJoin('kamar_inap', function($join) {
                      $join->on('reg_periksa.no_rawat', '=', 'kamar_inap.no_rawat')
                           ->whereRaw("kamar_inap.tgl_masuk = (select max(tgl_masuk) from kamar_inap where kamar_inap.no_rawat = reg_periksa.no_rawat)");
                  })
                  ->leftJoin('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                  ->leftJoin('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal');
        }

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function (Builder $q) use ($like) {
                $q->where('permintaan_lab.noorder', 'like', $like)
                  ->orWhere('permintaan_lab.no_rawat', 'like', $like)
                  ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                  ->orWhere('pasien.nm_pasien', 'like', $like)
                  ->orWhere('dokter.nm_dokter', 'like', $like)
                  ->orWhere('penjab.png_jawab', 'like', $like)
                  ->orWhere('permintaan_lab.diagnosa_klinis', 'like', $like);
            });
        }

        $selectFields = [
            'permintaan_lab.noorder',
            'permintaan_lab.no_rawat',
            'reg_periksa.no_rkm_medis',
            'pasien.nm_pasien',
            'pasien.jk',
            'permintaan_lab.tgl_permintaan',
            'permintaan_lab.jam_permintaan',
            'permintaan_lab.tgl_sampel',
            'permintaan_lab.jam_sampel',
            'permintaan_lab.tgl_hasil',
            'permintaan_lab.jam_hasil',
            'permintaan_lab.dokter_perujuk',
            'dokter.nm_dokter as dokter_rujuk_name',
            'penjab.png_jawab',
            'permintaan_lab.informasi_tambahan',
            'permintaan_lab.diagnosa_klinis',
            'permintaan_lab.status as ralan_or_ranap',
        ];

        if ($status === 'ralan') {
            $selectFields[] = 'poliklinik.nm_poli as lokasi';
        } elseif ($status === 'ranap') {
            $selectFields[] = $connection->raw("concat(ifnull(kamar_inap.kd_kamar,''), ' ', ifnull(bangsal.nm_bangsal, 'Ranap Gabung')) as lokasi");
        } else {
            $selectFields[] = $connection->raw("if(permintaan_lab.status='ralan', poliklinik.nm_poli, concat(ifnull(kamar_inap.kd_kamar,''), ' ', ifnull(bangsal.nm_bangsal, 'Ranap Gabung'))) as lokasi");
        }

        return $query->select($selectFields)
            ->orderByDesc('permintaan_lab.tgl_permintaan')
            ->orderByDesc('permintaan_lab.jam_permintaan')
            ->paginate(20)
            ->withQueryString();
    }

    private function getPeriksaQuery($connection, $from, $to, $search)
    {
        $query = $connection->table('periksa_lab')
            ->join('reg_periksa', 'periksa_lab.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
            ->join('petugas', 'periksa_lab.nip', '=', 'petugas.nip')
            ->join('dokter', 'periksa_lab.kd_dokter', '=', 'dokter.kd_dokter')
            ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
            ->where('periksa_lab.kategori', 'PK')
            ->whereBetween('periksa_lab.tgl_periksa', [$from, $to]);

        $query->leftJoin('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
              ->leftJoin('kamar_inap', function($join) {
                  $join->on('reg_periksa.no_rawat', '=', 'kamar_inap.no_rawat')
                       ->whereRaw("kamar_inap.tgl_masuk = (select max(tgl_masuk) from kamar_inap where kamar_inap.no_rawat = reg_periksa.no_rawat)");
              })
              ->leftJoin('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
              ->leftJoin('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal');

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function (Builder $q) use ($like) {
                $q->where('periksa_lab.no_rawat', 'like', $like)
                  ->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                  ->orWhere('pasien.nm_pasien', 'like', $like)
                  ->orWhere('petugas.nama', 'like', $like)
                  ->orWhere('dokter.nm_dokter', 'like', $like)
                  ->orWhere('penjab.png_jawab', 'like', $like);
            });
        }

        return $query->select([
                'periksa_lab.no_rawat',
                'reg_periksa.no_rkm_medis',
                'pasien.nm_pasien',
                'pasien.jk',
                'petugas.nama as nama_petugas',
                'periksa_lab.tgl_periksa',
                'periksa_lab.jam',
                'periksa_lab.dokter_perujuk',
                'periksa_lab.kd_dokter',
                'dokter.nm_dokter as nama_dokter',
                'penjab.png_jawab',
                'periksa_lab.status as ralan_or_ranap',
                $connection->raw("if(periksa_lab.status='Ralan', poliklinik.nm_poli, concat(ifnull(kamar_inap.kd_kamar,''), ' ', ifnull(bangsal.nm_bangsal, 'Ranap Gabung'))) as lokasi"),
                $connection->raw("sum(periksa_lab.biaya) as total_biaya_header")
            ])
            ->groupBy([
                'periksa_lab.no_rawat',
                'periksa_lab.tgl_periksa',
                'periksa_lab.jam',
                'reg_periksa.no_rkm_medis',
                'pasien.nm_pasien',
                'pasien.jk',
                'petugas.nama',
                'periksa_lab.dokter_perujuk',
                'periksa_lab.kd_dokter',
                'dokter.nm_dokter',
                'penjab.png_jawab',
                'periksa_lab.status',
                'poliklinik.nm_poli',
                'kamar_inap.kd_kamar',
                'bangsal.nm_bangsal',
            ])
            ->orderByDesc('periksa_lab.tgl_periksa')
            ->orderByDesc('periksa_lab.jam')
            ->paginate(20)
            ->withQueryString();
    }

    public function permintaanDetails(Request $request, string $noorder, ExternalDatabaseManager $databaseManager)
    {
        try {
            $connection = $databaseManager->connection();
            $categories = $connection->table('permintaan_pemeriksaan_lab')
                ->join('jns_perawatan_lab', 'permintaan_pemeriksaan_lab.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                ->where('permintaan_pemeriksaan_lab.noorder', $noorder)
                ->select([
                    'permintaan_pemeriksaan_lab.kd_jenis_prw',
                    'jns_perawatan_lab.nm_perawatan'
                ])
                ->orderBy('jns_perawatan_lab.kd_jenis_prw')
                ->get();

            $result = [];
            foreach ($categories as $cat) {
                $details = $connection->table('permintaan_detail_permintaan_lab')
                    ->join('template_laboratorium', 'permintaan_detail_permintaan_lab.id_template', '=', 'template_laboratorium.id_template')
                    ->where('permintaan_detail_permintaan_lab.noorder', $noorder)
                    ->where('permintaan_detail_permintaan_lab.kd_jenis_prw', $cat->kd_jenis_prw)
                    ->select([
                        'template_laboratorium.Pemeriksaan',
                        'template_laboratorium.satuan',
                        'template_laboratorium.nilai_rujukan_ld',
                        'template_laboratorium.nilai_rujukan_la',
                        'template_laboratorium.nilai_rujukan_pd',
                        'template_laboratorium.nilai_rujukan_pa',
                    ])
                    ->orderBy('template_laboratorium.urut')
                    ->get();

                $result[] = [
                    'kd_jenis_prw' => $cat->kd_jenis_prw,
                    'nm_perawatan' => $cat->nm_perawatan,
                    'details' => $details
                ];
            }

            return response()->json($result);

        } catch (Throwable $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function periksaDetails(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'no_rawat' => ['required', 'string'],
            'tgl_periksa' => ['required', 'date'],
            'jam' => ['required', 'string'],
        ]);

        try {
            $connection = $databaseManager->connection();

            $categories = $connection->table('periksa_lab')
                ->join('jns_perawatan_lab', 'periksa_lab.kd_jenis_prw', '=', 'jns_perawatan_lab.kd_jenis_prw')
                ->where('periksa_lab.no_rawat', $filters['no_rawat'])
                ->where('periksa_lab.tgl_periksa', $filters['tgl_periksa'])
                ->where('periksa_lab.jam', $filters['jam'])
                ->where('periksa_lab.kategori', 'PK')
                ->select([
                    'periksa_lab.kd_jenis_prw',
                    'jns_perawatan_lab.nm_perawatan',
                    'periksa_lab.biaya'
                ])
                ->orderBy('jns_perawatan_lab.kd_jenis_prw')
                ->get();

            $result = [];
            foreach ($categories as $cat) {
                $details = $connection->table('detail_periksa_lab')
                    ->join('template_laboratorium', 'detail_periksa_lab.id_template', '=', 'template_laboratorium.id_template')
                    ->where('detail_periksa_lab.no_rawat', $filters['no_rawat'])
                    ->where('detail_periksa_lab.kd_jenis_prw', $cat->kd_jenis_prw)
                    ->where('detail_periksa_lab.tgl_periksa', $filters['tgl_periksa'])
                    ->where('detail_periksa_lab.jam', $filters['jam'])
                    ->select([
                        'template_laboratorium.Pemeriksaan',
                        'detail_periksa_lab.nilai',
                        'template_laboratorium.satuan',
                        'detail_periksa_lab.nilai_rujukan',
                        'detail_periksa_lab.keterangan',
                        'detail_periksa_lab.biaya_item'
                    ])
                    ->orderBy('template_laboratorium.urut')
                    ->get();

                $result[] = [
                    'kd_jenis_prw' => $cat->kd_jenis_prw,
                    'nm_perawatan' => $cat->nm_perawatan,
                    'biaya' => $cat->biaya,
                    'details' => $details
                ];
            }

            $notes = $connection->table('saran_kesan_lab')
                ->where('no_rawat', $filters['no_rawat'])
                ->where('tgl_periksa', $filters['tgl_periksa'])
                ->where('jam', $filters['jam'])
                ->select(['saran', 'kesan'])
                ->first();

            return response()->json([
                'categories' => $result,
                'notes' => $notes ?: ['saran' => '', 'kesan' => '']
            ]);

        } catch (Throwable $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        }
    }

    public function updateSampel(Request $request, string $noorder, ExternalDatabaseManager $databaseManager)
    {
        $data = $request->validate([
            'tgl_sampel' => ['required', 'date'],
            'jam_sampel' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        try {
            $connection = $databaseManager->connection();
            $connection->table('permintaan_lab')
                ->where('noorder', $noorder)
                ->update([
                    'tgl_sampel' => $data['tgl_sampel'],
                    'jam_sampel' => strlen($data['jam_sampel']) === 5 ? $data['jam_sampel'] . ':00' : $data['jam_sampel'],
                ]);

            return redirect()->route('lis.periksa.tolis', ['noorder' => $noorder])
                ->with('success', 'Waktu pengambilan sampel berhasil disimpan dan disinkronkan ke LIS.');
        } catch (Throwable $exception) {
            return back()->withErrors(['update' => 'Gagal memperbarui sampel: ' . $exception->getMessage()]);
        }
    }

    public function updateHasil(Request $request, string $noorder, ExternalDatabaseManager $databaseManager)
    {
        $data = $request->validate([
            'tgl_hasil' => ['required', 'date'],
            'jam_hasil' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        try {
            $connection = $databaseManager->connection();
            $connection->table('permintaan_lab')
                ->where('noorder', $noorder)
                ->update([
                    'tgl_hasil' => $data['tgl_hasil'],
                    'jam_hasil' => strlen($data['jam_hasil']) === 5 ? $data['jam_hasil'] . ':00' : $data['jam_hasil'],
                ]);

            return back()->with('success', 'Waktu penyerahan hasil berhasil disimpan.');
        } catch (Throwable $exception) {
            return back()->withErrors(['update' => 'Gagal memperbarui hasil: ' . $exception->getMessage()]);
        }
    }

    private function getSimrsQuery($connection, $from, $to, $search)
    {
        $databaseManager = app(ExternalDatabaseManager::class);
        $setting = $databaseManager->setting();
        
        if ($setting && !empty($setting->lis_antara_db_host)) {
            try {
                $antaraConn = $databaseManager->antaraConnection();
                $table = 'permintaan_lab';
            } catch (Throwable $e) {
                $antaraConn = $connection;
                $table = 'sik_bridging_lab.permintaan_lab';
            }
        } else {
            $antaraConn = $connection;
            $table = 'sik_bridging_lab.permintaan_lab';
        }

        $query = $antaraConn->table($table)
            ->whereBetween('tgl_permintaan', [$from, $to]);

        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function (Builder $q) use ($like) {
                $q->where('noorder', 'like', $like)
                  ->orWhere('no_rawat', 'like', $like)
                  ->orWhere('no_rkm_medis', 'like', $like)
                  ->orWhere('nm_pasien', 'like', $like)
                  ->orWhere('dokter_perujuk', 'like', $like);
            });
        }

        return $query->select([
                'noorder',
                'tgl_permintaan',
                'jam_permintaan',
                'no_rkm_medis',
                'nm_pasien',
                'jk',
                'dokter_perujuk',
                'no_rawat'
            ])
            ->orderByDesc('noorder')
            ->paginate(20)
            ->withQueryString();
    }

    private function definition(string $type): array
    {
        $definitions = [
            'permintaan' => ['label' => 'Permintaan Laboratorium'],
            'periksa' => ['label' => 'Pemeriksaan Laboratorium'],
            'simrs' => ['label' => 'Periksa SIMRS'],
        ];
        abort_unless(isset($definitions[$type]), 404);

        return $definitions[$type];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
