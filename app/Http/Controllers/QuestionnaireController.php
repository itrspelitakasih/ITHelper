<?php

namespace App\Http\Controllers;

use App\Services\ExternalDatabaseManager;
use App\Services\SatuSehatQuestionnaireSender;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

class QuestionnaireController extends Controller
{
    public function index(Request $request, ExternalDatabaseManager $databaseManager, string $type)
    {
        $definition = $this->definition($type);
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
            $connection = $databaseManager->connection();
            $base = $this->baseQuery($connection, $type, $from, $to, $search);
            $summaryQuery = $connection->query()->fromSub(clone $base, 'questionnaires');
            $summary = [
                'all' => (clone $summaryQuery)->count(),
                'pending' => (clone $summaryQuery)->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''))->count(),
                'sent' => (clone $summaryQuery)->whereNotNull('sent_id')->where('sent_id', '<>', '')->count(),
            ];
            $query = $connection->query()->fromSub($base, 'questionnaires');
            if ($status === 'pending') $query->where(fn (Builder $q) => $q->whereNull('sent_id')->orWhere('sent_id', ''));
            elseif ($status === 'sent') $query->whereNotNull('sent_id')->where('sent_id', '<>', '');
            $rows = $query->orderByDesc('event_date')->orderByDesc('event_time')->paginate(20)->withQueryString();
        } catch (Throwable $exception) {
            $connectionError = "Database eksternal atau tabel {$definition['label']} belum dapat diakses. Periksa konfigurasi dan struktur database SIMRS.";
        }
        return view('pages.satusehat.questionnaires.index', compact('type', 'definition', 'rows', 'summary', 'connectionError') + [
            'title' => "{$definition['label']} SATUSEHAT", 'filters' => compact('status', 'from', 'to', 'search'),
        ]);
    }

    public function send(Request $request, SatuSehatQuestionnaireSender $sender, string $type)
    {
        $definition = $this->definition($type);
        $data = $request->validate(['questionnaires' => ['required', 'array', 'min:1', 'max:20'], 'questionnaires.*' => ['required', 'string', 'max:180']],
            ['questionnaires.required' => "Pilih minimal satu {$definition['label']} yang akan dikirim."]);
        try {
            $results = $sender->sendMany($type, $data['questionnaires']);
            return back()->with('success', count($results['sent'])." {$definition['label']} berhasil dikirim.")->with('send_failures', $results['failed']);
        } catch (Throwable $exception) {
            return back()->withErrors(['send' => $exception->getMessage()]);
        }
    }

    private function baseQuery(Connection $db, string $type, string $from, string $to, string $search): Builder
    {
        $query = $db->table('reg_periksa')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('resep_obat', 'resep_obat.no_rawat', '=', 'reg_periksa.no_rawat')->join('telaah_farmasi', 'telaah_farmasi.no_resep', '=', 'resep_obat.no_resep')
            ->join('pegawai', 'pegawai.nik', '=', 'telaah_farmasi.nip')->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        if ($type === 'request') {
            $query->leftJoin('satu_sehat_questionnairereq_pengkajian_obat as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'resep_obat.no_rawat')->on('sent.tgl_perawatan', '=', 'resep_obat.tgl_perawatan')
                    ->on('sent.jam_rawat', '=', 'resep_obat.jam')->on('sent.status', '=', 'resep_obat.status')->on('sent.no_resep', '=', 'resep_obat.no_resep');
            });
            $sent = 'sent.id_questionnaire_request';
        } else {
            $query->leftJoin('satu_sehat_questionnaireresponse as sent', function ($join) {
                $join->on('sent.no_rawat', '=', 'resep_obat.no_rawat')->on('sent.tgl_perawatan', '=', 'resep_obat.tgl_perawatan')
                    ->on('sent.jam_rawat', '=', 'resep_obat.jam')->on('sent.status', '=', 'resep_obat.status');
            });
            $sent = 'sent.id_questionnaireresponse';
        }
        return $query->whereBetween('reg_periksa.tgl_registrasi', [$from, $to])->when($search !== '', function (Builder $q) use ($search) {
            $like = "%{$search}%"; $q->where(fn (Builder $q) => $q->where('reg_periksa.no_rawat', 'like', $like)->orWhere('reg_periksa.no_rkm_medis', 'like', $like)
                ->orWhere('pasien.nm_pasien', 'like', $like)->orWhere('pasien.no_ktp', 'like', $like)->orWhere('resep_obat.no_resep', 'like', $like)->orWhere('pegawai.nama', 'like', $like));
        })->selectRaw("reg_periksa.no_rawat, reg_periksa.no_rkm_medis, reg_periksa.stts, pasien.nm_pasien, pasien.no_ktp, resep_obat.no_resep, resep_obat.tgl_perawatan as event_date, resep_obat.jam as event_time, resep_obat.status as source_status, pegawai.nama as practitioner_name, pegawai.no_ktp as practitioner_nik, satu_sehat_encounter.id_encounter, {$sent} as sent_id, telaah_farmasi.resep_identifikasi_pasien, telaah_farmasi.resep_tepat_obat, telaah_farmasi.resep_tepat_dosis, telaah_farmasi.resep_tepat_cara_pemberian, telaah_farmasi.resep_tepat_waktu_pemberian, telaah_farmasi.resep_ada_tidak_duplikasi_obat, telaah_farmasi.resep_interaksi_obat, telaah_farmasi.resep_kontra_indikasi_obat, telaah_farmasi.obat_tepat_pasien, telaah_farmasi.obat_tepat_obat, telaah_farmasi.obat_tepat_dosis, telaah_farmasi.obat_tepat_cara_pemberian, telaah_farmasi.obat_tepat_waktu_pemberian");
    }

    private function definition(string $type): array
    {
        $definitions = ['request' => ['label' => 'Questionnaire Request'], 'response' => ['label' => 'Questionnaire Response']];
        abort_unless(isset($definitions[$type]), 404); return $definitions[$type];
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
