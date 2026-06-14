<?php

namespace App\Services;

use App\Support\TtvTypes;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Throwable;

class SatuSehatDashboardStatistics
{
    public function __construct(private readonly ExternalDatabaseManager $databaseManager) {}

    public function get(string $from, string $to): array
    {
        $db = $this->databaseManager->connection();
        $definitions = collect($this->definitions());
        $items = $definitions->map(function (array $definition) use ($db, $from, $to) {
            try {
                $all = (int) $definition['source']($db, $from, $to);
                $sent = collect($definition['sent'])->sum(fn (array $output) => $this->sentCount($db, $output, $from, $to));
                return array_merge($definition, ['all' => $all, 'sent_count' => $sent, 'pending' => max(0, $all - $sent), 'available' => true]);
            } catch (Throwable) {
                return array_merge($definition, ['all' => 0, 'sent_count' => 0, 'pending' => 0, 'available' => false]);
            }
        })->values();

        return [
            'items' => $items,
            'totals' => ['all' => $items->sum('all'), 'sent' => $items->sum('sent_count'), 'pending' => $items->sum('pending')],
        ];
    }

    private function definitions(): array
    {
        $regCount = fn (string $table, string $foreign = 'no_rawat', ?string $nonBlank = null) => fn (Connection $db, string $from, string $to) => $db->table("{$table} as src")
            ->join('reg_periksa as reg', "reg.no_rawat", '=', "src.{$foreign}")->whereBetween('reg.tgl_registrasi', [$from, $to])
            ->when($nonBlank, fn (Builder $q) => $q->whereNotNull("src.{$nonBlank}")->where("src.{$nonBlank}", '<>', ''))->count();
        $orderCount = fn (string $detail, string $request) => fn (Connection $db, string $from, string $to) => $db->table("{$detail} as detail")
            ->join("{$request} as request", 'request.noorder', '=', 'detail.noorder')->whereBetween('request.tgl_permintaan', [$from, $to])->count();
        $sum = fn (array $queries) => fn (Connection $db, string $from, string $to) => collect($queries)->sum(function ($query) use ($db, $from, $to) {
            try { return $query($db, $from, $to); } catch (Throwable) { return 0; }
        });
        $out = fn (string $table, string $id, string $mode, ?string $request = null) => compact('table', 'id', 'mode', 'request');
        $ttvSources = collect(TtvTypes::all())->flatMap(fn (array $type) => [$regCount('pemeriksaan_ralan', 'no_rawat', $type['column']), $regCount('pemeriksaan_ranap', 'no_rawat', $type['column'])])->all();
        $ttvOutputs = collect(TtvTypes::all())->map(fn (array $type) => $out($type['table'], 'id_observation', 'no_rawat'))->all();

        return [
            ['label' => 'Encounter', 'path' => '/satusehat/encounters', 'permission' => 'encounters.view', 'source' => fn (Connection $db, string $from, string $to) => $db->table('reg_periksa')->where('status_bayar', 'Sudah Bayar')->whereBetween('tgl_registrasi', [$from, $to])->count(), 'sent' => [$out('satu_sehat_encounter', 'id_encounter', 'no_rawat')]],
            ['label' => 'EpisodeOfCare', 'path' => '/satusehat/episode-of-care', 'permission' => 'episode-of-care.view', 'source' => fn (Connection $db, string $from, string $to) => $db->table('diagnosa_pasien as src')->join('reg_periksa as reg', 'reg.no_rawat', '=', 'src.no_rawat')->where('src.kd_penyakit', 'like', '%O%')->whereBetween('reg.tgl_registrasi', [$from, $to])->count(), 'sent' => [$out('satu_sehat_episode_of_care', 'id_episode_of_care', 'no_rawat')]],
            ['label' => 'Observation', 'path' => '/satusehat/observations/lab-mb', 'permission' => 'observations.view', 'source' => $sum(array_merge([$orderCount('permintaan_detail_permintaan_labmb', 'permintaan_labmb'), $orderCount('permintaan_detail_permintaan_lab', 'permintaan_lab'), $orderCount('permintaan_pemeriksaan_radiologi', 'permintaan_radiologi')], $ttvSources)), 'sent' => array_merge([$out('satu_sehat_observation_lab_mb', 'id_observation', 'order', 'permintaan_labmb'), $out('satu_sehat_observation_lab', 'id_observation', 'order', 'permintaan_lab'), $out('satu_sehat_observation_radiologi', 'id_observation', 'order', 'permintaan_radiologi')], $ttvOutputs)],
            ['label' => 'Condition', 'path' => '/satusehat/conditions', 'permission' => 'conditions.view', 'source' => $regCount('diagnosa_pasien'), 'sent' => [$out('satu_sehat_condition', 'id_condition', 'no_rawat')]],
            ['label' => 'Procedure', 'path' => '/satusehat/procedures', 'permission' => 'procedures.view', 'source' => $regCount('prosedur_pasien'), 'sent' => [$out('satu_sehat_procedure', 'id_procedure', 'no_rawat')]],
            ['label' => 'CarePlan', 'path' => '/satusehat/care-plans', 'permission' => 'care-plans.view', 'source' => $sum([$regCount('pemeriksaan_ralan', 'no_rawat', 'rtl'), $regCount('pemeriksaan_ranap', 'no_rawat', 'rtl')]), 'sent' => [$out('satu_sehat_careplan', 'id_careplan', 'own_date')]],
            ['label' => 'ClinicalImpression', 'path' => '/satusehat/clinical-impressions', 'permission' => 'clinical-impressions.view', 'source' => $sum([$regCount('pemeriksaan_ralan', 'no_rawat', 'penilaian'), $regCount('pemeriksaan_ranap', 'no_rawat', 'penilaian')]), 'sent' => [$out('satu_sehat_clinicalimpression', 'id_clinicalimpression', 'own_date')]],
            ['label' => 'DiagnosticReport', 'path' => '/satusehat/diagnostic-reports/lab', 'permission' => 'diagnostic-reports.view', 'source' => $sum([$orderCount('permintaan_detail_permintaan_lab', 'permintaan_lab'), $orderCount('permintaan_pemeriksaan_radiologi', 'permintaan_radiologi')]), 'sent' => [$out('satu_sehat_diagnosticreport_lab', 'id_diagnosticreport', 'order', 'permintaan_lab'), $out('satu_sehat_diagnosticreport_radiologi', 'id_diagnosticreport', 'order', 'permintaan_radiologi')]],
            ['label' => 'Medication', 'path' => '/satusehat/medications/medication', 'permission' => 'medications.view', 'source' => $sum([$regCount('resep_obat'), $regCount('detail_pemberian_obat')]), 'sent' => [$out('satu_sehat_medicationrequest', 'id_medicationrequest', 'resep'), $out('satu_sehat_medicationrequest_racikan', 'id_medicationrequest', 'resep'), $out('satu_sehat_medicationdispense', 'id_medicationdispanse', 'no_rawat'), $out('satu_sehat_medicationstatement', 'id_medicationstatement', 'resep'), $out('satu_sehat_medicationstatement_racikan', 'id_medicationstatement', 'resep')]],
            ['label' => 'QuestionnaireResponse', 'path' => '/satusehat/questionnaires/request', 'permission' => 'questionnaires.view', 'source' => fn (Connection $db, string $from, string $to) => $db->table('telaah_farmasi as src')->join('resep_obat as resep', 'resep.no_resep', '=', 'src.no_resep')->join('reg_periksa as reg', 'reg.no_rawat', '=', 'resep.no_rawat')->whereBetween('reg.tgl_registrasi', [$from, $to])->count() * 2, 'sent' => [$out('satu_sehat_questionnairereq_pengkajian_obat', 'id_questionnaire_request', 'own_date'), $out('satu_sehat_questionnaireresponse', 'id_questionnaireresponse', 'own_date')]],
            ['label' => 'Composition', 'path' => '/satusehat/rme/rawat-inap', 'permission' => 'rme.view', 'source' => $sum([$regCount('resume_pasien_ranap'), $regCount('resume_pasien')]), 'sent' => [$out('satu_sehat_rme_rawat_inap', 'id_composition', 'no_rawat'), $out('satu_sehat_rme_rawat_jalan', 'id_composition', 'no_rawat')]],
            ['label' => 'RiskAssessment', 'path' => '/satusehat/risk-assessments/risk', 'permission' => 'risk-assessments.view', 'source' => $regCount('penilaian_awal_keperawatan_ralan'), 'sent' => [$out('satu_sehat_riskassessment', 'id_riskassessment', 'no_rawat')]],
            ['label' => 'ServiceRequest', 'path' => '/satusehat/service-requests/lab-mb', 'permission' => 'service-requests.view', 'source' => $sum([$orderCount('permintaan_detail_permintaan_labmb', 'permintaan_labmb'), $orderCount('permintaan_detail_permintaan_lab', 'permintaan_lab'), $orderCount('permintaan_pemeriksaan_radiologi', 'permintaan_radiologi')]), 'sent' => [$out('satu_sehat_servicerequest_lab_mb', 'id_servicerequest', 'order', 'permintaan_labmb'), $out('satu_sehat_servicerequest_lab', 'id_servicerequest', 'order', 'permintaan_lab'), $out('satu_sehat_servicerequest_radiologi', 'id_servicerequest', 'order', 'permintaan_radiologi')]],
            ['label' => 'Specimen', 'path' => '/satusehat/specimens/lab-mb', 'permission' => 'specimens.view', 'source' => $sum([$orderCount('permintaan_detail_permintaan_labmb', 'permintaan_labmb'), $orderCount('permintaan_detail_permintaan_lab', 'permintaan_lab'), $orderCount('permintaan_pemeriksaan_radiologi', 'permintaan_radiologi')]), 'sent' => [$out('satu_sehat_specimen_lab_mb', 'id_specimen', 'order', 'permintaan_labmb'), $out('satu_sehat_specimen_lab', 'id_specimen', 'order', 'permintaan_lab'), $out('satu_sehat_specimen_radiologi', 'id_specimen', 'order', 'permintaan_radiologi')]],
        ];
    }

    private function sentCount(Connection $db, array $output, string $from, string $to): int
    {
        try {
            $q = $db->table("{$output['table']} as sent")->whereNotNull("sent.{$output['id']}")->where("sent.{$output['id']}", '<>', '');
            match ($output['mode']) {
                'no_rawat' => $q->join('reg_periksa as reg', 'reg.no_rawat', '=', 'sent.no_rawat')->whereBetween('reg.tgl_registrasi', [$from, $to]),
                'own_date' => $q->whereBetween('sent.tgl_perawatan', [$from, $to]),
                'order' => $q->join("{$output['request']} as request", 'request.noorder', '=', 'sent.noorder')->whereBetween('request.tgl_permintaan', [$from, $to]),
                'resep' => $q->join('resep_obat as resep', 'resep.no_resep', '=', 'sent.no_resep')->join('reg_periksa as reg', 'reg.no_rawat', '=', 'resep.no_rawat')->whereBetween('reg.tgl_registrasi', [$from, $to]),
                default => null,
            };
            return $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

}
