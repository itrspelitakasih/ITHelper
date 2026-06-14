<?php

namespace App\Services;

use App\Models\ExternalDatabaseSetting;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SatuSehatQuestionnaireSender
{
    private ?string $token = null;
    private array $fields = ['resep_identifikasi_pasien', 'resep_tepat_obat', 'resep_tepat_dosis', 'resep_tepat_cara_pemberian', 'resep_tepat_waktu_pemberian', 'resep_ada_tidak_duplikasi_obat', 'resep_interaksi_obat', 'resep_kontra_indikasi_obat', 'obat_tepat_pasien', 'obat_tepat_obat', 'obat_tepat_dosis', 'obat_tepat_cara_pemberian', 'obat_tepat_waktu_pemberian'];

    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public function sendMany(string $type, array $keys): array
    {
        if (! in_array($type, ['request', 'response'], true)) throw new RuntimeException('Jenis Questionnaire tidak valid.');
        $setting = $this->databaseManager->setting(); if (! $setting) throw new RuntimeException('Database eksternal belum dikonfigurasi.');
        $this->ensureApiConfigured($setting); $db = $this->databaseManager->connection(); $results = ['sent' => [], 'failed' => []];
        foreach (array_unique($keys) as $key) {
            try { $results['sent'][$key] = $this->sendOne($db, $setting, $type, $key); }
            catch (Throwable $exception) { $results['failed'][$key] = $exception->getMessage(); }
        }
        return $results;
    }

    private function sendOne(Connection $db, ExternalDatabaseSetting $setting, string $type, string $key): string
    {
        [$noRawat, $date, $time, $status, $noResep] = array_pad(explode('|', $key, 5), 5, '');
        if (! $noRawat || ! $date || ! $time || ! in_array($status, ['Ralan', 'Ranap'], true) || ! $noResep) throw new RuntimeException('Kunci Questionnaire tidak valid.');
        $query = $db->table('resep_obat')->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'resep_obat.no_rawat')->join('pasien', 'pasien.no_rkm_medis', '=', 'reg_periksa.no_rkm_medis')
            ->join('telaah_farmasi', 'telaah_farmasi.no_resep', '=', 'resep_obat.no_resep')->join('pegawai', 'pegawai.nik', '=', 'telaah_farmasi.nip')
            ->join('satu_sehat_encounter', 'satu_sehat_encounter.no_rawat', '=', 'reg_periksa.no_rawat');
        $output = $type === 'request' ? 'satu_sehat_questionnairereq_pengkajian_obat' : 'satu_sehat_questionnaireresponse';
        $query->leftJoin("{$output} as sent", function ($join) use ($type) {
            $join->on('sent.no_rawat', '=', 'resep_obat.no_rawat')->on('sent.tgl_perawatan', '=', 'resep_obat.tgl_perawatan')->on('sent.jam_rawat', '=', 'resep_obat.jam')->on('sent.status', '=', 'resep_obat.status');
            if ($type === 'request') $join->on('sent.no_resep', '=', 'resep_obat.no_resep');
        });
        $sentColumn = $type === 'request' ? 'sent.id_questionnaire_request' : 'sent.id_questionnaireresponse';
        $row = $query->where(['resep_obat.no_rawat' => $noRawat, 'resep_obat.tgl_perawatan' => $date, 'resep_obat.jam' => $time, 'resep_obat.status' => $status, 'resep_obat.no_resep' => $noResep])
            ->selectRaw("resep_obat.*, pasien.no_ktp, pasien.nm_pasien, pegawai.no_ktp as practitioner_nik, pegawai.nama as practitioner_name, telaah_farmasi.*, satu_sehat_encounter.id_encounter, {$sentColumn} as sent_id")->first();
        if (! $row) throw new RuntimeException('Data Questionnaire tidak ditemukan.');
        if (filled($row->sent_id)) throw new RuntimeException('Questionnaire sudah pernah terkirim.');
        foreach (['NIK pasien' => $row->no_ktp, 'NIK praktisi' => $row->practitioner_nik, 'ID Encounter' => $row->id_encounter] as $label => $value) if (blank($value)) throw new RuntimeException("{$label} belum tersedia.");
        $payload = ['resourceType' => 'QuestionnaireResponse', 'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q0007', 'status' => 'completed',
            'subject' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp), 'display' => $row->nm_pasien],
            'encounter' => ['reference' => "Encounter/{$row->id_encounter}"], 'authored' => Carbon::parse("{$date} {$time}", 'Asia/Jakarta')->toIso8601String(),
            'author' => ['reference' => 'Practitioner/'.$this->findFhirId($setting, 'Practitioner', $row->practitioner_nik), 'display' => $row->practitioner_name],
            'source' => ['reference' => 'Patient/'.$this->findFhirId($setting, 'Patient', $row->no_ktp)], 'item' => $this->items($row, $type)];
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(45)->post(rtrim($setting->satusehat_fhir_url, '/').'/QuestionnaireResponse', $payload);
        $id = $response->json('id'); if ($response->failed() || blank($id)) throw new RuntimeException($response->json('issue.0.details.text') ?? $response->json('issue.0.diagnostics') ?? 'SATUSEHAT menolak QuestionnaireResponse.');
        $where = ['no_rawat' => $noRawat, 'tgl_perawatan' => $date, 'jam_rawat' => $time, 'status' => $status];
        if ($type === 'request') {
            $where['no_resep'] = $noResep;
            $values = ['nip_petugas' => $row->nip, 'id_questionnaire_request' => $id, 'catatan_khusus' => '', 'tgl_input' => now()];
            foreach ($this->fields as $field) $values[$field] = $row->{$field};
            $db->table($output)->updateOrInsert($where, $values);
        } else {
            $db->table($output)->updateOrInsert($where, ['id_questionnaireresponse' => $id]);
        }
        return $id;
    }

    private function items(object $row, string $type): array
    {
        $labels = ['Tepat Identifikasi Pasien?', 'Tepat Obat?', 'Tepat Dosis?', 'Tepat Cara Pemberian?', 'Tepat Waktu Pemberian?', 'Ada Duplikasi Obat?', 'Interaksi Obat?', 'Kontra Indikasi Obat?', 'Tepat Pasien?', 'Tepat Obat?', 'Tepat Dosis?', 'Tepat Cara Pemberian?', 'Tepat Waktu Pemberian?'];
        $groups = [['1', $type === 'request' ? 'Persyaratan Administrasi / Farmasetik' : 'Telaah Resep', 0, 8], ['2', $type === 'request' ? 'Persyaratan Klinis' : 'Telaah Obat', 8, 13]];
        $items = [];
        foreach ($groups as [$id, $text, $start, $end]) {
            $children = [];
            for ($i = $start; $i < $end; $i++) {
                $yes = strcasecmp((string) $row->{$this->fields[$i]}, 'Ya') === 0;
                $answer = $type === 'request'
                    ? ['valueCoding' => ['system' => 'http://terminology.kemkes.go.id/CodeSystem/clinical-term', 'code' => $yes ? 'OV000052' : 'OV000053', 'display' => $yes ? 'Sesuai' : 'Tidak Sesuai']]
                    : ['valueBoolean' => $yes];
                $children[] = ['linkId' => "{$id}.".($i - $start + 1), 'text' => $labels[$i], 'answer' => [$answer]];
            }
            $items[] = ['linkId' => $id, 'text' => $text, 'item' => $children];
        }
        return $items;
    }

    private function findFhirId(ExternalDatabaseSetting $setting, string $resource, string $nik): string
    {
        $response = Http::acceptJson()->withToken($this->token($setting))->timeout(30)->get(rtrim($setting->satusehat_fhir_url, '/')."/{$resource}", ['identifier' => "https://fhir.kemkes.go.id/id/nik|{$nik}"]);
        $id = $response->json('entry.0.resource.id'); if ($response->failed() || blank($id)) throw new RuntimeException("{$resource} dengan NIK {$nik} tidak ditemukan di SATUSEHAT."); return $id;
    }
    private function token(ExternalDatabaseSetting $setting): string
    {
        if ($this->token) return $this->token; $response = Http::asForm()->acceptJson()->timeout(30)->post(rtrim($setting->satusehat_auth_url, '/').'/accesstoken?grant_type=client_credentials', ['client_id' => $setting->satusehat_client_id, 'client_secret' => $setting->satusehat_client_secret]);
        $this->token = $response->json('access_token'); if ($response->failed() || blank($this->token)) throw new RuntimeException('Autentikasi SATUSEHAT gagal.'); return $this->token;
    }
    private function ensureApiConfigured(ExternalDatabaseSetting $setting): void
    {
        foreach (['Client ID' => $setting->satusehat_client_id, 'Client Secret' => $setting->satusehat_client_secret, 'URL Auth' => $setting->satusehat_auth_url, 'URL FHIR' => $setting->satusehat_fhir_url] as $label => $value) if (blank($value)) throw new RuntimeException("Pengaturan SATUSEHAT {$label} belum diisi.");
    }
}
