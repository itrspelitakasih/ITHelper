@props(['resource'])

@php
    $tips = [
        'encounter' => ['order' => 'Patient & Practitioner SATUSEHAT > Mapping Lokasi > Encounter', 'needs' => 'NIK pasien, NIK dokter, poli dengan mapping Location SATUSEHAT, dan kunjungan sudah bayar.'],
        'episode-of-care' => ['order' => 'Encounter > Episode of Care', 'needs' => 'NIK pasien, ID Encounter, diagnosis ANC ICD-10 kelompok O, serta tanggal pulang/perawatan.'],
        'ttv' => ['order' => 'Encounter > Observation TTV', 'needs' => 'NIK pasien, NIK petugas, ID Encounter, dan nilai TTV yang valid.'],
        'condition' => ['order' => 'Encounter > Condition', 'needs' => 'NIK pasien, ID Encounter, dan diagnosis pasien beserta kode ICD-10.'],
        'procedure' => ['order' => 'Encounter > Procedure', 'needs' => 'NIK pasien, ID Encounter, dan prosedur pasien beserta kode ICD-9.'],
        'care-plan' => ['order' => 'Encounter > Care Plan', 'needs' => 'NIK pasien, NIK praktisi, ID Encounter, serta rencana tindak lanjut (RTL).'],
        'clinical-impression' => ['order' => 'Encounter > Condition > Clinical Impression', 'needs' => 'NIK pasien, NIK praktisi, ID Encounter, Condition terkirim, dan data penilaian klinis.'],
        'diagnostic-report' => ['order' => 'Encounter > Service Request > Specimen > Observation > Diagnostic Report', 'needs' => 'NIK pasien/dokter, mapping pemeriksaan, hasil dan kesan pemeriksaan, serta seluruh ID resource sebelumnya.'],
        'medication' => ['order' => 'Mapping Obat > Medication', 'needs' => 'Mapping kode, bentuk, rute, satuan obat, dan status obat aktif.'],
        'medication-request' => ['order' => 'Encounter > Medication > Medication Request', 'needs' => 'NIK pasien/dokter, ID Encounter, Medication terkirim, resep, jumlah, dan aturan pakai.'],
        'medication-dispense' => ['order' => 'Encounter > Medication > Medication Dispense', 'needs' => 'NIK pasien/dokter, ID Encounter, ID Medication, mapping lokasi depo, batch/faktur, jumlah, dan aturan pakai.'],
        'medication-statement' => ['order' => 'Encounter > Medication > Medication Statement', 'needs' => 'NIK pasien, ID Encounter, Medication terkirim, resep sudah diserahkan, dan aturan pakai.'],
        'questionnaire' => ['order' => 'Encounter > Questionnaire Response', 'needs' => 'NIK pasien, NIK apoteker, ID Encounter, resep, dan seluruh isian telaah farmasi.'],
        'rme' => ['order' => 'Encounter > Condition & Procedure > Rekam Medis / Composition', 'needs' => 'NIK pasien/dokter, ID Encounter, resume medis lengkap, serta Condition dan Procedure yang relevan sudah terkirim.'],
        'risk-assessment' => ['order' => 'Encounter > Risk Assessment', 'needs' => 'NIK pasien/dokter, ID Encounter, dan hasil penilaian risiko jatuh.'],
        'service-request' => ['order' => 'Encounter > Service Request', 'needs' => 'NIK pasien/dokter, ID Encounter, mapping pemeriksaan, no. order, dan diagnosis klinis.'],
        'specimen' => ['order' => 'Service Request > Specimen', 'needs' => 'NIK pasien, Service Request terkirim, mapping jenis sampel, serta tanggal dan jam pengambilan sampel.'],
        'observation' => ['order' => 'Encounter > Service Request > Specimen > Observation', 'needs' => 'NIK pasien/praktisi, ID Encounter, Specimen terkirim, mapping pemeriksaan, dan hasil pemeriksaan.'],
    ];
    $tip = $tips[$resource] ?? ['order' => 'Lengkapi resource prasyarat sebelum mengirim.', 'needs' => 'Pastikan NIK, mapping, dan ID SATUSEHAT yang dibutuhkan telah tersedia.'];
@endphp

<div class="mb-4 rounded-xl border border-brand-200 bg-brand-50/60 p-4 dark:border-brand-500/30 dark:bg-brand-500/10">
    <p class="text-sm font-semibold text-brand-700 dark:text-brand-300">Petunjuk sebelum mengirim</p>
    <div class="mt-2 grid gap-2 text-xs text-gray-600 dark:text-gray-300 lg:grid-cols-2">
        <p><span class="font-semibold text-gray-800 dark:text-white/90">Urutan:</span> {{ $tip['order'] }}</p>
        <p><span class="font-semibold text-gray-800 dark:text-white/90">Wajib dilengkapi:</span> {{ $tip['needs'] }}</p>
    </div>
</div>
