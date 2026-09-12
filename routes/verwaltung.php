<?php

/*
 * Lernenden-Verwaltung für Admin und Berufsbildner.
 * Wird in web.php zweimal eingebunden (Präfix admin./berufsbildner.);
 * welche Lernenden sichtbar sind, regelt Lernender::sichtbarFuer().
 */

use App\Http\Controllers\DokumenteController;
use App\Http\Controllers\NotenImportController;
use App\Http\Controllers\Verwaltung\BetreuungController;
use App\Http\Controllers\Verwaltung\KontoController;
use App\Http\Controllers\Verwaltung\LernendeController;
use App\Http\Controllers\Verwaltung\LernendeNotenController;
use App\Http\Controllers\Verwaltung\NotenExportController;
use App\Http\Controllers\Verwaltung\NotenGesehenController;
use App\Http\Controllers\Verwaltung\PruefungenController;
use App\Http\Controllers\Verwaltung\RechnerController;
use App\Http\Controllers\Verwaltung\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/learners', [LernendeController::class, 'index'])->name('learners.index');
Route::get('/learners/create', [LernendeController::class, 'create'])->name('learners.create');
Route::post('/learners', [LernendeController::class, 'store'])->name('learners.store');
Route::get('/grades/export', [NotenExportController::class, 'alle'])->name('grades.export_all');

Route::get('/exams', [PruefungenController::class, 'index'])->name('exams.index');
Route::post('/exams', [PruefungenController::class, 'store'])->name('exams.store');
Route::put('/exams/{pruefung_id}', [PruefungenController::class, 'update'])->whereNumber('pruefung_id')->name('exams.update');
Route::delete('/exams/{pruefung_id}', [PruefungenController::class, 'destroy'])->whereNumber('pruefung_id')->name('exams.destroy');

Route::prefix('/learners/{lernender_id}')->whereNumber('lernender_id')->group(function () {
    Route::get('/', [LernendeController::class, 'show'])->name('learners.show');
    Route::get('/edit', [LernendeController::class, 'edit'])->name('learners.edit');
    Route::put('/', [LernendeController::class, 'update'])->name('learners.update');

    Route::post('/account/password', [KontoController::class, 'passwort'])->name('learners.account.password');
    Route::post('/account/active', [KontoController::class, 'aktiv'])->name('learners.account.active');

    Route::post('/supervisions', [BetreuungController::class, 'store'])->name('learners.supervision.store');
    Route::post('/supervisions/{betreuung_id}/end', [BetreuungController::class, 'beenden'])
        ->whereNumber('betreuung_id')->name('supervisions.end');

    Route::post('/tracks', [TrackController::class, 'store'])->name('learners.tracks.store');
    Route::post('/tracks/{track_id}/end', [TrackController::class, 'beenden'])
        ->whereNumber('track_id')->name('tracks.end');

    Route::get('/calculator', [RechnerController::class, 'index'])->name('learners.calculator');
    Route::post('/calculator', [RechnerController::class, 'berechnen'])->middleware('throttle:120,1')->name('learners.calculator.calculate');
    Route::get('/grades', [LernendeNotenController::class, 'index'])->name('learners.grades.index');
    Route::get('/grades/create', [LernendeNotenController::class, 'create'])->name('learners.grades.create');
    Route::post('/grades', [LernendeNotenController::class, 'store'])->name('learners.grades.store');
    Route::get('/grades/print', [NotenExportController::class, 'drucken'])->name('learners.grades.print');
    Route::get('/grades/export', [NotenExportController::class, 'lernender'])->name('learners.grades.export');
    Route::post('/grades/seen-all', [NotenGesehenController::class, 'alle'])->name('learners.grades.seen_all');
    Route::get('/grades/import', [NotenImportController::class, 'index'])->name('learners.grades.import.index');
    Route::post('/grades/import', [NotenImportController::class, 'lesen'])->middleware('throttle:30,1')->name('learners.grades.import.read');
    Route::post('/grades/import/apply', [NotenImportController::class, 'uebernehmen'])->name('learners.grades.import.apply');
    Route::post('/grades/import/discard', [NotenImportController::class, 'verwerfen'])->name('learners.grades.import.discard');
    Route::get('/grades/import/template', [NotenImportController::class, 'vorlage'])->name('learners.grades.import.template');
    Route::get('/documents', [DokumenteController::class, 'index'])->name('learners.documents.index');
    Route::post('/documents', [DokumenteController::class, 'store'])->middleware('throttle:30,1')->name('learners.documents.store');
    Route::get('/documents/{dokument_id}', [DokumenteController::class, 'show'])->whereNumber('dokument_id')->name('learners.documents.show');
    Route::delete('/documents/{dokument_id}', [DokumenteController::class, 'destroy'])->whereNumber('dokument_id')->name('learners.documents.destroy');
    Route::get('/documents/{dokument_id}/reconcile', [DokumenteController::class, 'abgleich'])->whereNumber('dokument_id')->name('learners.documents.reconcile');
    Route::post('/documents/{dokument_id}/reconcile', [DokumenteController::class, 'abgleichUebernehmen'])->whereNumber('dokument_id')->name('learners.documents.reconcile.apply');

    Route::prefix('/grades/{note_id}')->whereNumber('note_id')->group(function () {
        Route::get('/edit', [LernendeNotenController::class, 'edit'])->name('learners.grades.edit');
        Route::put('/', [LernendeNotenController::class, 'update'])->name('learners.grades.update');
        Route::delete('/', [LernendeNotenController::class, 'destroy'])->name('learners.grades.destroy');
        Route::post('/seen', [NotenGesehenController::class, 'einzeln'])->name('learners.grades.seen');
    });
});
