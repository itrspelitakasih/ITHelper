<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatMedicationSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class MedicationController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $definition = $this->definition($type);
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
        $rows = $this->emptyPaginator($request);
        $summary = ['all' => 0, 'pending' => 0, 'sent' => 0];
        $connectionError = null;

        try {
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $type, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'medications');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('sent_id')->where('sent_id', '<>', '')->count(),
            ];
            $query = $connection->query()->fromSub($base, 'medications');
            if ($status === 'pending') {
                $query->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''));
            } elseif ($status === 'sent') {
                $query->whereNotNull('sent_id')->where('sent_id', '<>', '');
            }
            $rows = $query->orderByDesc('event_date')->orderByDesc('event_time')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = "Database eksternal atau tabel {$definition['label']} belum dapat diakses. Periksa konfigurasi dan struktur database SIMRS.";
        }

        return view('pages.satusehat.medications.index', [
            'title' => "{$definition['label']} SATUSEHAT",
            'type' => $type,
            'definition' => $definition,
            'rows' => $rows,
            'summary' => $summary,
            'connectionError' => $connectionError,
            'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatMedicationSender $sender, string $type)
    {
        $definition = $this->definition($type);
        $data = $request->validate([
            'medications' => ['required', 'array', 'min:1', 'max:20'],
            'medications.*' => ['required', 'string', 'max:180'],
        ], ['medications.required' => "Pilih minimal satu {$definition['label']} yang akan dikirim."]);

        try {
            $results = $sender->sendMany($type, $data['medications']);

            return back()->with('success', count($results['sent'])." {$definition['label']} berhasil dikirim.")
                ->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $connection, string $type, string $from, string $to, string $search): Builder
    {
        return match ($type) {
            'medication' => $this->medicationQuery($connection, $search),
            'request', 'statement' => $this->prescriptionQuery($connection, $type, $from, $to, $search),
            'dispense' => $this->dispenseQuery($connection, $from, $to, $search),
        };
    }

    private function medicationQuery(Connection $connection, string $search): Builder
    {
        return $connection->table('satu_sehat_mapping_obat as map')
            ->join('databarang as barang', 'barang.kode_brng', '=', 'map.kode_brng')
            ->leftJoin('satu_sehat_medication as sent', 'sent.kode_brng', '=', 'map.kode_brng')
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = "%{$search}%";
                $query->where(fn (Builder $q) => $q->where('map.kode_brng', 'like', $like)->orWhere('map.obat_code', 'like', $like)
                    ->orWhere('map.obat_display', 'like', $like)->orWhere('map.form_display', 'like', $like));
            })
            ->selectRaw("map.kode_brng as item_code, map.obat_display as item_name, map.obat_code as mapping_code, map.form_display, barang.status as item_status, sent.id_medication as sent_id, '' as no_rawat, '' as no_rkm_medis, '' as nm_pasien, '' as no_resep, '' as no_racik, '' as source_status, CURDATE() as event_date, '00:00:00' as event_time, '' as quantity, '' as instruction, '' as extra");
    }

    private function prescriptionQuery(Connection $connection, string $type, string $from, string $to, string $search): Builder
    {
        $queries = [];
        foreach (['Ralan', 'Ranap'] as $status) {
            $queries[] = $this->prescriptionSource($connection, $type, false, $status, $from, $to, $search);
            $queries[] = $this->prescriptionSource($connection, $type, true, $status, $from, $to, $search);
        }
        $query = array_shift($queries);
        foreach ($queries as $part) {
            $query->unionAll($part);
        }

        return $query;
    }

    private function prescriptionSource(Connection $connection, string $type, bool $compound, string $status, string $from, string $to, string $search): Builder
    {
        $detail = $compound ? 'resep_dokter_racikan_detail' : 'resep_dokter';
        $output = "satu_sehat_medication{$type}".($compound ? '_racikan' : '');
        $date = $type === 'statement' ? 'tgl_penyerahan' : 'tgl_peresepan';
        $time = $type === 'statement' ? 'jam_penyerahan' : 'jam_peresepan';
        $query = $connection->table('reg_periksa')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('resep_obat', 'resep_obat.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('pegawai', 'pegawai.nik', '=', 'resep_obat.kd_dokter')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        if ($compound) {
            $query->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep', '=', 'resep_obat.no_resep')
                ->join($detail, function ($join) use ($detail) {
                    $join->on("{$detail}.no_resep", '=', 'resep_dokter_racikan.no_resep')
                        ->on("{$detail}.no_racik", '=', 'resep_dokter_racikan.no_racik');
                });
        } else {
            $query->join($detail, "{$detail}.no_resep", '=', 'resep_obat.no_resep');
        }
        $query->join('satu_sehat_mapping_obat as map', 'map.kode_brng', '=', "{$detail}.kode_brng")
            ->join('satu_sehat_medication as med', 'med.kode_brng', '=', 'map.kode_brng')
            ->leftJoin("{$output} as sent", function ($join) use ($detail, $compound) {
                $join->on('sent.no_resep', '=', "{$detail}.no_resep")->on('sent.kode_brng', '=', "{$detail}.kode_brng");
                if ($compound) {
                    $join->on('sent.no_racik', '=', "{$detail}.no_racik");
                }
            })
            ->where('reg_periksa.status_lanjut', $status)
            ->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($type === 'statement', fn (Builder $q) => $q->where('resep_obat.tgl_penyerahan', '<>', '0000-00-00'))
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%";
                $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('resep_obat.no_resep', 'like', $like)
                    ->orWhere('map.kode_brng', 'like', $like)->orWhere('map.obat_display', 'like', $like));
            });
        $instruction = $compound ? 'resep_dokter_racikan.aturan_pakai' : "{$detail}.aturan_pakai";
        $noRacik = $compound ? "{$detail}.no_racik" : "''";

        return $query->selectRaw("{$detail}.kode_brng as item_code, map.obat_display as item_name, map.obat_code as mapping_code, map.form_display, '' as item_status, sent.id_medication{$type} as sent_id, reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, resep_obat.no_resep, {$noRacik} as no_racik, '{$status}' as source_status, resep_obat.{$date} as event_date, resep_obat.{$time} as event_time, {$detail}.jml as quantity, {$instruction} as instruction, med.id_medication as extra");
    }

    private function dispenseQuery(Connection $connection, string $from, string $to, string $search): Builder
    {
        return $connection->table('reg_periksa')
            ->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('resep_obat', 'resep_obat.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat')
            ->join('detail_pemberian_obat as detail', function ($join) {
                $join->on('detail.no_rawat', '=', 'resep_obat.no_rawat')->on('detail.tgl_perawatan', '=', 'resep_obat.tgl_perawatan')->on('detail.jam', '=', 'resep_obat.jam');
            })
            ->join('aturan_pakai as aturan', function ($join) {
                $join->on('aturan.no_rawat', '=', 'detail.no_rawat')->on('aturan.tgl_perawatan', '=', 'detail.tgl_perawatan')
                    ->on('aturan.jam', '=', 'detail.jam')->on('aturan.kode_brng', '=', 'detail.kode_brng');
            })
            ->join('satu_sehat_mapping_obat as map', 'map.kode_brng', '=', 'detail.kode_brng')
            ->join('satu_sehat_medication as med', 'med.kode_brng', '=', 'map.kode_brng')
            ->join('satu_sehat_mapping_lokasi_depo_farmasi as lokasi', 'lokasi.kd_bangsal', '=', 'detail.kd_bangsal')
            ->leftJoin('satu_sehat_medicationdispense as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'detail.no_rawat')->on('sent.tgl_perawatan', '=', 'detail.tgl_perawatan')->on('sent.jam', '=', 'detail.jam')
                    ->on('sent.kode_brng', '=', 'detail.kode_brng')->on('sent.no_batch', '=', 'detail.no_batch')->on('sent.no_faktur', '=', 'detail.no_faktur');
            })
            ->whereIn('detail.status', ['Ralan', 'Ranap'])->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = "%{$search}%";
                $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                    ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('detail.kode_brng', 'like', $like)->orWhere('map.obat_display', 'like', $like));
            })
            ->selectRaw("detail.kode_brng as item_code, map.obat_display as item_name, map.obat_code as mapping_code, map.form_display, '' as item_status, sent.id_medicationdispanse as sent_id, reg_periksa.no_rawat, reg_periksa.no_rkm_medis, pasien.nm_pasien, resep_obat.no_resep, '' as no_racik, detail.status as source_status, detail.tgl_perawatan as event_date, detail.jam as event_time, detail.jml as quantity, aturan.aturan as instruction, CONCAT(detail.no_batch, '|', detail.no_faktur, '|', lokasi.id_lokasi_satusehat, '|', med.id_medication) as extra");
    }

    private function definition(string $type): array
    {
        $definitions = [
            'medication' => ['label' => 'Medication', 'dated' => false],
            'request' => ['label' => 'Medication Request', 'dated' => true],
            'dispense' => ['label' => 'Medication Dispense', 'dated' => true],
            'statement' => ['label' => 'Medication Statement', 'dated' => true],
        ];
        abort_unless(isset($definitions[$type]), 404);

        return $definitions[$type];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
