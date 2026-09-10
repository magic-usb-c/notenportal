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
use App\Http\Controllers\Verwaltung\RechnerController;
use App\Http\Controllers\Verwaltung\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/lernende', [LernendeController::class, 'index'])->name('lernende.index');
Route::get('/lernende/create', [LernendeController::class, 'create'])->name('lernende.create');
Route::post('/lernende', [LernendeController::class, 'store'])->name('lernende.store');
Route::get('/noten/export', [NotenExportController::class, 'alle'])->name('noten.export_alle');

Route::prefix('/lernende/{lernender_id}')->whereNumber('lernender_id')->group(function () {
    Route::get('/', [LernendeController::class, 'show'])->name('lernende.show');
    Route::get('/edit', [LernendeController::class, 'edit'])->name('lernende.edit');
    Route::put('/', [LernendeController::class, 'update'])->name('lernende.update');

    Route::post('/konto/passwort', [KontoController::class, 'passwort'])->name('lernende.konto.passwort');
    Route::post('/konto/aktiv', [KontoController::class, 'aktiv'])->name('lernende.konto.aktiv');

    Route::post('/betreuungen', [BetreuungController::class, 'store'])->name('lernende.betreuung.store');
    Route::post('/betreuungen/{betreuung_id}/beenden', [BetreuungController::class, 'beenden'])
        ->whereNumber('betreuung_id')->name('betreuungen.beenden');

    Route::post('/tracks', [TrackController::class, 'store'])->name('lernende.tracks.store');
    Route::post('/tracks/{track_id}/beenden', [TrackController::class, 'beenden'])
        ->whereNumber('track_id')->name('tracks.beenden');

    Route::get('/rechner', [RechnerController::class, 'index'])->name('lernende.rechner');
    Route::post('/rechner', [RechnerController::class, 'berechnen'])->middleware('throttle:120,1')->name('lernende.rechner.berechnen');
    Route::get('/noten', [LernendeNotenController::class, 'index'])->name('lernende.noten.index');
    Route::get('/noten/create', [LernendeNotenController::class, 'create'])->name('lernende.noten.create');
    Route::post('/noten', [LernendeNotenController::class, 'store'])->name('lernende.noten.store');
    Route::get('/noten/drucken', [NotenExportController::class, 'drucken'])->name('lernende.noten.drucken');
    Route::get('/noten/export', [NotenExportController::class, 'lernender'])->name('lernende.noten.export');
    Route::post('/noten/alle-gesehen', [NotenGesehenController::class, 'alle'])->name('lernende.noten.alle_gesehen');
    Route::get('/noten/import', [NotenImportController::class, 'index'])->name('lernende.noten.import.index');
    Route::post('/noten/import', [NotenImportController::class, 'lesen'])->middleware('throttle:30,1')->name('lernende.noten.import.lesen');
    Route::post('/noten/import/uebernehmen', [NotenImportController::class, 'uebernehmen'])->name('lernende.noten.import.uebernehmen');
    Route::post('/noten/import/verwerfen', [NotenImportController::class, 'verwerfen'])->name('lernende.noten.import.verwerfen');
    Route::get('/noten/import/vorlage', [NotenImportController::class, 'vorlage'])->name('lernende.noten.import.vorlage');
    Route::get('/dokumente', [DokumenteController::class, 'index'])->name('lernende.dokumente.index');
    Route::post('/dokumente', [DokumenteController::class, 'store'])->middleware('throttle:30,1')->name('lernende.dokumente.store');
    Route::get('/dokumente/{dokument_id}', [DokumenteController::class, 'show'])->whereNumber('dokument_id')->name('lernende.dokumente.show');
    Route::delete('/dokumente/{dokument_id}', [DokumenteController::class, 'destroy'])->whereNumber('dokument_id')->name('lernende.dokumente.destroy');
    Route::get('/dokumente/{dokument_id}/abgleich', [DokumenteController::class, 'abgleich'])->whereNumber('dokument_id')->name('lernende.dokumente.abgleich');
    Route::post('/dokumente/{dokument_id}/abgleich', [DokumenteController::class, 'abgleichUebernehmen'])->whereNumber('dokument_id')->name('lernende.dokumente.abgleich.uebernehmen');

    Route::prefix('/noten/{note_id}')->whereNumber('note_id')->group(function () {
        Route::get('/edit', [LernendeNotenController::class, 'edit'])->name('lernende.noten.edit');
        Route::put('/', [LernendeNotenController::class, 'update'])->name('lernende.noten.update');
        Route::delete('/', [LernendeNotenController::class, 'destroy'])->name('lernende.noten.destroy');
        Route::post('/gesehen', [NotenGesehenController::class, 'einzeln'])->name('lernende.noten.gesehen');
    });
});
