<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\KommentarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Lernender\NotenController as LernenderNotenController;
use App\Http\Controllers\Berufsbildner\NotenController as BerufsbildnerNotenController;
use App\Http\Controllers\Berufsbildner\LernendeController as BerufsbildnerLernendeController;
use App\Http\Controllers\Admin\BenutzerController as AdminBenutzerController;
use App\Http\Controllers\Admin\BerufsbildnerController as AdminBerufsbildnerController;
use App\Http\Controllers\Admin\StammdatenLehrberufeController;
use App\Http\Controllers\Admin\StammdatenModuleController;
use App\Http\Controllers\Admin\StammdatenFaecherController;
use App\Http\Controllers\Admin\StammdatenSemesterController;
use App\Http\Controllers\Admin\StammdatenKategorieController;
use App\Http\Controllers\Admin\LernendeController as AdminLernendeController;
use App\Http\Controllers\Admin\NotenController as AdminNotenController;
use App\Http\Controllers\Admin\BerichtController as AdminBerichtController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Register-Route ist in routes/auth.php als 'register' benannt definiert

Route::get('/dashboard', function (Request $request) {
    $u = $request->user();

    if ($u && $u->hasRole('Admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($u && $u->hasRole('Berufsbildner')) {
        return redirect()->route('berufsbildner.dashboard');
    }

    return redirect()->route('lernender.dashboard');
})->middleware(['auth'])->name('dashboard');

/**
 * Rollen-Dashboards mit Datenbeschaffung via DashboardController
 */
Route::get('/lernender', [DashboardController::class, 'lernender'])
    ->middleware(['auth', 'role:Lernender'])
    ->name('lernender.dashboard');

Route::get('/berufsbildner', [DashboardController::class, 'berufsbildner'])
    ->middleware(['auth', 'role:Berufsbildner'])
    ->name('berufsbildner.dashboard');

Route::get('/admin', [DashboardController::class, 'admin'])
    ->middleware(['auth', 'role:Admin'])
    ->name('admin.dashboard');

/**
 * Lernender: eigene Noten CRUD (URL bleibt /noten, aber Route-Namen sind jetzt lernender.noten.*)
 */
Route::middleware(['auth', 'role:Lernender'])
    ->prefix('noten')
    ->name('lernender.noten.')
    ->group(function () {
        Route::get('/', [LernenderNotenController::class, 'index'])->name('index');
        Route::get('/drucken', [LernenderNotenController::class, 'drucken'])->name('drucken');
        Route::get('/export', [LernenderNotenController::class, 'export'])->name('export');
        Route::get('/rechner', [LernenderNotenController::class, 'rechner'])->name('rechner');
        Route::get('/create', [LernenderNotenController::class, 'create'])->name('create');
        Route::post('/', [LernenderNotenController::class, 'store'])->name('store');

        Route::get('/{note_id}/edit', [LernenderNotenController::class, 'edit'])->name('edit');
        Route::put('/{note_id}', [LernenderNotenController::class, 'update'])->name('update');
        Route::delete('/{note_id}', [LernenderNotenController::class, 'destroy'])->name('destroy');

        // AJAX: Note als gelesen markieren (beim Öffnen des Detail-Accordions)
        Route::post('/{note_id}/gesehen', [LernenderNotenController::class, 'markGesehen'])->name('gesehen.mark');

        // AJAX: Notiz/Titel einer Note inline bearbeiten (ohne Seitenneuladen)
        Route::patch('/{note_id}/titel', [LernenderNotenController::class, 'updateTitel'])->name('titel.update');
    });

/**
 * Berufsbildner: Lernende auswählen + Noten je Lernender (read-only MVP)
 */
Route::middleware(['auth', 'role:Berufsbildner'])
    ->prefix('berufsbildner')
    ->name('berufsbildner.')
    ->group(function () {
        Route::get('/lernende', [BerufsbildnerLernendeController::class, 'index'])->name('lernende.index');

        // BB erfasst einen neuen Lernenden (Betreuung wird automatisch zugewiesen)
        Route::get('/lernende/erfassen', [BerufsbildnerLernendeController::class, 'create'])
            ->name('lernende.create');
        Route::post('/lernende', [BerufsbildnerLernendeController::class, 'store'])
            ->name('lernende.store');

        Route::get('/lernende/{lernender_id}', [BerufsbildnerLernendeController::class, 'show'])
            ->whereNumber('lernender_id')->name('lernende.show');

        // BB darf begrenzte Profilfelder (Lehrbeginn, Lehrende) seiner Lernenden pflegen
        Route::patch('/lernende/{lernender_id}', [BerufsbildnerLernendeController::class, 'update'])
            ->whereNumber('lernender_id')->name('lernende.update');

        // BB verwaltet Tracks (BMS/ABU) betreuter Lernender
        Route::post('/lernende/{lernender_id}/tracks', [BerufsbildnerLernendeController::class, 'trackStore'])
            ->whereNumber('lernender_id')->name('lernende.tracks.store');
        Route::post('/tracks/{track_id}/beenden', [BerufsbildnerLernendeController::class, 'trackEnd'])
            ->whereNumber('track_id')->name('tracks.beenden');

        Route::get('/lernende/{lernender_id}/noten', [BerufsbildnerNotenController::class, 'index'])
            ->name('lernende.noten.index');

        // BB markiert eine Note als gesehen
        Route::post('/lernende/{lernender_id}/noten/{note_id}/gesehen', [BerufsbildnerNotenController::class, 'markGesehen'])
            ->name('noten.gesehen');

        // BB markiert alle neuen Noten eines Lernenden als gesehen
        Route::post('/lernende/{lernender_id}/noten/alle-gesehen', [BerufsbildnerNotenController::class, 'markAlleGesehen'])
            ->name('noten.alle_gesehen');

        // BB: Druckansicht Notenblatt für einen Lernenden
        Route::get('/lernende/{lernender_id}/noten/drucken', [BerufsbildnerNotenController::class, 'drucken'])
            ->name('lernende.noten.drucken');

        // BB: CSV-Export Noten für einen betreuten Lernenden
        Route::get('/lernende/{lernender_id}/noten/export', [BerufsbildnerNotenController::class, 'export'])
            ->name('lernende.noten.export');

        // BB: CSV-Export ALLE Noten aller betreuten Lernenden
        Route::get('/export-alle-noten', [BerufsbildnerNotenController::class, 'exportAlle'])
            ->name('export');
    });

/**
 * Admin: Lernende auswählen + Noten je Lernender (read-only MVP, getrennt von Berufsbildner)
 */
Route::middleware(['auth', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Lernende: Übersicht + Detail + Noten
        Route::get('/lernende', [AdminLernendeController::class, 'index'])->name('lernende.index');
        Route::get('/lernende/{lernender_id}', [AdminLernendeController::class, 'show'])->name('lernende.show');
        Route::get('/lernende/{lernender_id}/noten', [AdminNotenController::class, 'index'])
            ->name('lernende.noten.index');
        Route::get('/lernende/{lernender_id}/noten/export', [AdminNotenController::class, 'export'])
            ->name('lernende.noten.export');
        Route::get('/lernende/{lernender_id}/noten/drucken', [AdminNotenController::class, 'drucken'])
            ->name('lernende.noten.drucken');
        Route::get('/lernende/{lernender_id}/noten/create', [AdminNotenController::class, 'create'])
            ->name('lernende.noten.create');
        Route::post('/lernende/{lernender_id}/noten', [AdminNotenController::class, 'store'])
            ->name('lernende.noten.store');
        Route::get('/lernende/{lernender_id}/noten/{note_id}/edit', [AdminNotenController::class, 'edit'])
            ->name('lernende.noten.edit');
        Route::put('/lernende/{lernender_id}/noten/{note_id}', [AdminNotenController::class, 'update'])
            ->name('lernende.noten.update');
        Route::delete('/lernende/{lernender_id}/noten/{note_id}', [AdminNotenController::class, 'destroy'])
            ->name('lernende.noten.destroy');

        // Profil (Lehrberuf, Lehrbeginn, Lehrende)
        Route::get('/lernende/{lernender_id}/profil/edit', [AdminLernendeController::class, 'editProfil'])
            ->name('lernende.profil.edit');
        Route::put('/lernende/{lernender_id}/profil', [AdminLernendeController::class, 'updateProfil'])
            ->name('lernende.profil.update');

        // Betreuungen je Lernender
        Route::get('/lernende/{lernender_id}/betreuung', [AdminLernendeController::class, 'betreuung'])
            ->name('lernende.betreuung');
        Route::post('/lernende/{lernender_id}/betreuung', [AdminLernendeController::class, 'betreuungStore'])
            ->name('lernende.betreuung.store');
        Route::post('/betreuungen/{betreuung_id}/beenden', [AdminLernendeController::class, 'betreuungEnd'])
            ->name('betreuungen.beenden');

        // Tracks je Lernender
        Route::get('/lernende/{lernender_id}/tracks', [AdminLernendeController::class, 'tracks'])
            ->name('lernende.tracks');
        Route::post('/lernende/{lernender_id}/tracks', [AdminLernendeController::class, 'trackStore'])
            ->name('lernende.tracks.store');
        Route::post('/tracks/{track_id}/beenden', [AdminLernendeController::class, 'trackEnd'])
            ->name('tracks.beenden');

        // Berufsbildner-Übersicht
        Route::get('/berufsbildner', [AdminBerufsbildnerController::class, 'index'])->name('berufsbildner.index');

        // Benutzerverwaltung
        Route::get('/benutzer', [AdminBenutzerController::class, 'index'])->name('benutzer.index');
        Route::get('/benutzer/create', [AdminBenutzerController::class, 'create'])->name('benutzer.create');
        Route::post('/benutzer', [AdminBenutzerController::class, 'store'])->name('benutzer.store');
        Route::get('/benutzer/{benutzer_id}/edit', [AdminBenutzerController::class, 'edit'])->name('benutzer.edit');
        Route::put('/benutzer/{benutzer_id}', [AdminBenutzerController::class, 'update'])->name('benutzer.update');
        Route::post('/benutzer/{benutzer_id}/toggle-aktiv', [AdminBenutzerController::class, 'toggleAktiv'])
            ->name('benutzer.toggle-aktiv');

        // Stammdaten: Lehrberufe (inkl. Modul- & Fach-Zuweisung)
        Route::get('/stammdaten/lehrberufe', [StammdatenLehrberufeController::class, 'index'])
            ->name('stammdaten.lehrberufe.index');
        Route::get('/stammdaten/lehrberufe/create', [StammdatenLehrberufeController::class, 'create'])
            ->name('stammdaten.lehrberufe.create');
        Route::post('/stammdaten/lehrberufe', [StammdatenLehrberufeController::class, 'store'])
            ->name('stammdaten.lehrberufe.store');
        Route::get('/stammdaten/lehrberufe/{lehrberuf_id}', [StammdatenLehrberufeController::class, 'show'])
            ->name('stammdaten.lehrberufe.show');
        Route::get('/stammdaten/lehrberufe/{lehrberuf_id}/edit', [StammdatenLehrberufeController::class, 'edit'])
            ->name('stammdaten.lehrberufe.edit');
        Route::put('/stammdaten/lehrberufe/{lehrberuf_id}', [StammdatenLehrberufeController::class, 'update'])
            ->name('stammdaten.lehrberufe.update');
        Route::post('/stammdaten/lehrberufe/{lehrberuf_id}/module', [StammdatenLehrberufeController::class, 'assignModul'])
            ->name('stammdaten.lehrberufe.module.assign');
        Route::delete('/stammdaten/lehrberufe/{lehrberuf_id}/module/{modul_id}', [StammdatenLehrberufeController::class, 'removeModul'])
            ->name('stammdaten.lehrberufe.module.remove');
        Route::post('/stammdaten/lehrberufe/{lehrberuf_id}/faecher', [StammdatenLehrberufeController::class, 'assignFach'])
            ->name('stammdaten.lehrberufe.faecher.assign');
        Route::delete('/stammdaten/lehrberufe/{lehrberuf_id}/faecher/{fach_id}', [StammdatenLehrberufeController::class, 'removeFach'])
            ->name('stammdaten.lehrberufe.faecher.remove');

        // Stammdaten: Module
        Route::get('/stammdaten/module', [StammdatenModuleController::class, 'index'])
            ->name('stammdaten.module.index');
        Route::get('/stammdaten/module/create', [StammdatenModuleController::class, 'create'])
            ->name('stammdaten.module.create');
        Route::post('/stammdaten/module', [StammdatenModuleController::class, 'store'])
            ->name('stammdaten.module.store');
        Route::get('/stammdaten/module/{modul_id}/edit', [StammdatenModuleController::class, 'edit'])
            ->name('stammdaten.module.edit');
        Route::put('/stammdaten/module/{modul_id}', [StammdatenModuleController::class, 'update'])
            ->name('stammdaten.module.update');

        // Stammdaten: Fächer
        Route::get('/stammdaten/faecher', [StammdatenFaecherController::class, 'index'])
            ->name('stammdaten.faecher.index');
        Route::get('/stammdaten/faecher/create', [StammdatenFaecherController::class, 'create'])
            ->name('stammdaten.faecher.create');
        Route::post('/stammdaten/faecher', [StammdatenFaecherController::class, 'store'])
            ->name('stammdaten.faecher.store');
        Route::get('/stammdaten/faecher/{fach_id}/edit', [StammdatenFaecherController::class, 'edit'])
            ->name('stammdaten.faecher.edit');
        Route::put('/stammdaten/faecher/{fach_id}', [StammdatenFaecherController::class, 'update'])
            ->name('stammdaten.faecher.update');

        // Stammdaten: Semester
        Route::get('/stammdaten/semester', [StammdatenSemesterController::class, 'index'])
            ->name('stammdaten.semester.index');
        Route::get('/stammdaten/semester/create', [StammdatenSemesterController::class, 'create'])
            ->name('stammdaten.semester.create');
        Route::post('/stammdaten/semester', [StammdatenSemesterController::class, 'store'])
            ->name('stammdaten.semester.store');
        Route::get('/stammdaten/semester/{semester_id}/edit', [StammdatenSemesterController::class, 'edit'])
            ->name('stammdaten.semester.edit');
        Route::put('/stammdaten/semester/{semester_id}', [StammdatenSemesterController::class, 'update'])
            ->name('stammdaten.semester.update');

        // Berichte
        Route::get('/berichte/noten', [AdminBerichtController::class, 'noten'])
            ->name('berichte.noten');
        Route::get('/berichte/noten/export', [AdminBerichtController::class, 'notenExport'])
            ->name('berichte.noten.export');

        // Stammdaten: Kategorien
        Route::get('/stammdaten/kategorien', [StammdatenKategorieController::class, 'index'])
            ->name('stammdaten.kategorien.index');
        Route::get('/stammdaten/kategorien/create', [StammdatenKategorieController::class, 'create'])
            ->name('stammdaten.kategorien.create');
        Route::post('/stammdaten/kategorien', [StammdatenKategorieController::class, 'store'])
            ->name('stammdaten.kategorien.store');
        Route::get('/stammdaten/kategorien/{kategorie_id}/edit', [StammdatenKategorieController::class, 'edit'])
            ->name('stammdaten.kategorien.edit');
        Route::put('/stammdaten/kategorien/{kategorie_id}', [StammdatenKategorieController::class, 'update'])
            ->name('stammdaten.kategorien.update');

        // Feedback: Meldungen aller Benutzer sichten und bearbeiten
        Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/export', [AdminFeedbackController::class, 'export'])->name('feedback.export');
        Route::patch('/feedback/{feedback_id}', [AdminFeedbackController::class, 'update'])
            ->whereNumber('feedback_id')->name('feedback.update');
    });

/**
 * Feedback: für alle eingeloggten Rollen, Berechtigung prüft der Controller selbst
 */
Route::middleware('auth')->group(function () {
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')->name('feedback.store');
});

/**
 * Kommentare: zugänglich für Lernende und Berufsbildner (Zugriffskontrolle im Controller)
 */
Route::middleware('auth')->group(function () {
    Route::post('/noten/{note_id}/kommentare', [KommentarController::class, 'store'])
        ->name('noten.kommentare.store');
    Route::delete('/kommentare/{kommentar_id}', [KommentarController::class, 'destroy'])
        ->name('noten.kommentare.destroy');
});

/**
 * Profil (Breeze)
 */
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/darstellung', [ProfileController::class, 'darstellung'])->name('profile.darstellung');
    // Selbst-Löschung ist deaktiviert: Accounts werden ausschliesslich vom Admin verwaltet
});

require __DIR__ . '/auth.php';