<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EncounterController;
use App\Http\Controllers\AuthController;
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

    Route::resource('users', UserController::class)->except('show')->middleware('permission:users.manage');
    Route::resource('roles', RoleController::class)->except('show')->middleware('permission:roles.manage');

    Route::get('/profile', fn () => view('pages.profile', ['title' => 'Profile']))->name('profile');
});
