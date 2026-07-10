<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApplicationSettingController;
use App\Http\Controllers\ExternalDatabaseSettingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TtvController;
use App\Http\Controllers\ConditionController;
use App\Http\Controllers\CarePlanController;
use App\Http\Controllers\ClinicalImpressionController;
use App\Http\Controllers\DiagnosticReportController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\ObservationController;
use App\Http\Controllers\ProcedureController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\ClinicalResourceController;
use App\Http\Controllers\EpisodeOfCareController;
use App\Http\Controllers\RawatJalanController;
use App\Http\Controllers\LaboratoriumController;

Route::middleware('guest')->group(function () {
    Route::get('/signin', [AuthController::class, 'create'])->name('login');
    Route::post('/signin', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/signout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/satusehat/encounters', [EncounterController::class, 'index'])
        ->middleware('permission:encounters.view')
        ->name('satusehat.encounters.index');
    Route::post('/satusehat/encounters/send', [EncounterController::class, 'send'])
        ->middleware('permission:encounters.send')
        ->name('satusehat.encounters.send');
    Route::get('/satusehat/episode-of-care', [EpisodeOfCareController::class, 'index'])
        ->middleware('permission:episode-of-care.view')->name('satusehat.episode-of-care.index');
    Route::post('/satusehat/episode-of-care/send', [EpisodeOfCareController::class, 'send'])
        ->middleware('permission:episode-of-care.send')->name('satusehat.episode-of-care.send');
    Route::get('/satusehat/ttv', [TtvController::class, 'index'])
        ->middleware('permission:ttv.view')
        ->name('satusehat.ttv.index');
    Route::post('/satusehat/ttv/send', [TtvController::class, 'send'])
        ->middleware('permission:ttv.send')
        ->name('satusehat.ttv.send');
    Route::get('/satusehat/conditions', [ConditionController::class, 'index'])
        ->middleware('permission:conditions.view')
        ->name('satusehat.conditions.index');
    Route::post('/satusehat/conditions/send', [ConditionController::class, 'send'])
        ->middleware('permission:conditions.send')
        ->name('satusehat.conditions.send');
    Route::get('/satusehat/care-plans', [CarePlanController::class, 'index'])
        ->middleware('permission:care-plans.view')
        ->name('satusehat.care-plans.index');
    Route::post('/satusehat/care-plans/send', [CarePlanController::class, 'send'])
        ->middleware('permission:care-plans.send')
        ->name('satusehat.care-plans.send');
    Route::get('/satusehat/clinical-impressions', [ClinicalImpressionController::class, 'index'])
        ->middleware('permission:clinical-impressions.view')
        ->name('satusehat.clinical-impressions.index');
    Route::post('/satusehat/clinical-impressions/send', [ClinicalImpressionController::class, 'send'])
        ->middleware('permission:clinical-impressions.send')
        ->name('satusehat.clinical-impressions.send');
    Route::get('/satusehat/diagnostic-reports/{type}', [DiagnosticReportController::class, 'index'])
        ->whereIn('type', ['lab', 'radiology'])->middleware('permission:diagnostic-reports.view')
        ->name('satusehat.diagnostic-reports.index');
    Route::post('/satusehat/diagnostic-reports/{type}/send', [DiagnosticReportController::class, 'send'])
        ->whereIn('type', ['lab', 'radiology'])->middleware('permission:diagnostic-reports.send')
        ->name('satusehat.diagnostic-reports.send');
    Route::get('/satusehat/medications/{type}', [MedicationController::class, 'index'])
        ->whereIn('type', ['medication', 'request', 'dispense', 'statement'])->middleware('permission:medications.view')
        ->name('satusehat.medications.index');
    Route::post('/satusehat/medications/{type}/send', [MedicationController::class, 'send'])
        ->whereIn('type', ['medication', 'request', 'dispense', 'statement'])->middleware('permission:medications.send')
        ->name('satusehat.medications.send');
    Route::get('/satusehat/observations/{type}', [ObservationController::class, 'index'])
        ->whereIn('type', ['lab-mb', 'lab-pk', 'radiology', 'ttv'])->middleware('permission:observations.view')
        ->name('satusehat.observations.index');
    Route::post('/satusehat/observations/{type}/send', [ObservationController::class, 'send'])
        ->whereIn('type', ['lab-mb', 'lab-pk', 'radiology', 'ttv'])->middleware('permission:observations.send')
        ->name('satusehat.observations.send');
    Route::get('/satusehat/procedures', [ProcedureController::class, 'index'])
        ->middleware('permission:procedures.view')->name('satusehat.procedures.index');
    Route::post('/satusehat/procedures/send', [ProcedureController::class, 'send'])
        ->middleware('permission:procedures.send')->name('satusehat.procedures.send');
    Route::get('/satusehat/questionnaires/{type}', [QuestionnaireController::class, 'index'])
        ->whereIn('type', ['request', 'response'])->middleware('permission:questionnaires.view')
        ->name('satusehat.questionnaires.index');
    Route::post('/satusehat/questionnaires/{type}/send', [QuestionnaireController::class, 'send'])
        ->whereIn('type', ['request', 'response'])->middleware('permission:questionnaires.send')
        ->name('satusehat.questionnaires.send');
    Route::get('/satusehat/{group}/{type}', [ClinicalResourceController::class, 'index'])
        ->whereIn('group', ['rme', 'risk-assessments', 'service-requests', 'specimens'])
        ->whereIn('type', ['rawat-inap', 'rawat-jalan', 'risk', 'lab-mb', 'lab-pk', 'radiology'])
        ->middleware('clinical-resource.permission:view')->name('satusehat.clinical-resources.index');
    Route::post('/satusehat/{group}/{type}/send', [ClinicalResourceController::class, 'send'])
        ->whereIn('group', ['rme', 'risk-assessments', 'service-requests', 'specimens'])
        ->whereIn('type', ['rawat-inap', 'rawat-jalan', 'risk', 'lab-mb', 'lab-pk', 'radiology'])
        ->middleware('clinical-resource.permission:send')->name('satusehat.clinical-resources.send');

    Route::get('/settings/database', [ExternalDatabaseSettingController::class, 'edit'])
        ->middleware('permission:settings.database')
        ->name('settings.database.edit');
    Route::put('/settings/database', [ExternalDatabaseSettingController::class, 'update'])
        ->middleware('permission:settings.database')
        ->name('settings.database.update');
    Route::get('/settings/app', [ApplicationSettingController::class, 'edit'])
        ->middleware('permission:settings.app')
        ->name('settings.app.edit');
    Route::put('/settings/app', [ApplicationSettingController::class, 'update'])
        ->middleware('permission:settings.app')
        ->name('settings.app.update');

    Route::resource('users', UserController::class)->except('show')->middleware('permission:users.manage');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:roles.manage');

    Route::get('/rawat-jalan/registrasi', [RawatJalanController::class, 'registrasi'])
        ->middleware('permission:rawat-jalan.registrasi.view')
        ->name('rawat-jalan.registrasi.index');
    Route::get('/rawat-jalan/igd', [RawatJalanController::class, 'igd'])
        ->middleware('permission:rawat-jalan.igd.view')
        ->name('rawat-jalan.igd.index');

    Route::get('/laboratorium/{type}', [LaboratoriumController::class, 'index'])
        ->whereIn('type', ['permintaan', 'periksa', 'simrs'])
        ->middleware('permission:laboratorium.view')
        ->name('laboratorium.index');
    Route::get('/laboratorium/permintaan/{noorder}/details', [LaboratoriumController::class, 'permintaanDetails'])
        ->middleware('permission:laboratorium.view')
        ->name('laboratorium.permintaan.details');
    Route::get('/laboratorium/periksa/details', [LaboratoriumController::class, 'periksaDetails'])
        ->middleware('permission:laboratorium.view')
        ->name('laboratorium.periksa.details');
    Route::post('/laboratorium/permintaan/{noorder}/sampel', [LaboratoriumController::class, 'updateSampel'])
        ->middleware('permission:laboratorium.view')
        ->name('laboratorium.permintaan.sampel');
    Route::post('/laboratorium/permintaan/{noorder}/hasil', [LaboratoriumController::class, 'updateHasil'])
        ->middleware('permission:laboratorium.view')
        ->name('laboratorium.permintaan.hasil');

    // LIS routes
    Route::prefix('lis')->name('lis.')->group(function () {
        Route::get('/periksa', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'index'])->name('periksa.index');
        Route::get('/periksa-simrs', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'simrs'])->name('periksa.simrs');
        Route::get('/periksa/search-pasien', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'searchPasien'])->name('periksa.search-pasien');
        Route::get('/periksa/{id}', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'show'])->name('periksa.show');
        Route::post('/periksa/{id}/update', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'update'])->name('periksa.update');
        Route::post('/periksa/{id}/selesai', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'selesai'])->name('periksa.selesai');
        Route::post('/periksa/{id}/manual', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'manual'])->name('periksa.manual');
        Route::post('/periksa/{id}/paket', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'paket'])->name('periksa.paket');
        Route::post('/periksa/{id}/formula', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'formula'])->name('periksa.formula');
        Route::get('/periksa/{id}/cetak', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'cetak'])->name('periksa.cetak');
        Route::get('/periksa/{id}/cetak-biaya', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'cetakBiaya'])->name('periksa.cetak-biaya');
        Route::get('/periksa/{id}/biaya', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'biaya'])->name('periksa.biaya');
        Route::post('/periksa/{id}/biaya', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'addBiaya'])->name('periksa.biaya.add');
        Route::post('/periksa/{id}/biaya/delete', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'deleteBiaya'])->name('periksa.biaya.delete');
        Route::post('/periksa/{id}/alat', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'alat'])->name('periksa.alat');
        Route::get('/tolis/{noorder}', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'tolis'])->name('periksa.tolis');
        Route::post('/periksa/{id}/sync-simrs', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'syncSimrs'])->name('periksa.sync-simrs');
        Route::post('/periksa/store-manual', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'storeManual'])->name('periksa.store-manual');
        Route::post('/periksa/{id}/delete', [\App\Http\Controllers\Lis\LisPeriksaController::class, 'destroy'])->name('periksa.destroy');


        // LIS raw analyzer results
        Route::get('/result', [\App\Http\Controllers\Lis\LisResultController::class, 'index'])->name('result.index');
        Route::post('/result/acc', [\App\Http\Controllers\Lis\LisResultController::class, 'acc'])->name('result.acc');
        Route::post('/result/gacc', [\App\Http\Controllers\Lis\LisResultController::class, 'gacc'])->name('result.gacc');
        Route::post('/result/nilai', [\App\Http\Controllers\Lis\LisResultController::class, 'nilai'])->name('result.nilai');
        Route::post('/result/nr', [\App\Http\Controllers\Lis\LisResultController::class, 'nr'])->name('result.nr');
        Route::post('/result/tanda', [\App\Http\Controllers\Lis\LisResultController::class, 'tanda'])->name('result.tanda');
        Route::post('/result/keterangan', [\App\Http\Controllers\Lis\LisResultController::class, 'keterangan'])->name('result.keterangan');
        Route::post('/result/delete', [\App\Http\Controllers\Lis\LisResultController::class, 'delete'])->name('result.delete');
        Route::post('/result/hapus', [\App\Http\Controllers\Lis\LisResultController::class, 'hapus'])->name('result.hapus');

        // LIS references
        Route::get('/referensi', [\App\Http\Controllers\Lis\LisReferensiController::class, 'index'])->name('referensi.index');
        Route::post('/referensi/{type}/store', [\App\Http\Controllers\Lis\LisReferensiController::class, 'store'])->name('referensi.store');
        Route::post('/referensi/{type}/update', [\App\Http\Controllers\Lis\LisReferensiController::class, 'update'])->name('referensi.update');
        Route::post('/referensi/{type}/delete', [\App\Http\Controllers\Lis\LisReferensiController::class, 'destroy'])->name('referensi.delete');
        Route::get('/referensi/paket/{id}', [\App\Http\Controllers\Lis\LisReferensiController::class, 'showPaket'])->name('referensi.paket.show');
        Route::post('/referensi/paket/{id}/add', [\App\Http\Controllers\Lis\LisReferensiController::class, 'addPaketDetail'])->name('referensi.paket.add');
        Route::post('/referensi/paket/{id}/delete', [\App\Http\Controllers\Lis\LisReferensiController::class, 'deletePaketDetail'])->name('referensi.paket.delete');
    });

    // Update Data routes
    Route::prefix('update-data')->name('update-data.')->middleware('permission:update-data.import')->group(function () {
        Route::get('/{type}', [\App\Http\Controllers\UpdateData\ImportController::class, 'index'])
            ->whereIn('type', ['ralan', 'ranap', 'lab', 'radiology'])
            ->name('import.index');
        Route::post('/{type}', [\App\Http\Controllers\UpdateData\ImportController::class, 'store'])
            ->whereIn('type', ['ralan', 'ranap', 'lab', 'radiology'])
            ->name('import.store');
        Route::get('/{type}/export', [\App\Http\Controllers\UpdateData\ImportController::class, 'export'])
            ->whereIn('type', ['ralan', 'ranap', 'lab', 'radiology'])
            ->name('import.export');
    });

    Route::get('/profile', fn () => view('pages.profile', ['title' => 'Profile']))->name('profile');
});


