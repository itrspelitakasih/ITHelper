<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ExternalDatabaseManager;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Throwable;

class LaporanController extends Controller
{
    public function kunjunganRalan(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'kd_dokter' => ['nullable', 'string', 'max:50'],
            'kd_poli' => ['nullable', 'string', 'max:50'],
            'kd_pj' => ['nullable', 'string', 'max:50'],
            'status_daftar' => ['nullable', 'string', 'in:Semua,Baru,Lama'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $kdDokter = $filters['kd_dokter'] ?? '';
        $kdPoli = $filters['kd_poli'] ?? '';
        $kdPj = $filters['kd_pj'] ?? '';
        $statusDaftar = $filters['status_daftar'] ?? 'Semua';
        $showAll = $filters['show_all'] ?? false;

        $registrations = $this->emptyPaginator($request);
        $dokters = collect();
        $polis = collect();
        $penjabs = collect();
        $connectionError = null;

        $stats = [
            'lama' => 0,
            'baru' => 0,
            'laki' => 0,
            'perempuan' => 0,
        ];

        try {
            $connection = $databaseManager->connection();
            
            $dokters = $connection->table('dokter')->select(['kd_dokter', 'nm_dokter'])->orderBy('nm_dokter')->get();
            $polis = $connection->table('poliklinik')->select(['kd_poli', 'nm_poli'])->orderBy('nm_poli')->get();
            $penjabs = $connection->table('penjab')->select(['kd_pj', 'png_jawab'])->orderBy('png_jawab')->get();

            // Check if user requests Excel export
            $export = $request->input('export');
            if ($export === 'excel') {
                $query = $connection->table('reg_periksa')
                    ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                    ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                    ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                    ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                    ->join('kabupaten', 'pasien.kd_kab', '=', 'kabupaten.kd_kab')
                    ->join('kecamatan', 'pasien.kd_kec', '=', 'kecamatan.kd_kec')
                    ->join('kelurahan', 'pasien.kd_kel', '=', 'kelurahan.kd_kel')
                    ->where('reg_periksa.status_lanjut', 'Ralan')
                    ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to]);

                if ($statusDaftar !== 'Semua') {
                    $query->where('reg_periksa.stts_daftar', $statusDaftar);
                }
                if ($kdDokter !== '') {
                    $query->where('reg_periksa.kd_dokter', $kdDokter);
                }
                if ($kdPoli !== '') {
                    $query->where('reg_periksa.kd_poli', $kdPoli);
                }
                if ($kdPj !== '') {
                    $query->where('reg_periksa.kd_pj', $kdPj);
                }

                $records = $query->select([
                    'reg_periksa.no_rawat',
                    'reg_periksa.tgl_registrasi',
                    'reg_periksa.stts_daftar',
                    'reg_periksa.no_rkm_medis',
                    'reg_periksa.umurdaftar',
                    'reg_periksa.sttsumur',
                    'pasien.nm_pasien',
                    'pasien.jk',
                    'pasien.alamat',
                    'kelurahan.nm_kel',
                    'kecamatan.nm_kec',
                    'kabupaten.nm_kab',
                    'poliklinik.nm_poli',
                    'dokter.nm_dokter as nama_dokter',
                ])
                ->orderBy('reg_periksa.tgl_registrasi')
                ->orderBy('reg_periksa.jam_reg')
                ->get();

                // Eager load diagnosis
                $noRawats = $records->pluck('no_rawat')->toArray();
                $diagnoses = collect();
                if (count($noRawats) > 0) {
                    $diagnoses = $connection->table('diagnosa_pasien')
                        ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
                        ->whereIn('diagnosa_pasien.no_rawat', $noRawats)
                        ->select(['diagnosa_pasien.no_rawat', 'penyakit.kd_penyakit', 'penyakit.nm_penyakit'])
                        ->orderBy('diagnosa_pasien.prioritas')
                        ->get()
                        ->groupBy('no_rawat');
                }

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Kunjungan Ralan');

                // Header
                $sheet->setCellValue('A1', 'LAPORAN KUNJUNGAN RAWAT JALAN');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                
                $sheet->setCellValue('A2', 'Periode: ' . date('d-m-Y', strtotime($from)) . ' s/d ' . date('d-m-Y', strtotime($to)));
                $sheet->mergeCells('A2:I2');

                // Column headers
                $sheet->setCellValue('A4', 'No');
                $sheet->mergeCells('A4:A5');
                
                $sheet->setCellValue('B4', 'No. RKM Medis');
                $sheet->mergeCells('B4:C4');
                $sheet->setCellValue('B5', 'Lama');
                $sheet->setCellValue('C5', 'Baru');
                
                $sheet->setCellValue('D4', 'Nama Pasien');
                $sheet->mergeCells('D4:D5');
                
                $sheet->setCellValue('E4', 'Umur');
                $sheet->mergeCells('E4:F4');
                $sheet->setCellValue('E5', 'L');
                $sheet->setCellValue('F5', 'P');
                
                $sheet->setCellValue('G4', 'Alamat PJ');
                $sheet->mergeCells('G4:G5');
                
                $sheet->setCellValue('H4', 'Poliklinik');
                $sheet->mergeCells('H4:H5');
                
                $sheet->setCellValue('I4', 'Dokter Dituju');
                $sheet->mergeCells('I4:I5');

                $sheet->getStyle('A4:I5')->getFont()->setBold(true);
                $sheet->getStyle('A4:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4:I5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $row = 6;
                $no = 1;
                $totalLama = 0;
                $totalBaru = 0;
                $totalLaki = 0;
                $totalPerempuan = 0;

                foreach ($records as $reg) {
                    $sheet->setCellValue('A' . $row, $no++);
                    
                    if ($reg->stts_daftar === 'Lama') {
                        $sheet->setCellValue('B' . $row, $reg->no_rkm_medis);
                        $totalLama++;
                    } else {
                        $sheet->setCellValue('C' . $row, $reg->no_rkm_medis);
                        $totalBaru++;
                    }

                    $sheet->setCellValue('D' . $row, $reg->nm_pasien);

                    $umurStr = $reg->umurdaftar . ' ' . $reg->sttsumur;
                    if ($reg->jk === 'L') {
                        $sheet->setCellValue('E' . $row, $umurStr);
                        $totalLaki++;
                    } else {
                        $sheet->setCellValue('F' . $row, $umurStr);
                        $totalPerempuan++;
                    }

                    $alamatFull = $reg->alamat . ', ' . $reg->nm_kel . ', ' . $reg->nm_kec . ', ' . $reg->nm_kab;
                    $sheet->setCellValue('G' . $row, $alamatFull);
                    $sheet->setCellValue('H' . $row, $reg->nm_poli);
                    $sheet->setCellValue('I' . $row, $reg->nama_dokter);

                    $row++;
                }

                // Rekap Total Row
                $sheet->setCellValue('A' . $row, '>>');
                $sheet->setCellValue('B' . $row, $totalLama);
                $sheet->setCellValue('C' . $row, $totalBaru);
                $sheet->setCellValue('D' . $row, 'Total Kunjungan: ' . ($totalLama + $totalBaru));
                $sheet->setCellValue('E' . $row, $totalLaki);
                $sheet->setCellValue('F' . $row, $totalPerempuan);
                $sheet->setCellValue('G' . $row, 'Total L/P: ' . ($totalLaki + $totalPerempuan));

                $sheet->getStyle('A' . $row . ':I' . $row)->getFont()->setBold(true);

                // Auto size columns
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                return response()->streamDownload(function() use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                }, 'laporan-kunjungan-ralan.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]);
            }

            $query = $connection->table('reg_periksa')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->join('poliklinik', 'reg_periksa.kd_poli', '=', 'poliklinik.kd_poli')
                ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                ->join('kabupaten', 'pasien.kd_kab', '=', 'kabupaten.kd_kab')
                ->join('kecamatan', 'pasien.kd_kec', '=', 'kecamatan.kd_kec')
                ->join('kelurahan', 'pasien.kd_kel', '=', 'kelurahan.kd_kel')
                ->where('reg_periksa.status_lanjut', 'Ralan')
                ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to]);

            if ($statusDaftar !== 'Semua') {
                $query->where('reg_periksa.stts_daftar', $statusDaftar);
            }
            if ($kdDokter !== '') {
                $query->where('reg_periksa.kd_dokter', $kdDokter);
            }
            if ($kdPoli !== '') {
                $query->where('reg_periksa.kd_poli', $kdPoli);
            }
            if ($kdPj !== '') {
                $query->where('reg_periksa.kd_pj', $kdPj);
            }

            // Stats Rekap
            $statsQuery = clone $query;
            $statsRaw = $statsQuery->selectRaw("
                sum(case when reg_periksa.stts_daftar = 'Lama' then 1 else 0 end) as total_lama,
                sum(case when reg_periksa.stts_daftar = 'Baru' then 1 else 0 end) as total_baru,
                sum(case when pasien.jk = 'L' then 1 else 0 end) as total_lk,
                sum(case when pasien.jk = 'P' then 1 else 0 end) as total_pr
            ")->first();

            if ($statsRaw) {
                $stats['lama'] = (int) $statsRaw->total_lama;
                $stats['baru'] = (int) $statsRaw->total_baru;
                $stats['laki'] = (int) $statsRaw->total_lk;
                $stats['perempuan'] = (int) $statsRaw->total_pr;
            }

            $registrations = $query->select([
                'reg_periksa.no_rawat',
                'reg_periksa.tgl_registrasi',
                'reg_periksa.stts_daftar',
                'reg_periksa.no_rkm_medis',
                'reg_periksa.umurdaftar',
                'reg_periksa.sttsumur',
                'pasien.nm_pasien',
                'pasien.jk',
                'pasien.alamat',
                'kelurahan.nm_kel',
                'kecamatan.nm_kec',
                'kabupaten.nm_kab',
                'poliklinik.nm_poli',
                'dokter.nm_dokter as nama_dokter',
            ])
            ->orderBy('reg_periksa.tgl_registrasi')
            ->orderBy('reg_periksa.jam_reg')
            ->paginate($showAll ? 1000000 : 50)
            ->withQueryString();

            // Eager load diagnosis
            $noRawats = $registrations->pluck('no_rawat')->toArray();
            if (count($noRawats) > 0) {
                $diagnoses = $connection->table('diagnosa_pasien')
                    ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
                    ->whereIn('diagnosa_pasien.no_rawat', $noRawats)
                    ->select(['diagnosa_pasien.no_rawat', 'penyakit.kd_penyakit', 'penyakit.nm_penyakit'])
                    ->orderBy('diagnosa_pasien.prioritas')
                    ->get()
                    ->groupBy('no_rawat');

                foreach ($registrations as $reg) {
                    $regDiag = $diagnoses->get($reg->no_rawat)?->first();
                    $reg->kd_penyakit = $regDiag?->kd_penyakit ?? '-';
                    $reg->nm_penyakit = $regDiag?->nm_penyakit ?? '-';
                }
            }

        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Database Eksternal. Error: ' . $exception->getMessage();
        }

        return view('pages.laporan.kunjungan-ralan', [
            'title' => 'Laporan Kunjungan Rawat Jalan',
            'registrations' => $registrations,
            'dokters' => $dokters,
            'polis' => $polis,
            'penjabs' => $penjabs,
            'connectionError' => $connectionError,
            'stats' => $stats,
            'filters' => compact('from', 'to', 'kdDokter', 'kdPoli', 'kdPj', 'statusDaftar', 'showAll'),
        ]);
    }

    public function kunjunganRanap(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'kd_dokter' => ['nullable', 'string', 'max:50'],
            'kd_bangsal' => ['nullable', 'string', 'max:50'],
            'kd_pj' => ['nullable', 'string', 'max:50'],
            'status_daftar' => ['nullable', 'string', 'in:Semua,Baru,Lama'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $kdDokter = $filters['kd_dokter'] ?? '';
        $kdBangsal = $filters['kd_bangsal'] ?? '';
        $kdPj = $filters['kd_pj'] ?? '';
        $statusDaftar = $filters['status_daftar'] ?? 'Semua';
        $showAll = $filters['show_all'] ?? false;

        $registrations = $this->emptyPaginator($request);
        $dokters = collect();
        $bangsals = collect();
        $penjabs = collect();
        $connectionError = null;

        $stats = [
            'lama' => 0,
            'baru' => 0,
            'laki' => 0,
            'perempuan' => 0,
        ];

        try {
            $connection = $databaseManager->connection();
            
            $dokters = $connection->table('dokter')->select(['kd_dokter', 'nm_dokter'])->orderBy('nm_dokter')->get();
            $bangsals = $connection->table('bangsal')->select(['kd_bangsal', 'nm_bangsal'])->orderBy('nm_bangsal')->get();
            $penjabs = $connection->table('penjab')->select(['kd_pj', 'png_jawab'])->orderBy('png_jawab')->get();

            // Check if user requests Excel export
            $export = $request->input('export');
            if ($export === 'excel') {
                $query = $connection->table('reg_periksa')
                    ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                    ->join('kamar_inap', 'reg_periksa.no_rawat', '=', 'kamar_inap.no_rawat')
                    ->join('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                    ->join('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal')
                    ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                    ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                    ->join('kabupaten', 'pasien.kd_kab', '=', 'kabupaten.kd_kab')
                    ->join('kecamatan', 'pasien.kd_kec', '=', 'kecamatan.kd_kec')
                    ->join('kelurahan', 'pasien.kd_kel', '=', 'kelurahan.kd_kel')
                    ->where('reg_periksa.status_lanjut', 'Ranap')
                    ->where('reg_periksa.stts', '<>', 'Batal')
                    ->where('kamar_inap.stts_pulang', '<>', 'Pindah Kamar')
                    ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to]);

                if ($statusDaftar !== 'Semua') {
                    $query->where('reg_periksa.stts_daftar', $statusDaftar);
                }
                if ($kdDokter !== '') {
                    $query->where('reg_periksa.kd_dokter', $kdDokter);
                }
                if ($kdBangsal !== '') {
                    $query->where('kamar.kd_bangsal', $kdBangsal);
                }
                if ($kdPj !== '') {
                    $query->where('reg_periksa.kd_pj', $kdPj);
                }

                $records = $query->select([
                    'reg_periksa.no_rawat',
                    'reg_periksa.tgl_registrasi',
                    'reg_periksa.stts_daftar',
                    'reg_periksa.no_rkm_medis',
                    'reg_periksa.umurdaftar',
                    'reg_periksa.sttsumur',
                    'pasien.nm_pasien',
                    'pasien.jk',
                    'pasien.alamat',
                    'kelurahan.nm_kel',
                    'kecamatan.nm_kec',
                    'kabupaten.nm_kab',
                    'kamar_inap.kd_kamar',
                    'kamar_inap.stts_pulang',
                    'kamar_inap.tgl_masuk',
                    'bangsal.nm_bangsal',
                    'dokter.nm_dokter as nama_dokter',
                ])
                ->groupBy('reg_periksa.no_rawat')
                ->orderBy('reg_periksa.tgl_registrasi')
                ->get();

                // Eager load diagnosis & DPJP
                $noRawats = $records->pluck('no_rawat')->toArray();
                $diagnoses = collect();
                $dpjps = collect();
                if (count($noRawats) > 0) {
                    $diagnoses = $connection->table('diagnosa_pasien')
                        ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
                        ->whereIn('diagnosa_pasien.no_rawat', $noRawats)
                        ->select(['diagnosa_pasien.no_rawat', 'penyakit.kd_penyakit', 'penyakit.nm_penyakit'])
                        ->orderBy('diagnosa_pasien.prioritas')
                        ->get()
                        ->groupBy('no_rawat');

                    $dpjps = $connection->table('dpjp_ranap')
                        ->join('dokter', 'dpjp_ranap.kd_dokter', '=', 'dokter.kd_dokter')
                        ->whereIn('dpjp_ranap.no_rawat', $noRawats)
                        ->select(['dpjp_ranap.no_rawat', 'dokter.nm_dokter'])
                        ->get()
                        ->groupBy('no_rawat');
                }

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Kunjungan Ranap');

                // Header
                $sheet->setCellValue('A1', 'LAPORAN KUNJUNGAN RAWAT INAP');
                $sheet->mergeCells('A1:K1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                
                $sheet->setCellValue('A2', 'Periode: ' . date('d-m-Y', strtotime($from)) . ' s/d ' . date('d-m-Y', strtotime($to)));
                $sheet->mergeCells('A2:K2');

                // Column headers
                $sheet->setCellValue('A4', 'No');
                $sheet->mergeCells('A4:A5');
                
                $sheet->setCellValue('B4', 'No. RKM Medis');
                $sheet->mergeCells('B4:C4');
                $sheet->setCellValue('B5', 'Lama');
                $sheet->setCellValue('C5', 'Baru');
                
                $sheet->setCellValue('D4', 'Nama Pasien');
                $sheet->mergeCells('D4:D5');
                
                $sheet->setCellValue('E4', 'Umur');
                $sheet->mergeCells('E4:F4');
                $sheet->setCellValue('E5', 'L');
                $sheet->setCellValue('F5', 'P');
                
                $sheet->setCellValue('G4', 'Alamat PJ');
                $sheet->mergeCells('G4:G5');
                
                $sheet->setCellValue('H4', 'Ruang/Kamar');
                $sheet->mergeCells('H4:H5');
                
                $sheet->setCellValue('I4', 'Stts Pulang');
                $sheet->mergeCells('I4:I5');
                
                $sheet->setCellValue('J4', 'Tgl Masuk');
                $sheet->mergeCells('J4:J5');
                
                $sheet->setCellValue('K4', 'Dokter DPJP');
                $sheet->mergeCells('K4:K5');

                $sheet->getStyle('A4:K5')->getFont()->setBold(true);
                $sheet->getStyle('A4:K5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4:K5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                $row = 6;
                $no = 1;
                $totalLama = 0;
                $totalBaru = 0;
                $totalLaki = 0;
                $totalPerempuan = 0;

                foreach ($records as $reg) {
                    $mainDokter = $reg->nama_dokter;
                    $dpjpList = $dpjps->get($reg->no_rawat);
                    if ($dpjpList) {
                        $names = $dpjpList->pluck('nm_dokter')->toArray();
                        $dokterDpjp = implode(', ', $names) . ', ' . $mainDokter;
                    } else {
                        $dokterDpjp = $mainDokter;
                    }

                    $sheet->setCellValue('A' . $row, $no++);
                    
                    if ($reg->stts_daftar === 'Lama') {
                        $sheet->setCellValue('B' . $row, $reg->no_rkm_medis);
                        $totalLama++;
                    } else {
                        $sheet->setCellValue('C' . $row, $reg->no_rkm_medis);
                        $totalBaru++;
                    }

                    $sheet->setCellValue('D' . $row, $reg->nm_pasien);

                    $umurStr = $reg->umurdaftar . ' ' . $reg->sttsumur;
                    if ($reg->jk === 'L') {
                        $sheet->setCellValue('E' . $row, $umurStr);
                        $totalLaki++;
                    } else {
                        $sheet->setCellValue('F' . $row, $umurStr);
                        $totalPerempuan++;
                    }

                    $alamatFull = $reg->alamat . ', ' . $reg->nm_kel . ', ' . $reg->nm_kec . ', ' . $reg->nm_kab;
                    $sheet->setCellValue('G' . $row, $alamatFull);
                    $sheet->setCellValue('H' . $row, $reg->kd_kamar . ' (' . $reg->nm_bangsal . ')');
                    $sheet->setCellValue('I' . $row, $reg->stts_pulang);
                    $sheet->setCellValue('J' . $row, date('d-m-Y', strtotime($reg->tgl_masuk)));
                    $sheet->setCellValue('K' . $row, $dokterDpjp);

                    $row++;
                }

                // Rekap Total Row
                $sheet->setCellValue('A' . $row, '>>');
                $sheet->setCellValue('B' . $row, $totalLama);
                $sheet->setCellValue('C' . $row, $totalBaru);
                $sheet->setCellValue('D' . $row, 'Total Kunjungan: ' . ($totalLama + $totalBaru));
                $sheet->setCellValue('E' . $row, $totalLaki);
                $sheet->setCellValue('F' . $row, $totalPerempuan);
                $sheet->setCellValue('G' . $row, 'Total L/P: ' . ($totalLaki + $totalPerempuan));

                $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setBold(true);

                // Auto size columns
                foreach (range('A', 'K') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                return response()->streamDownload(function() use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                }, 'laporan-kunjungan-ranap.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]);
            }

            $query = $connection->table('reg_periksa')
                ->join('pasien', 'reg_periksa.no_rkm_medis', '=', 'pasien.no_rkm_medis')
                ->join('kamar_inap', 'reg_periksa.no_rawat', '=', 'kamar_inap.no_rawat')
                ->join('kamar', 'kamar_inap.kd_kamar', '=', 'kamar.kd_kamar')
                ->join('bangsal', 'kamar.kd_bangsal', '=', 'bangsal.kd_bangsal')
                ->join('dokter', 'reg_periksa.kd_dokter', '=', 'dokter.kd_dokter')
                ->join('penjab', 'reg_periksa.kd_pj', '=', 'penjab.kd_pj')
                ->join('kabupaten', 'pasien.kd_kab', '=', 'kabupaten.kd_kab')
                ->join('kecamatan', 'pasien.kd_kec', '=', 'kecamatan.kd_kec')
                ->join('kelurahan', 'pasien.kd_kel', '=', 'kelurahan.kd_kel')
                ->where('reg_periksa.status_lanjut', 'Ranap')
                ->where('reg_periksa.stts', '<>', 'Batal')
                ->where('kamar_inap.stts_pulang', '<>', 'Pindah Kamar')
                ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to]);

            if ($statusDaftar !== 'Semua') {
                $query->where('reg_periksa.stts_daftar', $statusDaftar);
            }
            if ($kdDokter !== '') {
                $query->where('reg_periksa.kd_dokter', $kdDokter);
            }
            if ($kdBangsal !== '') {
                $query->where('kamar.kd_bangsal', $kdBangsal);
            }
            if ($kdPj !== '') {
                $query->where('reg_periksa.kd_pj', $kdPj);
            }

            // Stats Rekap
            $statsQuery = clone $query;
            $statsRaw = $statsQuery->selectRaw("
                count(distinct case when reg_periksa.stts_daftar = 'Lama' then reg_periksa.no_rawat end) as total_lama,
                count(distinct case when reg_periksa.stts_daftar = 'Baru' then reg_periksa.no_rawat end) as total_baru,
                count(distinct case when pasien.jk = 'L' then reg_periksa.no_rawat end) as total_lk,
                count(distinct case when pasien.jk = 'P' then reg_periksa.no_rawat end) as total_pr
            ")->first();

            if ($statsRaw) {
                $stats['lama'] = (int) $statsRaw->total_lama;
                $stats['baru'] = (int) $statsRaw->total_baru;
                $stats['laki'] = (int) $statsRaw->total_lk;
                $stats['perempuan'] = (int) $statsRaw->total_pr;
            }

            $registrations = $query->select([
                'reg_periksa.no_rawat',
                'reg_periksa.tgl_registrasi',
                'reg_periksa.stts_daftar',
                'reg_periksa.no_rkm_medis',
                'reg_periksa.umurdaftar',
                'reg_periksa.sttsumur',
                'pasien.nm_pasien',
                'pasien.jk',
                'pasien.alamat',
                'kelurahan.nm_kel',
                'kecamatan.nm_kec',
                'kabupaten.nm_kab',
                'kamar_inap.kd_kamar',
                'kamar_inap.stts_pulang',
                'kamar_inap.tgl_masuk',
                'bangsal.nm_bangsal',
                'dokter.nm_dokter as nama_dokter',
            ])
            ->groupBy('reg_periksa.no_rawat')
            ->orderBy('reg_periksa.tgl_registrasi')
            ->paginate($showAll ? 1000000 : 50)
            ->withQueryString();

            // Eager load diagnosis & DPJP list
            $noRawats = $registrations->pluck('no_rawat')->toArray();
            if (count($noRawats) > 0) {
                // Eager load diagnoses
                $diagnoses = $connection->table('diagnosa_pasien')
                    ->join('penyakit', 'diagnosa_pasien.kd_penyakit', '=', 'penyakit.kd_penyakit')
                    ->whereIn('diagnosa_pasien.no_rawat', $noRawats)
                    ->select(['diagnosa_pasien.no_rawat', 'penyakit.kd_penyakit', 'penyakit.nm_penyakit'])
                    ->orderBy('diagnosa_pasien.prioritas')
                    ->get()
                    ->groupBy('no_rawat');

                // Eager load DPJP
                $dpjps = $connection->table('dpjp_ranap')
                    ->join('dokter', 'dpjp_ranap.kd_dokter', '=', 'dokter.kd_dokter')
                    ->whereIn('dpjp_ranap.no_rawat', $noRawats)
                    ->select(['dpjp_ranap.no_rawat', 'dokter.nm_dokter'])
                    ->get()
                    ->groupBy('no_rawat');

                foreach ($registrations as $reg) {
                    $regDiag = $diagnoses->get($reg->no_rawat)?->first();
                    $reg->kd_penyakit = $regDiag?->kd_penyakit ?? '-';
                    $reg->nm_penyakit = $regDiag?->nm_penyakit ?? '-';

                    // Combine DPJP and main doctor
                    $mainDokter = $reg->nama_dokter;
                    $dpjpList = $dpjps->get($reg->no_rawat);
                    if ($dpjpList) {
                        $names = $dpjpList->pluck('nm_dokter')->toArray();
                        $reg->dokter_dpjp = implode(', ', $names) . ', ' . $mainDokter;
                    } else {
                        $reg->dokter_dpjp = $mainDokter;
                    }
                }
            }

        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Database Eksternal. Error: ' . $exception->getMessage();
        }

        return view('pages.laporan.kunjungan-ranap', [
            'title' => 'Laporan Kunjungan Rawat Inap',
            'registrations' => $registrations,
            'dokters' => $dokters,
            'bangsals' => $bangsals,
            'penjabs' => $penjabs,
            'connectionError' => $connectionError,
            'stats' => $stats,
            'filters' => compact('from', 'to', 'kdDokter', 'kdBangsal', 'kdPj', 'statusDaftar', 'showAll'),
        ]);
    }

    public function detailJMDokter(Request $request, ExternalDatabaseManager $databaseManager)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'kd_dokter' => ['nullable', 'string', 'max:50'],
            'kd_poli' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $kdDokter = $filters['kd_dokter'] ?? '';
        $kdPoli = $filters['kd_poli'] ?? '';
        $search = $filters['search'] ?? '';
        $showAll = $filters['show_all'] ?? false;

        $transactions = $this->emptyPaginator($request);
        $dokters = collect();
        $polis = collect();
        $connectionError = null;

        $stats = [
            'total_jm' => 0,
            'total_sarana' => 0,
            'total_bhp' => 0,
            'total_kso' => 0,
            'total_menejemen' => 0,
            'total_biaya' => 0,
        ];

        try {
            $connection = $databaseManager->connection();
            
            $dokters = $connection->table('dokter')->select(['kd_dokter', 'nm_dokter'])->orderBy('nm_dokter')->get();
            $polis = $connection->table('poliklinik')->select(['kd_poli', 'nm_poli'])->orderBy('nm_poli')->get();

            // Construct Subqueries
            $q1 = $connection->table('rawat_jl_dr as t')
                ->join('reg_periksa as reg', 't.no_rawat', '=', 'reg.no_rawat')
                ->join('pasien as p', 'reg.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->join('dokter as d', 't.kd_dokter', '=', 'd.kd_dokter')
                ->join('jns_perawatan as jp', 't.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->join('poliklinik as poli', 'reg.kd_poli', '=', 'poli.kd_poli')
                ->select([
                    't.tgl_perawatan',
                    't.jam_rawat',
                    't.no_rawat',
                    'reg.no_rkm_medis',
                    'p.nm_pasien',
                    't.kd_dokter',
                    'd.nm_dokter',
                    'jp.nm_perawatan',
                    \Illuminate\Support\Facades\DB::raw("'Ralan' as status_lanjut"),
                    'poli.nm_poli as ruangan',
                    'reg.kd_poli',
                    't.tarif_tindakandr',
                    't.material',
                    't.bhp',
                    't.kso',
                    't.menejemen',
                    't.biaya_rawat'
                ]);

            $q2 = $connection->table('rawat_jl_drpr as t')
                ->join('reg_periksa as reg', 't.no_rawat', '=', 'reg.no_rawat')
                ->join('pasien as p', 'reg.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->join('dokter as d', 't.kd_dokter', '=', 'd.kd_dokter')
                ->join('jns_perawatan as jp', 't.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->join('poliklinik as poli', 'reg.kd_poli', '=', 'poli.kd_poli')
                ->select([
                    't.tgl_perawatan',
                    't.jam_rawat',
                    't.no_rawat',
                    'reg.no_rkm_medis',
                    'p.nm_pasien',
                    't.kd_dokter',
                    'd.nm_dokter',
                    'jp.nm_perawatan',
                    \Illuminate\Support\Facades\DB::raw("'Ralan' as status_lanjut"),
                    'poli.nm_poli as ruangan',
                    'reg.kd_poli',
                    't.tarif_tindakandr',
                    't.material',
                    't.bhp',
                    't.kso',
                    't.menejemen',
                    't.biaya_rawat'
                ]);

            $q3 = $connection->table('rawat_inap_dr as t')
                ->join('reg_periksa as reg', 't.no_rawat', '=', 'reg.no_rawat')
                ->join('pasien as p', 'reg.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->join('dokter as d', 't.kd_dokter', '=', 'd.kd_dokter')
                ->join('jns_perawatan_inap as jp', 't.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->select([
                    't.tgl_perawatan',
                    't.jam_rawat',
                    't.no_rawat',
                    'reg.no_rkm_medis',
                    'p.nm_pasien',
                    't.kd_dokter',
                    'd.nm_dokter',
                    'jp.nm_perawatan',
                    \Illuminate\Support\Facades\DB::raw("'Ranap' as status_lanjut"),
                    \Illuminate\Support\Facades\DB::raw("(SELECT b.nm_bangsal FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.no_rawat = t.no_rawat ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC LIMIT 1) as ruangan"),
                    \Illuminate\Support\Facades\DB::raw("'' as kd_poli"),
                    't.tarif_tindakandr',
                    't.material',
                    't.bhp',
                    't.kso',
                    't.menejemen',
                    't.biaya_rawat'
                ]);

            $q4 = $connection->table('rawat_inap_drpr as t')
                ->join('reg_periksa as reg', 't.no_rawat', '=', 'reg.no_rawat')
                ->join('pasien as p', 'reg.no_rkm_medis', '=', 'p.no_rkm_medis')
                ->join('dokter as d', 't.kd_dokter', '=', 'd.kd_dokter')
                ->join('jns_perawatan_inap as jp', 't.kd_jenis_prw', '=', 'jp.kd_jenis_prw')
                ->select([
                    't.tgl_perawatan',
                    't.jam_rawat',
                    't.no_rawat',
                    'reg.no_rkm_medis',
                    'p.nm_pasien',
                    't.kd_dokter',
                    'd.nm_dokter',
                    'jp.nm_perawatan',
                    \Illuminate\Support\Facades\DB::raw("'Ranap' as status_lanjut"),
                    \Illuminate\Support\Facades\DB::raw("(SELECT b.nm_bangsal FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.no_rawat = t.no_rawat ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC LIMIT 1) as ruangan"),
                    \Illuminate\Support\Facades\DB::raw("'' as kd_poli"),
                    't.tarif_tindakandr',
                    't.material',
                    't.bhp',
                    't.kso',
                    't.menejemen',
                    't.biaya_rawat'
                ]);

            $union = $q1->unionAll($q2)->unionAll($q3)->unionAll($q4);
            
            $query = $connection->query()->fromSub($union, 'unioned')
                ->whereBetween('tgl_perawatan', [$from, $to]);

            if ($kdDokter !== '') {
                $query->where('kd_dokter', $kdDokter);
            }
            if ($kdPoli !== '') {
                $query->where('kd_poli', $kdPoli);
            }
            if ($search !== '') {
                $query->where(function($q) use ($search) {
                    $q->where('nm_pasien', 'like', "%{$search}%")
                      ->orWhere('no_rkm_medis', 'like', "%{$search}%")
                      ->orWhere('no_rawat', 'like', "%{$search}%")
                      ->orWhere('nm_perawatan', 'like', "%{$search}%")
                      ->orWhere('nm_dokter', 'like', "%{$search}%");
                });
            }

            // Check if user requests Excel export
            $export = $request->input('export');
            if ($export === 'excel') {
                $records = $query->orderBy('tgl_perawatan')->orderBy('jam_rawat')->get();

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Tindakan Dokter');
 
                 // Header
                 $sheet->setCellValue('A1', 'LAPORAN TINDAKAN DOKTER');
                 $sheet->mergeCells('A1:G1');
                 $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                 
                 $sheet->setCellValue('A2', 'Periode: ' . date('d-m-Y', strtotime($from)) . ' s/d ' . date('d-m-Y', strtotime($to)));
                 $sheet->mergeCells('A2:G2');
 
                 // Column headers
                 $headers = [
                     'A4' => 'No',
                     'B4' => 'Tanggal',
                     'C4' => 'No. Rawat',
                     'D4' => 'No. RM',
                     'E4' => 'Nama Pasien',
                     'F4' => 'Tindakan/Perawatan',
                     'G4' => 'Poli/Ruangan'
                 ];
 
                 foreach ($headers as $cell => $val) {
                     $sheet->setCellValue($cell, $val);
                 }
 
                 $sheet->getStyle('A4:G4')->getFont()->setBold(true);
                 $sheet->getStyle('A4:G4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
 
                 $row = 5;
                 $no = 1;
 
                 foreach ($records as $reg) {
                     $sheet->setCellValue('A' . $row, $no++);
                     $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($reg->tgl_perawatan)) . ' ' . $reg->jam_rawat);
                     $sheet->setCellValue('C' . $row, $reg->no_rawat);
                     $sheet->setCellValue('D' . $row, $reg->no_rkm_medis);
                     $sheet->setCellValue('E' . $row, $reg->nm_pasien);
                     $sheet->setCellValue('F' . $row, $reg->nm_perawatan);
                     $sheet->setCellValue('G' . $row, $reg->ruangan);
 
                     $row++;
                 }
 
                 // Auto size columns
                 foreach (range('A', 'G') as $col) {
                     $sheet->getColumnDimension($col)->setAutoSize(true);
                 }
 
                 return response()->streamDownload(function() use ($spreadsheet) {
                     $writer = new Xlsx($spreadsheet);
                     $writer->save('php://output');
                 }, 'laporan-tindakan-dokter.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]);
            }

            // Stats Rekap
            $statsQuery = clone $query;
            $statsRaw = $statsQuery->selectRaw("
                COALESCE(sum(tarif_tindakandr), 0) as total_jm,
                COALESCE(sum(material), 0) as total_sarana,
                COALESCE(sum(bhp), 0) as total_bhp,
                COALESCE(sum(kso), 0) as total_kso,
                COALESCE(sum(menejemen), 0) as total_menejemen,
                COALESCE(sum(biaya_rawat), 0) as total_biaya
            ")->first();

            if ($statsRaw) {
                $stats['total_jm'] = (double) $statsRaw->total_jm;
                $stats['total_sarana'] = (double) $statsRaw->total_sarana;
                $stats['total_bhp'] = (double) $statsRaw->total_bhp;
                $stats['total_kso'] = (double) $statsRaw->total_kso;
                $stats['total_menejemen'] = (double) $statsRaw->total_menejemen;
                $stats['total_biaya'] = (double) $statsRaw->total_biaya;
            }

            $transactions = $query->orderBy('tgl_perawatan')
                ->orderBy('jam_rawat')
                ->paginate($showAll ? 1000000 : 50)
                ->withQueryString();

        } catch (Throwable $exception) {
            $connectionError = 'Database eksternal belum dapat diakses. Periksa konfigurasi pada menu Pengaturan > Database Eksternal. Error: ' . $exception->getMessage();
        }

        return view('pages.laporan.detail-jm-dokter', [
            'title' => 'Laporan Tindakan Dokter',
            'transactions' => $transactions,
            'dokters' => $dokters,
            'polis' => $polis,
            'connectionError' => $connectionError,
            'stats' => $stats,
            'filters' => compact('from', 'to', 'kdDokter', 'kdPoli', 'search', 'showAll'),
        ]);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 50, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
