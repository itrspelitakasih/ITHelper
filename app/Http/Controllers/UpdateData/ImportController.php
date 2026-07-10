<?php

namespace App\Http\Controllers\UpdateData;

use App\Http\Controllers\Controller;
use App\Services\ExternalDatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportController extends Controller
{
    private $mappings = [
        'ralan' => [
            'table' => 'jns_perawatan',
            'title' => 'Tarif Rawat Jalan',
            'columns' => ['kd_jenis_prw', 'nm_perawatan', 'kd_kategori', 'material', 'bhp', 'tarif_tindakandr', 'tarif_tindakanpr', 'kso', 'menejemen', 'total_byrdr', 'total_byrpr', 'total_byrdrpr', 'kd_pj', 'kd_poli', 'status']
        ],
        'ranap' => [
            'table' => 'jns_perawatan_inap',
            'title' => 'Tarif Rawat Inap',
            'columns' => ['kd_jenis_prw', 'nm_perawatan', 'kd_kategori', 'material', 'bhp', 'tarif_tindakandr', 'tarif_tindakanpr', 'kso', 'menejemen', 'total_byrdr', 'total_byrpr', 'total_byrdrpr', 'kd_pj', 'kd_bangsal', 'status', 'kelas']
        ],
        'lab' => [
            'table' => 'jns_perawatan_lab',
            'title' => 'Tarif Laboratorium',
            'columns' => ['kd_jenis_prw', 'nm_perawatan', 'bagian_rs', 'bhp', 'tarif_perujuk', 'tarif_tindakan_dokter', 'tarif_tindakan_petugas', 'kso', 'menejemen', 'total_byr', 'kd_pj', 'status', 'kelas', 'kategori']
        ],
        'radiology' => [
            'table' => 'jns_perawatan_radiologi',
            'title' => 'Tarif Radiologi',
            'columns' => ['kd_jenis_prw', 'nm_perawatan', 'bagian_rs', 'bhp', 'tarif_perujuk', 'tarif_tindakan_dokter', 'tarif_tindakan_petugas', 'kso', 'menejemen', 'total_byr', 'kd_pj', 'status', 'kelas']
        ],
    ];

    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type = 'ralan')
    {
        if (!array_key_exists($type, $this->mappings)) {
            $type = 'ralan';
        }

        $mapping = $this->mappings[$type];
        $tableName = $mapping['table'];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kd_pj' => ['nullable', 'string', 'max:50'],
            'kelas' => ['nullable', 'string', 'max:50'],
        ]);

        $search = trim($filters['search'] ?? '');
        $kd_pj = trim($filters['kd_pj'] ?? '');
        $kelas = trim($filters['kelas'] ?? '');

        $records = collect([]);
        $penjabs = collect([]);
        $kelases = collect([]);
        $connectionError = null;

        try {
            $conn = $databaseManager->connection();

            // Fetch penjabs (Cara Bayar) for filter
            $penjabs = $conn->table('penjab')->orderBy('png_jawab')->get();

            // Fetch distinct kelas if column exists in table
            $hasKelas = in_array('kelas', $mapping['columns']);
            if ($hasKelas) {
                $kelases = $conn->table($tableName)
                    ->whereNotNull('kelas')
                    ->where('kelas', '!=', '')
                    ->distinct()
                    ->pluck('kelas');
            }

            // Build query
            $query = $conn->table($tableName);

            // Join relation for penjab (cara bayar)
            $query->leftJoin('penjab', "{$tableName}.kd_pj", '=', 'penjab.kd_pj');

            if ($type === 'ralan') {
                $query->leftJoin('poliklinik', 'jns_perawatan.kd_poli', '=', 'poliklinik.kd_poli');
            } elseif ($type === 'ranap') {
                $query->leftJoin('bangsal', 'jns_perawatan_inap.kd_bangsal', '=', 'bangsal.kd_bangsal');
            }

            // Apply filters
            if ($search !== '') {
                $like = "%{$search}%";
                $query->where(function ($q) use ($tableName, $like, $type) {
                    $q->where("{$tableName}.kd_jenis_prw", 'like', $like)
                      ->orWhere("{$tableName}.nm_perawatan", 'like', $like);
                    
                    if ($type === 'ralan') {
                        $q->orWhere('poliklinik.nm_poli', 'like', $like);
                    } elseif ($type === 'ranap') {
                        $q->orWhere('bangsal.nm_bangsal', 'like', $like);
                    }
                });
            }

            if ($kd_pj !== '') {
                $query->where("{$tableName}.kd_pj", $kd_pj);
            }

            if ($hasKelas && $kelas !== '') {
                $query->where("{$tableName}.kelas", $kelas);
            }

            // Only show active records (status = '1')
            $query->where("{$tableName}.status", '1');

            // Select matching table schema columns + png_jawab name + relation names
            $selects = [];
            foreach ($mapping['columns'] as $col) {
                $selects[] = "{$tableName}.{$col}";
            }
            $selects[] = 'penjab.png_jawab';
            if ($type === 'ralan') {
                $selects[] = 'poliklinik.nm_poli';
            } elseif ($type === 'ranap') {
                $selects[] = 'bangsal.nm_bangsal';
            }

            $records = $query->select($selects)
                ->orderBy("{$tableName}.kd_jenis_prw")
                ->paginate(30)
                ->onEachSide(1)
                ->withQueryString();

        } catch (Throwable $e) {
            $connectionError = 'Database eksternal belum dapat diakses: ' . $e->getMessage();
        }

        return view('pages.update-data.import.index', [
            'title' => 'Import Database',
            'type' => $type,
            'mapping' => $mapping,
            'mappings' => $this->mappings,
            'records' => $records,
            'penjabs' => $penjabs,
            'kelases' => $kelases,
            'filters' => [
                'search' => $search,
                'kd_pj' => $kd_pj,
                'kelas' => $kelas,
            ],
            'connectionError' => $connectionError,
        ]);
    }

    public function store(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $request->validate([
            'file' => ['required', 'file'],
            'skip_header' => ['nullable', 'boolean'],
            'on_duplicate_update' => ['nullable', 'boolean'],
        ]);

        if (!array_key_exists($type, $this->mappings)) {
            $type = 'ralan';
        }

        $skipHeaderInput = $request->boolean('skip_header', true);
        $onDuplicateUpdate = $request->boolean('on_duplicate_update', true);

        $mapping = $this->mappings[$type];
        $tableName = $mapping['table'];
        $columns = $mapping['columns'];

        $file = $request->file('file');
        $path = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            return back()->with('error', 'Format file tidak didukung. Unggah file berformat .csv, .txt, .xlsx, atau .xls.');
        }

        try {
            $rows = [];
            $lineCount = 0;
            $headerSkipped = false;

            if (in_array($extension, ['xlsx', 'xls'])) {
                // Read Excel via PhpSpreadsheet
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                
                foreach ($sheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    
                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $data[] = trim($cell->getValue() ?? '');
                    }

                    // Filter out rows where all columns are empty
                    $allEmpty = true;
                    foreach ($data as $val) {
                        if ($val !== '') {
                            $allEmpty = false;
                            break;
                        }
                    }
                    if ($allEmpty) {
                        continue;
                    }

                    $lineCount++;

                    // Auto-detect header: if the first column is exactly "kd_jenis_prw", we skip this row
                    if ($lineCount === 1) {
                        if (strtolower($data[0]) === 'kd_jenis_prw') {
                            $headerSkipped = true;
                            continue;
                        }
                        if ($skipHeaderInput && !$headerSkipped) {
                            $headerSkipped = true;
                            continue;
                        }
                    }

                    // Pad or truncate row data to match table columns count
                    $columnCount = count($columns);
                    if (count($data) < $columnCount) {
                        $data = array_pad($data, $columnCount, '');
                    } elseif (count($data) > $columnCount) {
                        $data = array_slice($data, 0, $columnCount);
                    }

                    $rows[] = $data;
                }
            } else {
                // Read CSV/TXT
                $handle = fopen($path, 'r');
                if (!$handle) {
                    throw new \Exception("Gagal membuka file CSV.");
                }

                while (($data = fgetcsv($handle, 10000, ",")) !== false) {
                    $lineCount++;
                    
                    // Trim all inputs
                    $data = array_map('trim', $data);

                    // Auto-detect header: if the first column is exactly "kd_jenis_prw", we skip this row
                    if ($lineCount === 1) {
                        if (strtolower($data[0]) === 'kd_jenis_prw') {
                            $headerSkipped = true;
                            continue;
                        }
                        if ($skipHeaderInput && !$headerSkipped) {
                            $headerSkipped = true;
                            continue;
                        }
                    }

                    // Skip empty rows
                    if (empty($data) || (count($data) === 1 && $data[0] === '')) {
                        continue;
                    }

                    // Pad or truncate row data to match table columns count
                    $columnCount = count($columns);
                    if (count($data) < $columnCount) {
                        $data = array_pad($data, $columnCount, '');
                    } elseif (count($data) > $columnCount) {
                        $data = array_slice($data, 0, $columnCount);
                    }

                    $rows[] = $data;
                }
                fclose($handle);
            }

            if (empty($rows)) {
                return back()->with('error', 'Tidak ada data valid yang ditemukan untuk diimpor.');
            }

            $conn = $databaseManager->connection();

            // Perform DB upsert or insert in chunk transaction
            $conn->transaction(function () use ($conn, $tableName, $columns, $rows, $onDuplicateUpdate) {
                $columnsCsv = implode(', ', $columns);
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                if ($onDuplicateUpdate) {
                    $updateParts = [];
                    foreach ($columns as $column) {
                        if ($column !== 'kd_jenis_prw') {
                            $updateParts[] = "{$column} = VALUES({$column})";
                        }
                    }
                    $updateQuery = implode(', ', $updateParts);
                    $sql = "INSERT INTO {$tableName} ({$columnsCsv}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateQuery}";
                } else {
                    $sql = "INSERT INTO {$tableName} ({$columnsCsv}) VALUES ({$placeholders})";
                }

                // Execute inserts/updates in chunks of 500 rows
                foreach (array_chunk($rows, 500) as $chunk) {
                    foreach ($chunk as $row) {
                        $conn->statement($sql, $row);
                    }
                }
            });

            return back()->with('success', 'Berhasil mengimpor ' . count($rows) . ' data ke tabel ' . $tableName . '.');

        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengimpor data: ' . $e->getMessage());
        }
    }

    public function export(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        if (!array_key_exists($type, $this->mappings)) {
            $type = 'ralan';
        }

        $mapping = $this->mappings[$type];
        $tableName = $mapping['table'];
        $columns = $mapping['columns'];

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kd_pj' => ['nullable', 'string', 'max:50'],
            'kelas' => ['nullable', 'string', 'max:50'],
        ]);

        $search = trim($filters['search'] ?? '');
        $kd_pj = trim($filters['kd_pj'] ?? '');
        $kelas = trim($filters['kelas'] ?? '');

        try {
            $conn = $databaseManager->connection();
            $query = $conn->table($tableName);

            // Apply same filters as view
            if ($search !== '') {
                $like = "%{$search}%";
                $query->where(function ($q) use ($tableName, $like) {
                    $q->where("{$tableName}.kd_jenis_prw", 'like', $like)
                      ->orWhere("{$tableName}.nm_perawatan", 'like', $like);
                });
            }

            if ($kd_pj !== '') {
                $query->where("{$tableName}.kd_pj", $kd_pj);
            }

            if (in_array('kelas', $columns) && $kelas !== '') {
                $query->where("{$tableName}.kelas", $kelas);
            }

            // Only export active records (status = '1')
            $query->where("{$tableName}.status", '1');

            $filename = "backup_{$tableName}_" . date('Ymd_His') . ".csv";
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0'
            ];

            $callback = function() use ($query, $columns) {
                $file = fopen('php://output', 'w');
                
                // Write CSV headers (column names)
                fputcsv($file, $columns);
                
                // Stream results in chunks
                $query->orderBy('kd_jenis_prw')->chunk(1000, function($rows) use ($file, $columns) {
                    foreach ($rows as $row) {
                        $data = [];
                        foreach ($columns as $col) {
                            $data[] = $row->{$col};
                        }
                        fputcsv($file, $data);
                    }
                });

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membackup/mengekspor data: ' . $e->getMessage());
        }
    }
}
