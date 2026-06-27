<?php

namespace App\Http\Controllers\Lis;

use App\Http\Controllers\Controller;
use App\Models\Lis\Pasien;
use App\Models\Lis\Dokter;
use App\Models\Lis\Ruang;
use App\Models\Lis\Periksa;
use App\Models\Lis\Result;
use App\Models\Lis\Kode;
use App\Models\Lis\KodeSimrs;
use App\Models\Lis\PeriksaSimrsDetail;
use App\Models\Lis\Tarif;
use App\Models\Lis\PeriksaBiaya;
use App\Models\Lis\Petugas;
use App\Models\Lis\Paket;
use App\Models\Lis\PaketDetail;
use App\Models\Lis\Formula;
use App\Models\Lis\Parameter;
use App\Services\ExternalDatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LisPeriksaController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'in:all,pending,hasil,biaya'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $status = $filters['status'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $extConn = null;
        $connectionError = null;
        try {
            $extConn = $databaseManager->connection();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal SIMRS belum dapat diakses. Detail pasien eksternal tidak dapat ditampilkan.';
        }

        // 1. Get query builder for LIS periksa
        $query = Periksa::query()->whereBetween('tanggal', [$from . ' 00:00:00', $to . ' 23:59:59']);

        // Filter status
        if ($status === 'pending') {
            $query->where('state', 0);
        } elseif ($status === 'hasil') {
            $query->where('state', 1)->where('validasi', 1);
        }

        // Filter search
        if ($search !== '') {
            $matchingRms = [];
            if ($extConn) {
                try {
                    $matchingRms = $extConn->table('pasien')
                        ->where('no_rkm_medis', 'like', "%{$search}%")
                        ->orWhere('nm_pasien', 'like', "%{$search}%")
                        ->pluck('no_rkm_medis')
                        ->toArray();
                } catch (Throwable $e) {}
            }

            $query->where(function ($q) use ($matchingRms, $search) {
                if (count($matchingRms) > 0) {
                    $localPasienIds = Pasien::whereIn('no_rm', $matchingRms)->pluck('id')->toArray();
                    $q->whereIn('id_pasien', $localPasienIds);
                }
                $q->orWhere('nomor', 'like', "%{$search}%")
                  ->orWhere('no_reg', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('tanggal')->paginate(20)->withQueryString();

        // 2. Fetch external details from SIMRS for the current page rows
        if ($extConn && $rows->count() > 0) {
            $pasienRms = $rows->map(fn($r) => $r->pasien->no_rm ?? null)->filter()->unique()->toArray();
            $dokterIds = $rows->map(fn($r) => $r->dokter->id_dokter ?? null)->filter()->unique()->toArray();
            $ruangIds = $rows->map(fn($r) => $r->ruang->poli_id ?? null)->filter()->unique()->toArray();
            $noRawats = $rows->map(fn($r) => $r->no_reg)->filter()->unique()->toArray();

            try {
                $extPasiens = $extConn->table('pasien')
                    ->whereIn('no_rkm_medis', $pasienRms)
                    ->get()
                    ->keyBy('no_rkm_medis');

                $extDokters = $extConn->table('dokter')
                    ->whereIn('kd_dokter', $dokterIds)
                    ->get()
                    ->keyBy('kd_dokter');

                $extRuangs = $extConn->table('poliklinik')
                    ->whereIn('kd_poli', $ruangIds)
                    ->get()
                    ->keyBy('kd_poli');

                $extRegs = $extConn->table('reg_periksa as r')
                    ->join('penjab as pen', 'r.kd_pj', '=', 'pen.kd_pj')
                    ->whereIn('r.no_rawat', $noRawats)
                    ->select('r.no_rawat', 'r.kd_pj', 'pen.png_jawab')
                    ->get()
                    ->keyBy('no_rawat');

                foreach ($rows as $row) {
                    $no_rm = $row->pasien->no_rm ?? '';
                    $row->ext_pasien = $extPasiens->get($no_rm);
                    
                    $kd_dokter = $row->dokter->id_dokter ?? '';
                    $row->ext_dokter = $extDokters->get($kd_dokter);

                    $kd_poli = $row->ruang->poli_id ?? '';
                    $row->ext_ruang = $extRuangs->get($kd_poli);

                    $row->ext_reg = $extRegs->get($row->no_reg);
                }
            } catch (Throwable $e) {}
        }

        $allDokters = Dokter::orderBy('nama')->get();
        $allRuangs = Ruang::orderBy('nama')->get();
        $allPetugas = Petugas::orderBy('nama')->get();

        return view('pages.lis.periksa.index', [
            'title' => 'Pemeriksaan LIS',
            'rows' => $rows,
            'filters' => compact('from', 'to', 'status', 'search'),
            'connectionError' => $connectionError,
            'allDokters' => $allDokters,
            'allRuangs' => $allRuangs,
            'allPetugas' => $allPetugas,
        ]);
    }

    public function simrs(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $search = trim($filters['search'] ?? '');

        $rows = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            
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
                $query->where(function ($q) use ($like) {
                    $q->where('noorder', 'like', $like)
                      ->orWhere('no_rawat', 'like', $like)
                      ->orWhere('no_rkm_medis', 'like', $like)
                      ->orWhere('nm_pasien', 'like', $like)
                      ->orWhere('dokter_perujuk', 'like', $like);
                });
            }

            $rows = $query->select([
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

        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal SIMRS belum dapat diakses. Periksa konfigurasi bridging.';
        }

        return view('pages.lis.periksa.simrs', [
            'title' => 'Pemeriksaan SIMRS',
            'rows' => $rows,
            'connectionError' => $connectionError,
            'filters' => compact('from', 'to', 'search'),
        ]);
    }

    public function show($id, ExternalDatabaseManager $databaseManager)
    {
        $periksa = Periksa::findOrFail($id);

        $extConn = null;
        $connectionError = null;
        try {
            $extConn = $databaseManager->connection();
        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal SIMRS belum dapat diakses.';
        }

        // Fetch external data for patient, doctor, and room
        $extPasien = null;
        $extDokter = null;
        $extDokter2 = null;
        $extRuang = null;
        $extReg = null;

        if ($extConn) {
            try {
                $extPasien = $extConn->table('pasien')
                    ->where('no_rkm_medis', $periksa->pasien->no_rm ?? '')
                    ->first();

                $extDokter = $extConn->table('dokter')
                    ->where('kd_dokter', $periksa->dokter->id_dokter ?? '')
                    ->first();

                $extDokter2 = $extConn->table('dokter')
                    ->where('kd_dokter', $periksa->dokter2->id_dokter ?? '')
                    ->first();

                $extRuang = $extConn->table('poliklinik')
                    ->where('kd_poli', $periksa->ruang->poli_id ?? '')
                    ->first();

                if ($periksa->no_reg) {
                    $extReg = $extConn->table('reg_periksa as r')
                        ->join('penjab as pen', 'r.kd_pj', '=', 'pen.kd_pj')
                        ->where('r.no_rawat', $periksa->no_reg)
                        ->select('r.kd_pj', 'pen.png_jawab')
                        ->first();
                }
            } catch (Throwable $e) {}
        }

        // Fetch exam detail parameters from LIS DB
        $detail = DB::connection('lis')
            ->table('result as a')
            ->leftJoin('kode as b', 'a.KodeParamater', '=', 'b.lis')
            ->where('a.KodePatient', $periksa->nomor)
            ->select([
                'b.nama',
                'a.Nilai',
                'b.satuan',
                'b.metoda',
                'a.KodeParamater',
                'a.KodeAlat',
                'a.acc',
                'a.id',
                'b.parameter',
                'b.pembulatan',
                'a.NR',
                'a.tanda',
                'a.keterangan'
            ])
            ->get();

        // References for selection modals
        $modelKode = Kode::orderBy('grup1')->get();
        $modelPaket = Paket::all();
        $modelFormula = Formula::all();

        // Analyzer raw results for linkage option
        $kemarin = now()->subDay()->toDateTimeString();
        $sekarang = now()->toDateTimeString();
        $modelResult = Result::whereIn('KodeAlat', ['A 15'])
            ->whereBetween('tanggal', [$kemarin, $sekarang])
            ->whereNull('sampel')
            ->where('KodePatient', '!=', '')
            ->select('KodePatient', 'KodeAlat', DB::raw("date_format(tanggal, '%d-%m-%Y %H:%i:%s') as tgl"))
            ->groupBy(['KodePatient', 'KodeAlat', 'tanggal'])
            ->get();

        // Dropdown data: sync active from SIMRS (status = 1) if connected, otherwise fallback to local LIS
        $allDokters = collect();
        $allRuangs = collect();
        $allPetugas = collect();

        if ($extConn) {
            try {
                // 1. Sync & Get active Doctors from SIMRS
                $extDokters = $extConn->table('dokter')
                    ->where('status', '1')
                    ->select(['kd_dokter', 'nm_dokter'])
                    ->orderBy('nm_dokter')
                    ->get();

                foreach ($extDokters as $ed) {
                    Dokter::firstOrCreate(
                        ['id_dokter' => $ed->kd_dokter],
                        ['nama' => $ed->nm_dokter, 'kode' => 1]
                    );
                }
                
                $activeDocIds = $extDokters->pluck('kd_dokter')->toArray();
                $currentDocCodes = [];
                if ($periksa->dokter && !empty($periksa->dokter->id_dokter)) {
                    $currentDocCodes[] = $periksa->dokter->id_dokter;
                }
                if ($periksa->dokter2 && !empty($periksa->dokter2->id_dokter)) {
                    $currentDocCodes[] = $periksa->dokter2->id_dokter;
                }
                $docCodesToQuery = array_unique(array_merge($activeDocIds, $currentDocCodes));

                $allDokters = Dokter::whereIn('id_dokter', $docCodesToQuery)
                    ->orWhereIn('id', array_filter([$periksa->id_dokter, $periksa->id_dokter2]))
                    ->orderBy('nama')
                    ->get()
                    ->unique('nama')
                    ->values();

                // 2. Sync & Get active Ruangs from SIMRS
                $extRuangs = $extConn->table('poliklinik')
                    ->where('status', '1')
                    ->select(['kd_poli', 'nm_poli'])
                    ->orderBy('nm_poli')
                    ->get();

                foreach ($extRuangs as $er) {
                    Ruang::firstOrCreate(
                        ['poli_id' => $er->kd_poli],
                        ['nama' => $er->nm_poli]
                    );
                }
                
                $activePoliIds = $extRuangs->pluck('kd_poli')->toArray();
                if ($periksa->ruang && !empty($periksa->ruang->poli_id)) {
                    $activePoliIds[] = $periksa->ruang->poli_id;
                }
                $allRuangs = Ruang::whereIn('poli_id', $activePoliIds)
                    ->orWhere('id', $periksa->id_ruang)
                    ->orderBy('nama')
                    ->get()
                    ->unique('nama')
                    ->values();

                // 3. Sync & Get active Petugas from SIMRS
                $extPetugas = $extConn->table('petugas')
                    ->where('status', '1')
                    ->where('kd_jbtn', 'J014')
                    ->select(['nip', 'nama'])
                    ->orderBy('nama')
                    ->get();

                foreach ($extPetugas as $ep) {
                    Petugas::firstOrCreate(
                        ['id_petugas' => $ep->nip],
                        ['nama' => $ep->nama, 'is_aktif' => 1]
                    );
                }
                
                $activeNips = $extPetugas->pluck('nip')->toArray();
                if ($periksa->petugas && !empty($periksa->petugas->id_petugas)) {
                    $activeNips[] = $periksa->petugas->id_petugas;
                }
                $allPetugas = Petugas::whereIn('id_petugas', $activeNips)
                    ->orWhere('id', $periksa->id_petugas)
                    ->orderBy('nama')
                    ->get()
                    ->unique('nama')
                    ->values();

            } catch (Throwable $e) {
                // Fallback to local LIS if sync fails
                $allDokters = Dokter::orderBy('nama')->get()->unique('nama')->values();
                $allRuangs = Ruang::orderBy('nama')->get()->unique('nama')->values();
                $allPetugas = Petugas::orderBy('nama')->get()->unique('nama')->values();
            }
        } else {
            // Fallback to local LIS if no external connection
            $allDokters = Dokter::orderBy('nama')->get()->unique('nama')->values();
            $allRuangs = Ruang::orderBy('nama')->get()->unique('nama')->values();
            $allPetugas = Petugas::orderBy('nama')->get()->unique('nama')->values();
        }

        // Biaya and Tarif data for selected treatments table
        $modelBiaya = PeriksaBiaya::where('periksa_id', $id)->get();
        $modelTarif = Tarif::orderBy('pemeriksaan')->get();

        $setting = $databaseManager->setting();
        $lisUrl = ($setting && !empty($setting->lis_url)) ? rtrim($setting->lis_url, '/') : 'http://localhost/lis';
        if (str_contains($lisUrl, 'localhost') && !str_contains($lisUrl, '/lis')) {
            $lisUrl .= '/lis';
        }

        return view('pages.lis.periksa.show', [
            'title' => 'Detail Pemeriksaan LIS',
            'model' => $periksa,
            'extPasien' => $extPasien,
            'extDokter' => $extDokter,
            'extDokter2' => $extDokter2,
            'extRuang' => $extRuang,
            'extReg' => $extReg,
            'detail' => $detail,
            'modelKode' => $modelKode,
            'modelPaket' => $modelPaket,
            'modelFormula' => $modelFormula,
            'modelResult' => $modelResult,
            'allDokters' => $allDokters,
            'allRuangs' => $allRuangs,
            'allPetugas' => $allPetugas,
            'modelBiaya' => $modelBiaya,
            'modelTarif' => $modelTarif,
            'connectionError' => $connectionError,
            'lisUrl' => $lisUrl,
        ]);
    }

    public function tolis($noorder, ExternalDatabaseManager $databaseManager)
    {
        $setting = $databaseManager->setting();
        try {
            if ($setting && !empty($setting->lis_antara_db_host)) {
                $extConn = $databaseManager->antaraConnection();
                $orderTable = 'permintaan_lab';
                $detailTable = 'detail_permintaan_lab';
            } else {
                $extConn = $databaseManager->connection();
                $orderTable = 'sik_bridging_lab.permintaan_lab';
                $detailTable = 'sik_bridging_lab.detail_permintaan_lab';
            }
        } catch (Throwable $exception) {
            return back()->withErrors(['error' => 'Koneksi database SIMRS eksternal/antara tidak aktif. Gagal memproses order.']);
        }

        // Fetch SIMRS order details
        $order = $extConn->table($orderTable)
            ->where('noorder', $noorder)
            ->first();

        $details = collect();

        if (!$order) {
            // Try to query the main SIMRS tables as a fallback
            try {
                $mainConn = $databaseManager->connection();
                $mainOrder = $mainConn->table('permintaan_lab as p')
                    ->join('reg_periksa as r', 'p.no_rawat', '=', 'r.no_rawat')
                    ->join('pasien as pas', 'r.no_rkm_medis', '=', 'pas.no_rkm_medis')
                    ->join('dokter as d', 'p.dokter_perujuk', '=', 'd.kd_dokter')
                    ->leftJoin('poliklinik as poli', 'r.kd_poli', '=', 'poli.kd_poli')
                    ->where('p.noorder', $noorder)
                    ->select([
                        'p.noorder',
                        'p.no_rawat',
                        'r.no_rkm_medis',
                        'pas.nm_pasien',
                        'pas.tmp_lahir',
                        'pas.tgl_lahir',
                        'pas.jk',
                        'pas.alamat',
                        'p.dokter_perujuk as kode_dokter_perujuk',
                        'd.nm_dokter as dokter_perujuk',
                        'r.kd_poli as kode_ruang',
                        'poli.nm_poli as nama_ruang',
                        'p.diagnosa_klinis',
                        'p.tgl_permintaan',
                        'p.jam_permintaan'
                    ])
                    ->first();

                if ($mainOrder) {
                    // Insert to bridging table if writable
                    try {
                        $extConn->table($orderTable)->insert([
                            'noorder' => $mainOrder->noorder,
                            'no_rawat' => $mainOrder->no_rawat,
                            'no_rkm_medis' => $mainOrder->no_rkm_medis,
                            'nm_pasien' => $mainOrder->nm_pasien,
                            'jk' => $mainOrder->jk,
                            'tgl_lahir' => $mainOrder->tgl_lahir,
                            'tmp_lahir' => $mainOrder->tmp_lahir ?? '',
                            'alamat' => $mainOrder->alamat ?? '',
                            'dokter_perujuk' => $mainOrder->dokter_perujuk,
                            'kode_dokter_perujuk' => $mainOrder->kode_dokter_perujuk,
                            'kode_ruang' => $mainOrder->kode_ruang ?? '',
                            'nama_ruang' => $mainOrder->nama_ruang ?? '',
                            'tgl_permintaan' => $mainOrder->tgl_permintaan,
                            'jam_permintaan' => $mainOrder->jam_permintaan,
                            'diagnosa_klinis' => $mainOrder->diagnosa_klinis ?? ''
                        ]);
                    } catch (Throwable $insertEx) {}

                    // Load details from main SIMRS detail table
                    $mainDetails = $mainConn->table('permintaan_detail_permintaan_lab as dp')
                        ->where('dp.noorder', $noorder)
                        ->select('dp.noorder', 'dp.kd_jenis_prw', 'dp.id_template')
                        ->get();

                    foreach ($mainDetails as $md) {
                        try {
                            $extConn->table($detailTable)->insert([
                                'noorder' => $md->noorder,
                                'kd_jenis_prw' => $md->kd_jenis_prw,
                                'id_template' => $md->id_template,
                                'nilai_rujukan' => ''
                            ]);
                        } catch (Throwable $insertEx) {}
                    }

                    $order = $mainOrder;
                    $details = $mainDetails;
                }
            } catch (Throwable $e) {}
        } else {
            $details = $extConn->table($detailTable)
                ->where('noorder', $noorder)
                ->get();
        }

        // Fallback: If bridging details are empty but order exists, pull from main SIMRS details
        if ($details->isEmpty()) {
            try {
                $mainConn = $databaseManager->connection();
                $mainDetails = $mainConn->table('permintaan_detail_permintaan_lab as dp')
                    ->where('dp.noorder', $noorder)
                    ->select('dp.noorder', 'dp.kd_jenis_prw', 'dp.id_template')
                    ->get();
                if ($mainDetails->isNotEmpty()) {
                    foreach ($mainDetails as $md) {
                        try {
                            $extConn->table($detailTable)->insert([
                                'noorder' => $md->noorder,
                                'kd_jenis_prw' => $md->kd_jenis_prw,
                                'id_template' => $md->id_template,
                                'nilai_rujukan' => ''
                            ]);
                        } catch (Throwable $insertEx) {}
                    }
                    $details = $mainDetails;
                }
            } catch (Throwable $e) {}
        }

        if (!$order) {
            return back()->withErrors(['error' => "Order dengan nomor order {$noorder} tidak ditemukan di SIMRS."]);
        }

        // Insert/sync local LIS records
        // 1. Patient
        $pasien = Pasien::firstOrCreate(
            ['no_rm' => $order->no_rkm_medis],
            [
                'nama' => $order->nm_pasien,
                'tempat_lahir' => $order->tmp_lahir ?? '',
                'tgl_lahir' => date('d-m-Y', strtotime($order->tgl_lahir)),
                'gender' => ($order->jk === 'L') ? 1 : 2,
                'alamat' => $order->alamat ?? '',
                'state' => 1,
                'id_instansi' => 0
            ]
        );

        // 2. Doctor
        $dokter = Dokter::firstOrCreate(
            ['id_dokter' => $order->kode_dokter_perujuk],
            [
                'nama' => $order->dokter_perujuk,
                'kode' => 1
            ]
        );

        // 3. Room
        $ruang = Ruang::firstOrCreate(
            ['poli_id' => $order->kode_ruang],
            [
                'nama' => $order->nama_ruang
            ]
        );

        // 4. Periksa
        $periksa = Periksa::where('no_reg', $noorder)->first();

        // Find penjamin from reg_periksa in external DB
        $id_penjamin = 1; // Default UMUM
        if ($extConn && !empty($order->no_rawat)) {
            try {
                $extReg = $extConn->table('reg_periksa as r')
                    ->join('penjab as pen', 'r.kd_pj', '=', 'pen.kd_pj')
                    ->where('r.no_rawat', $order->no_rawat)
                    ->select('pen.png_jawab')
                    ->first();
                if ($extReg) {
                    $name = strtoupper($extReg->png_jawab);
                    if (str_contains($name, 'BPJS') || str_contains($name, 'KIS') || str_contains($name, 'ASKES')) {
                        $id_penjamin = 2; // BPJS
                    } elseif (str_contains($name, 'UMUM') || str_contains($name, 'CASH') || str_contains($name, 'MANDIRI') || str_contains($name, 'KOSONG')) {
                        $id_penjamin = 1; // UMUM
                    } else {
                        $id_penjamin = 3; // ASURANSI
                    }
                }
            } catch (Throwable $e) {}
        }

        if (!$periksa) {
            $seq = Periksa::getSeq();
            $periksa = Periksa::create([
                'tanggal' => now()->toDateTimeString(),
                'seq' => $seq,
                'nomor' => date("ymd") . $seq,
                'no_reg' => $noorder,
                'id_pasien' => $pasien->id,
                'id_dokter' => $dokter->id,
                'id_dokter2' => 1, // Default Penanggung Jawab
                'id_ruang' => $ruang->id,
                'id_petugas' => 1, // Default Petugas
                'id_penjamin' => $id_penjamin, // Set penjamin!
                'id_paket' => 0,
                'unit' => 'Laboratorium',
                'create_by' => auth()->user()->name ?? 'System',
                'create_at' => now()->toDateTimeString(),
                'validasi' => 0,
                'state' => 0,
                'ket_klinik' => $order->diagnosa_klinis ?? ''
            ]);
        } else {
            // Update penjamin if it exists
            $periksa->id_penjamin = $id_penjamin;
            $periksa->save();
        }

        // 5. PeriksaBiaya (Tarif)
        $uniqueGrups = $details->pluck('kd_jenis_prw')->unique()->filter()->toArray();
        foreach ($uniqueGrups as $grup) {
            $tarif = Tarif::find($grup);
            if ($tarif) {
                PeriksaBiaya::firstOrCreate(
                    [
                        'periksa_id' => $periksa->id,
                        'tarif_id' => $grup,
                    ],
                    [
                        'tarif' => $tarif->biaya
                    ]
                );
            }
        }

        // 6. PeriksaSimrsDetail & LIS Result Parameters
        PeriksaSimrsDetail::where('no_lab', $noorder)->delete();
        foreach ($details as $d) {
            $lisCode = KodeSimrs::where('grup', $d->kd_jenis_prw)
                ->where('kode', $d->id_template)
                ->value('lis') ?? '';

            PeriksaSimrsDetail::create([
                'no_lab' => $noorder,
                'grup' => $d->kd_jenis_prw,
                'kode' => $d->id_template,
                'nama' => '',
                'lis' => $lisCode
            ]);

            if (!empty($lisCode)) {
                // Check if result record already exists for this parameter
                $exists = Result::where('KodePatient', $periksa->nomor)
                    ->where('KodeParamater', $lisCode)
                    ->exists();

                if (!$exists) {
                    $defaultNR = Kode::where('lis', $lisCode)->value('parameter') ?? '';
                    Result::create([
                        'KodePatient' => $periksa->nomor,
                        'KodeAlat' => '-',
                        'KodeParamater' => $lisCode,
                        'tanggal' => now()->toDateTimeString(),
                        'acc' => 1,
                        'NR' => $defaultNR
                    ]);
                }
            }
        }

        return redirect()->route('lis.periksa.show', $periksa->id)
            ->with('success', 'Pemeriksaan SIMRS berhasil disimpan ke LIS.');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'field' => ['required', 'string'],
            'value' => ['nullable', 'string'],
        ]);

        $periksa = Periksa::findOrFail($id);
        $field = $data['field'];
        $value = $data['value'];

        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();

        if ($field === 'note') {
            $periksa->note = $value;
        } elseif ($field === 'ket_klinik') {
            $periksa->ket_klinik = $value;
        } elseif ($field === 'id_ruang') {
            $periksa->id_ruang = $value;
        } elseif ($field === 'id_petugas') {
            $periksa->id_petugas = $value;
        } elseif ($field === 'id_dokter') {
            $periksa->id_dokter = $value;
        } elseif ($field === 'id_dokter2') {
            $periksa->id_dokter2 = $value;
        } elseif ($field === 'id_verifikasi') {
            $periksa->id_verifikasi = $value;
        } elseif ($field === 'penjamin') {
            $periksa->id_penjamin = $value;
        } elseif ($field === 'tgl_distribusi') {
            $periksa->tgl_distribusi = $value;
        } elseif ($field === 'id_jenis') {
            $periksa->id_jenis = $value;
        } elseif ($field === 'ket_verifikasi') {
            $periksa->ket_verifikasi = $value;
        }

        $periksa->save();

        return response()->json(['status' => 'success', 'message' => 'Data periksa berhasil diperbarui.']);
    }

    public function selesai(Request $request, $id)
    {
        $periksa = Periksa::findOrFail($id);
        $periksa->state = 1;
        $periksa->validasi = 1;
        $periksa->selesai = now()->toDateTimeString();
        $periksa->tgl_validasi = now()->toDateTimeString();
        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();
        $periksa->save();

        // Sync to SIMRS if this is a bridged order
        $this->syncResultsToSimrs($periksa);

        return back()->with('success', 'Pemeriksaan LIS berhasil divalidasi, diselesaikan, dan disinkronkan ke SIMRS.');
    }

    public function manual(Request $request, $id)
    {
        $data = $request->validate([
            'kode' => ['required', 'string'],
        ]);

        $periksa = Periksa::findOrFail($id);
        $codes = explode(',', $data['kode']);

        foreach ($codes as $c) {
            $lisCode = Kode::where('id', $c)->value('lis') ?? $c;
            Result::create([
                'KodePatient' => $periksa->nomor,
                'KodeAlat' => '-',
                'KodeParamater' => $lisCode,
                'tanggal' => now()->toDateTimeString(),
                'acc' => 1
            ]);
        }

        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();
        $periksa->save();

        return back()->with('success', 'Parameter manual berhasil ditambahkan.');
    }

    public function paket(Request $request, $id)
    {
        $data = $request->validate([
            'kode' => ['required', 'string'],
        ]);

        $periksa = Periksa::findOrFail($id);
        $codes = explode(',', $data['kode']);

        foreach ($codes as $c) {
            $details = PaketDetail::where('id_paket', $c)->get();
            foreach ($details as $d) {
                $lisCode = Kode::where('id', $d->id_kode)->value('lis');
                if ($lisCode) {
                    Result::create([
                        'PatientName' => '-',
                        'KodePatient' => $periksa->nomor,
                        'KodeAlat' => '-',
                        'KodeParamater' => $lisCode,
                        'tanggal' => now()->toDateTimeString(),
                        'acc' => 1
                    ]);
                }
            }
        }

        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();
        $periksa->save();

        return back()->with('success', 'Paket pemeriksaan berhasil ditambahkan.');
    }

    public function formula(Request $request, $id)
    {
        $data = $request->validate([
            'kode' => ['required', 'string'],
        ]);

        $periksa = Periksa::findOrFail($id);
        $codes = explode(',', $data['kode']);

        foreach ($codes as $c) {
            $formula = Formula::find($c);
            if ($formula) {
                $details = Kode::where('formula', $formula->lis)->get();
                foreach ($details as $d) {
                    Result::create([
                        'KodePatient' => $periksa->nomor,
                        'KodeAlat' => '-',
                        'KodeParamater' => $d->lis,
                        'tanggal' => now()->toDateTimeString(),
                        'acc' => 1
                    ]);
                }
            }
        }

        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();
        $periksa->save();

        return back()->with('success', 'Formula pemeriksaan berhasil ditambahkan.');
    }

    public function alat(Request $request, $id)
    {
        $data = $request->validate([
            'nomor' => ['required', 'string'],
            'sampel' => ['required', 'string'],
            'alat' => ['required', 'string'],
        ]);

        $periksa = Periksa::findOrFail($id);
        $kemarin = now()->subDay()->toDateTimeString();
        $sekarang = now()->toDateTimeString();

        $rawResults = Result::where('KodeAlat', $data['alat'])
            ->where('KodePatient', $data['sampel'])
            ->whereBetween('tanggal', [$kemarin, $sekarang])
            ->whereNull('sampel')
            ->get();

        foreach ($rawResults as $res) {
            $res->sampel = $res->KodePatient;
            $res->KodePatient = $periksa->nomor;
            $res->acc = 1;
            $res->save();
        }

        $periksa->state = 1;
        $periksa->validasi = 1;
        $periksa->selesai = now()->toDateTimeString();
        $periksa->tgl_validasi = now()->toDateTimeString();
        $periksa->update_by = auth()->user()->name ?? 'System';
        $periksa->update_at = now()->toDateTimeString();
        $periksa->save();

        return back()->with('success', 'Data alat analyzer berhasil ditarik dan diselesaikan.');
    }

    public function biaya($id)
    {
        $periksa = Periksa::findOrFail($id);
        $modelTarif = Tarif::orderBy('pemeriksaan')->get();
        $modelBiaya = PeriksaBiaya::where('periksa_id', $id)->get();

        return view('pages.lis.periksa.biaya', [
            'title' => 'Entri Biaya Pemeriksaan',
            'model' => $periksa,
            'modelTarif' => $modelTarif,
            'modelBiaya' => $modelBiaya,
        ]);
    }

    public function addBiaya(Request $request, $id)
    {
        $data = $request->validate([
            'kode' => ['required', 'string'],
        ]);

        $codes = explode(',', $data['kode']);
        foreach ($codes as $c) {
            $tarif = Tarif::find($c);
            if ($tarif) {
                PeriksaBiaya::create([
                    'periksa_id' => $id,
                    'tarif_id' => $c,
                    'tarif' => $tarif->biaya
                ]);
            }
        }

        return back()->with('success', 'Biaya berhasil dibebankan.');
    }

    public function deleteBiaya(Request $request, $id)
    {
        $data = $request->validate([
            'id_biaya' => ['required', 'integer'],
        ]);

        PeriksaBiaya::where('periksa_id', $id)->where('id', $data['id_biaya'])->delete();

        return back()->with('success', 'Biaya berhasil dihapus.');
    }

    public function cetak($id, ExternalDatabaseManager $databaseManager)
    {
        $periksa = Periksa::findOrFail($id);
        $periksa->cetak = 1;
        $periksa->save();

        // Log print action
        Log::create([
            'id_user' => auth()->user()->id ?? 1,
            'kode' => $periksa->nomor,
            'keterangan' => 'Cetak Pemeriksaan - ' . ($periksa->petugas->nama ?? 'System'),
            'tanggal' => now()->toDateTimeString(),
            'ip' => request()->ip()
        ]);

        $extConn = null;
        try {
            $extConn = $databaseManager->connection();
        } catch (Throwable $e) {}

        $extPasien = null;
        $extDokter = null;
        $extRuang = null;
        $extReg = null;

        if ($extConn) {
            try {
                $extPasien = $extConn->table('pasien')
                    ->where('no_rkm_medis', $periksa->pasien->no_rm ?? '')
                    ->first();

                $extDokter = $extConn->table('dokter')
                    ->where('kd_dokter', $periksa->dokter->id_dokter ?? '')
                    ->first();

                $extRuang = $extConn->table('poliklinik')
                    ->where('kd_poli', $periksa->ruang->poli_id ?? '')
                    ->first();

                if ($periksa->no_reg) {
                    $extReg = $extConn->table('reg_periksa as r')
                        ->join('penjab as pen', 'r.kd_pj', '=', 'pen.kd_pj')
                        ->where('r.no_rawat', $periksa->no_reg)
                        ->select('r.kd_pj', 'pen.png_jawab')
                        ->first();
                }
            } catch (Throwable $e) {}
        }

        $detail = DB::connection('lis')
            ->table('result as a')
            ->leftJoin('kode as b', 'a.KodeParamater', '=', 'b.lis')
            ->leftJoin('v_grup as c', 'b.grup1', '=', 'c.nama')
            ->where('a.KodePatient', 'like', "%{$periksa->nomor}%")
            ->where('a.acc', '1')
            ->select([
                'b.nama',
                'a.Nilai',
                'a.keterangan',
                'b.satuan',
                'b.metoda',
                'a.KodeParamater',
                'b.parameter',
                'b.pembulatan',
                'b.kali',
                'a.NR',
                'a.tanda',
                'b.grup1',
                'b.grup2',
                'b.grup3',
                'c.order as grup_order'
            ])
            ->get();

        // Calculate age in days for normal range evaluation
        $ageDays = 0;
        if ($extPasien) {
            $birth = strtotime($extPasien->tgl_lahir);
            $ageDays = floor((time() - $birth) / (60 * 60 * 24));
        }

        // Group rows in hierarchy (grup1 -> grup2 -> grup3)
        $groupedDetails = [];
        foreach ($detail as $row) {
            // Evaluates normal range and flags
            $gender = ($extPasien->jk ?? 'L') === 'L' ? 1 : 2;
            
            // Format Nilai according to precision config
            $val = floatval($row->Nilai);
            if ($row->pembulatan == 0) {
                $formattedVal = floor($val);
            } elseif ($row->pembulatan == 99) {
                $formattedVal = $row->Nilai;
            } else {
                $formattedVal = round($val, $row->pembulatan);
            }

            if ($row->kali != 0) {
                $formattedVal = $formattedVal * $row->kali;
            }

            // Flag and Kritis detection (simplified logic mimicking VKode DB logic)
            // You can query v_kode or run standard evaluation.
            $flag = $row->tanda ?: '';
            $isKritis = false;
            
            // If custom normal range exists
            $nr = $row->NR ?: '';
            if (empty($nr)) {
                // Fetch from v_kode
                $nr = DB::connection('lis')
                    ->table('v_kode')
                    ->where('lis', $row->KodeParamater)
                    ->where('sex', $gender)
                    ->where('umur1', '<=', $ageDays)
                    ->where('umur2', '>=', $ageDays)
                    ->value('nr') ?? '';
            }

            $row->formatted_nilai = $formattedVal;
            $row->flag = $flag;
            $row->is_kritis = $isKritis;
            $row->normal_range = $nr;

            $groupedDetails[$row->grup1][$row->grup2][$row->grup3][] = $row;
        }

        return view('pages.lis.periksa.cetak', [
            'model' => $periksa,
            'extPasien' => $extPasien,
            'extDokter' => $extDokter,
            'extRuang' => $extRuang,
            'extReg' => $extReg,
            'groupedDetails' => $groupedDetails,
        ]);
    }

    public function cetakBiaya($id)
    {
        $periksa = Periksa::findOrFail($id);
        $modelBiaya = PeriksaBiaya::where('periksa_id', $id)->get();
        $total = $modelBiaya->sum('tarif');

        $periksa->total = $total;
        $periksa->save();

        return view('pages.lis.periksa.cetak-biaya', [
            'model' => $periksa,
            'modelBiaya' => $modelBiaya,
            'total' => $total,
        ]);
    }

    public function searchPasien(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $date = $request->input('date');
        $search = trim($request->input('q', ''));

        $results = [];

        // 1. Search in external SIMRS database first
        try {
            $extConn = $databaseManager->connection();
            if ($extConn) {
                $query = $extConn->table('reg_periksa as r')
                    ->join('pasien as p', 'r.no_rkm_medis', '=', 'p.no_rkm_medis')
                    ->join('dokter as d', 'r.kd_dokter', '=', 'd.kd_dokter')
                    ->join('poliklinik as pol', 'r.kd_poli', '=', 'pol.kd_poli')
                    ->join('penjab as pen', 'r.kd_pj', '=', 'pen.kd_pj');

                if ($date) {
                    $query->where('r.tgl_registrasi', $date);
                }

                if ($search !== '') {
                    $query->where(function($q) use ($search) {
                        $q->where('r.no_rkm_medis', 'like', "%{$search}%")
                          ->orWhere('p.nm_pasien', 'like', "%{$search}%");
                    });
                }

                $results = $query->select([
                        'r.no_rkm_medis as no_rm',
                        'p.nm_pasien as nama',
                        'p.tmp_lahir as tempat_lahir',
                        'p.tgl_lahir',
                        'p.jk as gender',
                        'p.alamat',
                        'r.kd_dokter',
                        'd.nm_dokter as nama_dokter',
                        'r.kd_poli',
                        'pol.nm_poli as nama_ruang',
                        'r.kd_pj',
                        'pen.png_jawab as nama_penjamin',
                        'r.no_reg',
                        'r.no_rawat'
                    ])
                    ->limit(50)
                    ->get()
                    ->map(function($p) {
                        $localDokter = null;
                        if (!empty($p->kd_dokter)) {
                            $localDokter = Dokter::firstOrCreate(
                                ['id_dokter' => $p->kd_dokter],
                                [
                                    'nama' => $p->nama_dokter ?: 'Dokter SIMRS',
                                    'kode' => 1
                                ]
                            );
                        }

                        $localRuang = null;
                        if (!empty($p->kd_poli)) {
                            $localRuang = Ruang::firstOrCreate(
                                ['poli_id' => $p->kd_poli],
                                [
                                    'nama' => $p->nama_ruang ?: 'Poli SIMRS'
                                ]
                            );
                        }

                        return [
                            'no_rm' => $p->no_rm,
                            'nama' => $p->nama,
                            'tempat_lahir' => $p->tempat_lahir ?? '',
                            'tgl_lahir' => date('d-m-Y', strtotime($p->tgl_lahir)),
                            'gender' => ($p->gender === 'L') ? 1 : 2,
                            'alamat' => $p->alamat ?? '',
                            'kd_dokter' => $p->kd_dokter,
                            'id_dokter_local' => $localDokter ? $localDokter->id : null,
                            'kd_poli' => $p->kd_poli,
                            'id_ruang_local' => $localRuang ? $localRuang->id : null,
                            'kd_pj' => $p->kd_pj,
                            'nama_penjamin' => $p->nama_penjamin,
                            'no_reg' => $p->no_reg,
                            'no_rawat' => $p->no_rawat,
                            'source' => 'simrs'
                        ];
                    })
                    ->toArray();
            }
        } catch (Throwable $e) {}

        // 2. Also search in local LIS database if a search query is provided
        if ($search !== '') {
            try {
                $localPasiens = Pasien::where('no_rm', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->limit(20)
                    ->get()
                    ->map(function($p) {
                        return [
                            'no_rm' => $p->no_rm,
                            'nama' => $p->nama,
                            'tempat_lahir' => $p->tempat_lahir ?? '',
                            'tgl_lahir' => $p->tgl_lahir,
                            'gender' => (int)$p->gender,
                            'alamat' => $p->alamat ?? '',
                            'source' => 'local',
                            'id' => $p->id
                        ];
                    })
                    ->toArray();

                $existingRms = array_column($results, 'no_rm');
                foreach ($localPasiens as $lp) {
                    if (!in_array($lp['no_rm'], $existingRms)) {
                        $results[] = $lp;
                    }
                }
            } catch (Throwable $e) {}
        }

        return response()->json($results);
    }

    public function storeManual(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'id_dokter' => ['required', 'integer'],
            'id_dokter2' => ['required', 'integer'],
            'id_ruang' => ['required', 'integer'],
            'id_petugas' => ['required', 'integer'],
            'id_penjamin' => ['required', 'integer'],
            'no_rm' => ['nullable', 'string', 'max:50'],
            'nama' => ['nullable', 'string', 'max:150'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tgl_lahir' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'integer'],
            'alamat' => ['nullable', 'string'],
            'no_reg' => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($validated['no_rm'])) {
            return back()->withErrors(['error' => 'Pasien harus dipilih atau didaftarkan terlebih dahulu.']);
        }

        // Sync / Create patient locally
        $pasien = Pasien::where('no_rm', $validated['no_rm'])->first();
        if (!$pasien) {
            $pasien = Pasien::create([
                'no_rm' => $validated['no_rm'],
                'nama' => $validated['nama'] ?? '-',
                'tempat_lahir' => $validated['tempat_lahir'] ?? '',
                'tgl_lahir' => $validated['tgl_lahir'] ?? date('d-m-Y'),
                'gender' => $validated['gender'] ?? 1,
                'alamat' => $validated['alamat'] ?? '',
                'state' => 1,
                'id_instansi' => 0
            ]);
        }

        // Generate next LIS periksa number
        $seq = Periksa::getSeq();
        $nomor = date("ymd", strtotime($validated['tanggal'])) . $seq;

        // Create Periksa
        $periksa = Periksa::create([
            'tanggal' => $validated['tanggal'] . ' ' . date('H:i:s'),
            'seq' => $seq,
            'nomor' => $nomor,
            'no_reg' => $validated['no_reg'] ?? '-',
            'id_pasien' => $pasien->id,
            'id_dokter' => $validated['id_dokter'],
            'id_dokter2' => $validated['id_dokter2'],
            'id_ruang' => $validated['id_ruang'],
            'id_petugas' => $validated['id_petugas'],
            'id_penjamin' => $validated['id_penjamin'],
            'id_paket' => 0,
            'unit' => 'Laboratorium',
            'create_by' => auth()->user()->name ?? 'System',
            'create_at' => now()->toDateTimeString(),
            'validasi' => 0,
            'state' => 0
        ]);

        return redirect()->route('lis.periksa.show', $periksa->id)
            ->with('success', 'Pemeriksaan manual berhasil disimpan.');
    }

    public function destroy($id)
    {
        $periksa = Periksa::findOrFail($id);

        // Delete related results
        Result::where('KodePatient', $periksa->nomor)->delete();

        // Delete related biaya
        PeriksaBiaya::where('periksa_id', $id)->delete();

        // Delete related SIMRS details
        PeriksaSimrsDetail::where('no_lab', $periksa->no_reg)->delete();

        // Delete the periksa record
        $periksa->delete();

        return back()->with('success', 'Data pemeriksaan LIS berhasil dihapus.');
    }

    public function syncSimrs($id)
    {
        $periksa = Periksa::findOrFail($id);

        if (empty($periksa->no_reg) || $periksa->no_reg === '-') {
            return back()->withErrors(['error' => 'Pemeriksaan ini bukan berasal dari SIMRS.']);
        }

        $this->syncResultsToSimrs($periksa);

        return back()->with('success', 'Hasil pemeriksaan LIS berhasil disinkronkan ke SIMRS.');
    }

    private function syncResultsToSimrs(Periksa $periksa)
    {
        $noorder = $periksa->no_reg;
        if (empty($noorder) || $noorder === '-') {
            return;
        }

        try {
            $databaseManager = app(ExternalDatabaseManager::class);
            $conn = $databaseManager->connection();

            // Find SIMRS order
            $orderInfo = $conn->table('permintaan_lab')->where('noorder', $noorder)->first();
            if (!$orderInfo) {
                return;
            }

            $no_rawat = $orderInfo->no_rawat;
            $tgl_periksa = date('Y-m-d', strtotime($periksa->tanggal));
            $jam = date('H:i:s', strtotime($periksa->tanggal));
            $kd_dokter = $periksa->dokter->id_dokter ?? '';
            $dokter_perujuk = $orderInfo->dokter_perujuk ?? '';
            $status = $orderInfo->status ?? 'Ralan';
            $nip = $periksa->petugas->nip ?? 'APT003';

            $mappings = PeriksaSimrsDetail::where('no_lab', $noorder)->get();
            if ($mappings->isEmpty()) {
                // If mappings are empty, try to populate them from SIMRS order details
                try {
                    $mainDetails = $conn->table('permintaan_detail_permintaan_lab as dp')
                        ->where('dp.noorder', $noorder)
                        ->select('dp.noorder', 'dp.kd_jenis_prw', 'dp.id_template')
                        ->get();

                    if ($mainDetails->isNotEmpty()) {
                        foreach ($mainDetails as $md) {
                            $lisCode = KodeSimrs::where('grup', $md->kd_jenis_prw)
                                ->where('kode', $md->id_template)
                                ->value('lis') ?? '';

                            PeriksaSimrsDetail::firstOrCreate(
                                [
                                    'no_lab' => $noorder,
                                    'grup' => $md->kd_jenis_prw,
                                    'kode' => $md->id_template,
                                ],
                                [
                                    'nama' => '',
                                    'lis' => $lisCode
                                ]
                            );
                        }
                        $mappings = PeriksaSimrsDetail::where('no_lab', $noorder)->get();
                    }
                } catch (Throwable $e) {}
            }

            foreach ($mappings as $map) {
                $kd_jenis_prw = $map->grup;
                $id_template = $map->kode;
                $lisCode = $map->lis;

                // Fetch result value
                $resultVal = Result::where('KodePatient', $periksa->nomor)
                    ->where('KodeParamater', $lisCode)
                    ->first();

                // Ensure periksa_lab row exists
                $jns = $conn->table('jns_perawatan_lab')->where('kd_jenis_prw', $kd_jenis_prw)->first();
                $existsHeader = $conn->table('periksa_lab')
                    ->where('no_rawat', $no_rawat)
                    ->where('kd_jenis_prw', $kd_jenis_prw)
                    ->where('tgl_periksa', $tgl_periksa)
                    ->where('jam', $jam)
                    ->exists();

                if (!$existsHeader) {
                    $conn->table('periksa_lab')->insert([
                        'no_rawat' => $no_rawat,
                        'nip' => $nip,
                        'kd_jenis_prw' => $kd_jenis_prw,
                        'tgl_periksa' => $tgl_periksa,
                        'jam' => $jam,
                        'dokter_perujuk' => $dokter_perujuk,
                        'bagian_rs' => $jns->bagian_rs ?? 0,
                        'bhp' => $jns->bhp ?? 0,
                        'tarif_perujuk' => $jns->tarif_perujuk ?? 0,
                        'tarif_tindakan_dokter' => $jns->tarif_tindakan_dokter ?? 0,
                        'tarif_tindakan_petugas' => $jns->tarif_tindakan_petugas ?? 0,
                        'kso' => $jns->kso ?? 0,
                        'menejemen' => $jns->menejemen ?? 0,
                        'biaya' => $jns->total_byr ?? 0,
                        'kd_dokter' => $kd_dokter,
                        'status' => $status,
                        'kategori' => 'PK'
                    ]);
                }

                // Ensure detail_periksa_lab row
                $template = $conn->table('template_laboratorium')->where('id_template', $id_template)->first();

                $existsDetail = $conn->table('detail_periksa_lab')
                    ->where('no_rawat', $no_rawat)
                    ->where('kd_jenis_prw', $kd_jenis_prw)
                    ->where('tgl_periksa', $tgl_periksa)
                    ->where('jam', $jam)
                    ->where('id_template', $id_template)
                    ->exists();

                if ($existsDetail) {
                    $conn->table('detail_periksa_lab')
                        ->where('no_rawat', $no_rawat)
                        ->where('kd_jenis_prw', $kd_jenis_prw)
                        ->where('tgl_periksa', $tgl_periksa)
                        ->where('jam', $jam)
                        ->where('id_template', $id_template)
                        ->update([
                            'nilai' => $resultVal->Nilai ?? '',
                            'nilai_rujukan' => $resultVal->NR ?? ($resultVal->parameter ?? ''),
                            'keterangan' => $resultVal->keterangan ?? '',
                        ]);
                } else {
                    $conn->table('detail_periksa_lab')->insert([
                        'no_rawat' => $no_rawat,
                        'kd_jenis_prw' => $kd_jenis_prw,
                        'tgl_periksa' => $tgl_periksa,
                        'jam' => $jam,
                        'id_template' => $id_template,
                        'nilai' => $resultVal->Nilai ?? '',
                        'nilai_rujukan' => $resultVal->NR ?? ($resultVal->parameter ?? ''),
                        'keterangan' => $resultVal->keterangan ?? '',
                        'bagian_rs' => $template->bagian_rs ?? 0,
                        'bhp' => $template->bhp ?? 0,
                        'bagian_perujuk' => $template->bagian_perujuk ?? 0,
                        'bagian_dokter' => $template->bagian_dokter ?? 0,
                        'bagian_laborat' => $template->bagian_laborat ?? 0,
                        'kso' => $template->kso ?? 0,
                        'menejemen' => $template->menejemen ?? 0,
                        'biaya_item' => $template->biaya_item ?? 0
                    ]);
                }
            }

            // Sync notes / saran
            if (!empty($periksa->note)) {
                $existsSaran = $conn->table('saran_kesan_lab')
                    ->where('no_rawat', $no_rawat)
                    ->where('tgl_periksa', $tgl_periksa)
                    ->where('jam', $jam)
                    ->exists();
                if ($existsSaran) {
                    $conn->table('saran_kesan_lab')
                        ->where('no_rawat', $no_rawat)
                        ->where('tgl_periksa', $tgl_periksa)
                        ->where('jam', $jam)
                        ->update([
                            'saran' => $periksa->note,
                            'kesan' => ''
                        ]);
                } else {
                    $conn->table('saran_kesan_lab')->insert([
                        'no_rawat' => $no_rawat,
                        'tgl_periksa' => $tgl_periksa,
                        'jam' => $jam,
                        'saran' => $periksa->note,
                        'kesan' => ''
                    ]);
                }
            }

            // Update permintaan_lab order
            $conn->table('permintaan_lab')
                ->where('noorder', $noorder)
                ->update([
                    'tgl_hasil' => $tgl_periksa,
                    'jam_hasil' => $jam
                ]);

        } catch (Throwable $e) {
            // Log or ignore
        }
    }
}
