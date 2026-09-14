<?php

use App\Http\Controllers\Admin\AktivitaetController as AdminAktivitaetController;
use App\Http\Controllers\Admin\BenutzerController as AdminBenutzerController;
use App\Http\Controllers\Admin\BerichtController as AdminBerichtController;
use App\Http\Controllers\Admin\BerufsbildnerController as AdminBerufsbildnerController;
use App\Http\Controllers\Admin\BetriebController;
use App\Http\Controllers\Admin\DatenauskunftController as AdminDatenauskunftController;
use App\Http\Controllers\Admin\EinrichtungController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ModulkatalogController;
use App\Http\Controllers\Admin\StammdatenFaecherController;
use App\Http\Controllers\Admin\StammdatenKategorieController;
use App\Http\Controllers\Admin\StammdatenLehrberufeController;
use App\Http\Controllers\Admin\StammdatenModuleController;
use App\Http\Controllers\Admin\StammdatenSemesterController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CalendarExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatenauskunftController;
use App\Http\Controllers\DokumenteController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\KommentarController;
use App\Http\Controllers\Lernender\CalendarController as LernenderCalendarController;
use App\Http\Controllers\Lernender\NotenController as LernenderNotenController;
use App\Http\Controllers\Lernender\PruefungenController;
use App\Http\Controllers\Lernender\RechnerController as LernenderRechnerController;
use App\Http\Controllers\Lernender\ZieleController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\NotenImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitzungController;
use App\Http\Controllers\SprachwahlController;
use App\Http\Controllers\SucheController;
use App\Http\Controllers\SystemhinweisController;
use App\Support\LegacyPaths;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

// Register-Route ist in routes/auth.php als 'register' benannt definiert

Route::get('/dashboard', function (Request $request) {
    $u = $request->user();

    if ($u && $u->hasRole('Admin')) {
        return redirect()->route('admin.dashboard');
    }

    if ($u && $u->hasRole('Berufsbildner')) {
        return redirect()->route('trainer.dashboard');
    }

    return redirect()->route('learner.dashboard');
})->middleware(['auth'])->name('dashboard');

/**
 * Rollen-Dashboards mit Datenbeschaffung via DashboardController
 */
Route::get('/learner', [DashboardController::class, 'lernender'])
    ->middleware(['auth', 'role:Lernender'])
    ->name('learner.dashboard');

Route::get('/trainer', [DashboardController::class, 'berufsbildner'])
    ->middleware(['auth', 'role:Berufsbildner'])
    ->name('trainer.dashboard');

Route::get('/admin', [DashboardController::class, 'admin'])
    ->middleware(['auth', 'role:Admin'])
    ->name('admin.dashboard');

/**
 * Lernender: eigene Noten (learner.grades.*)
 */
Route::middleware(['auth', 'role:Lernender'])
    ->prefix('grades')
    ->name('learner.grades.')
    ->group(function () {
        Route::get('/', [LernenderNotenController::class, 'index'])->name('index');
        Route::get('/print', [LernenderNotenController::class, 'drucken'])->name('print');
        Route::get('/export', [LernenderNotenController::class, 'export'])->name('export');
        Route::get('/calculator', [LernenderRechnerController::class, 'index'])->name('calculator');
        Route::post('/calculator', [LernenderRechnerController::class, 'berechnen'])->middleware('throttle:120,1')->name('calculator.calculate');
        Route::post('/calculator/simulate', [LernenderRechnerController::class, 'simulieren'])->middleware('throttle:30,1')->name('calculator.simulate');
        Route::get('/import', [NotenImportController::class, 'index'])->name('import.index');
        Route::post('/import', [NotenImportController::class, 'lesen'])->middleware('throttle:30,1')->name('import.read');
        Route::post('/import/validate', [NotenImportController::class, 'pruefen'])->middleware('throttle:60,1')->name('import.validate');
        Route::post('/import/apply', [NotenImportController::class, 'uebernehmen'])->name('import.apply');
        Route::post('/import/discard', [NotenImportController::class, 'verwerfen'])->name('import.discard');
        Route::get('/import/template', [NotenImportController::class, 'vorlage'])->name('import.template');
        Route::get('/create', [LernenderNotenController::class, 'create'])->name('create');
        Route::post('/', [LernenderNotenController::class, 'store'])->name('store');
        Route::post('/modules/{modul_id}/repeat', [LernenderNotenController::class, 'modulWiederholen'])->whereNumber('modul_id')->name('module.repeat');
        Route::post('/modules/{modul_id}/resume', [LernenderNotenController::class, 'modulFortsetzen'])->whereNumber('modul_id')->name('module.resume');

        Route::get('/{note_id}/edit', [LernenderNotenController::class, 'edit'])->name('edit');
        Route::put('/{note_id}', [LernenderNotenController::class, 'update'])->name('update');
        Route::delete('/{note_id}', [LernenderNotenController::class, 'destroy'])->name('destroy');

        // AJAX: Note als gelesen markieren (beim Öffnen des Detail-Accordions)
        Route::post('/{note_id}/seen', [LernenderNotenController::class, 'markGesehen'])->name('seen');

        // AJAX: Notiz/Titel einer Note inline bearbeiten (ohne Seitenneuladen)
        Route::patch('/{note_id}/title', [LernenderNotenController::class, 'updateTitel'])->name('title.update');
    });

/**
 * Lernender: eigene Dokumente (private Ablage, Auslieferung nur über den Controller)
 */
Route::middleware(['auth', 'role:Lernender'])
    ->prefix('documents')
    ->name('learner.documents.')
    ->group(function () {
        Route::get('/', [DokumenteController::class, 'index'])->name('index');
        Route::post('/', [DokumenteController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{dokument_id}', [DokumenteController::class, 'show'])->whereNumber('dokument_id')->name('show');
        Route::delete('/{dokument_id}', [DokumenteController::class, 'destroy'])->whereNumber('dokument_id')->name('destroy');
        Route::get('/{dokument_id}/reconcile', [DokumenteController::class, 'abgleich'])->whereNumber('dokument_id')->name('reconcile');
        Route::post('/{dokument_id}/reconcile', [DokumenteController::class, 'abgleichUebernehmen'])->whereNumber('dokument_id')->name('reconcile.apply');
    });

/**
 * Lernender: Agenda (eigene Prüfungen, Schulnetz-Termine/-Lektionen, Kalender-Abo) und Ziele
 */
Route::middleware(['auth', 'role:Lernender'])
    ->name('learner.')
    ->group(function () {
        Route::get('/exams', [PruefungenController::class, 'index'])->name('exams.index');
        Route::post('/exams', [PruefungenController::class, 'store'])->name('exams.store');
        Route::put('/exams/{pruefung_id}', [PruefungenController::class, 'update'])->whereNumber('pruefung_id')->name('exams.update');
        Route::delete('/exams/{pruefung_id}', [PruefungenController::class, 'destroy'])->whereNumber('pruefung_id')->name('exams.destroy');
        Route::post('/exams/detected/{calendar_event_id}/adopt', [PruefungenController::class, 'adopt'])
            ->whereNumber('calendar_event_id')->name('exams.adopt');
        Route::post('/exams/{pruefung_id}/unlock', [PruefungenController::class, 'unlock'])
            ->whereNumber('pruefung_id')->name('exams.unlock');

        Route::post('/calendar/feed', [LernenderCalendarController::class, 'feedStore'])
            ->middleware('throttle:10,1,calendar-feed')->name('calendar.feed.store');
        Route::delete('/calendar/feed/{id}', [LernenderCalendarController::class, 'feedDestroy'])
            ->whereNumber('id')->middleware('throttle:10,1,calendar-feed-destroy')->name('calendar.feed.destroy');
        Route::post('/calendar/feed/{id}/sync', [LernenderCalendarController::class, 'feedSync'])
            ->whereNumber('id')->middleware('throttle:6,1,calendar-feed-sync')->name('calendar.feed.sync');
        Route::post('/calendar/sync', [LernenderCalendarController::class, 'sync'])
            ->middleware('throttle:6,1,calendar-sync')->name('calendar.sync');

        Route::post('/goals', [ZieleController::class, 'store'])->name('goals.store');
        Route::delete('/goals/{ziel_id}', [ZieleController::class, 'destroy'])->whereNumber('ziel_id')->name('goals.destroy');
    });

/**
 * Öffentlicher iCal-Abo-Link (Token statt Login) für Lernende, Berufsbildner und Admin.
 */
Route::get('/calendar/{token}.ics', CalendarExportController::class)
    ->middleware('throttle:60,1,calendar-export')->whereAlphaNumeric('token')->name('calendar.export');

/**
 * Installierbare Web-App (PWA): Manifest und statische Offline-Seite, beide öffentlich,
 * ohne Personendaten (siehe public/sw.js für den Service Worker).
 */
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::view('/offline', 'offline')->name('offline');

/**
 * Betriebslogo (Block AH): öffentlich, ohne Login, für Login-Seite und Mail-Kopf.
 * Ohne Session/Cookie/CSRF-Middleware der web-Gruppe: eine cachebare Antwort darf keinen
 * Set-Cookie-Header tragen (sonst könnte ein Proxy über http die Session eines Admins mitcachen).
 */
Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ]);

/**
 * Lernenden-Verwaltung: gleicher Funktionsumfang für Admin und Berufsbildner,
 * Sichtbarkeit über Lernender::sichtbarFuer() (routes/verwaltung.php)
 */
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(base_path('routes/verwaltung.php'));
Route::middleware(['auth', 'role:Berufsbildner'])->prefix('trainer')->name('trainer.')->group(base_path('routes/verwaltung.php'));

/**
 * Admin: Benutzerkonten (Admin/BB), Stammdaten, Berichte
 */
Route::middleware(['auth', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Berufsbildner-Übersicht
        Route::get('/trainers', [AdminBerufsbildnerController::class, 'index'])->name('trainers.index');

        // Benutzerverwaltung
        Route::get('/users', [AdminBenutzerController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminBenutzerController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminBenutzerController::class, 'store'])->name('users.store');
        Route::get('/users/{benutzer_id}/edit', [AdminBenutzerController::class, 'edit'])->name('users.edit');
        Route::put('/users/{benutzer_id}', [AdminBenutzerController::class, 'update'])->name('users.update');
        Route::post('/users/{benutzer_id}/toggle-active', [AdminBenutzerController::class, 'toggleAktiv'])
            ->name('users.toggle-active');
        Route::get('/users/{benutzer_id}/data-export', [AdminDatenauskunftController::class, 'zeigen'])
            ->whereNumber('benutzer_id')->middleware('throttle:3,1,data-export')->name('users.data-export');

        // Stammdaten: Lehrberufe (inkl. Modul- & Fach-Zuweisung)
        Route::get('/master-data/professions', [StammdatenLehrberufeController::class, 'index'])
            ->name('master-data.professions.index');
        Route::get('/master-data/professions/create', [StammdatenLehrberufeController::class, 'create'])
            ->name('master-data.professions.create');
        Route::post('/master-data/professions', [StammdatenLehrberufeController::class, 'store'])
            ->name('master-data.professions.store');
        Route::get('/master-data/professions/{lehrberuf_id}', [StammdatenLehrberufeController::class, 'show'])
            ->name('master-data.professions.show');
        Route::get('/master-data/professions/{lehrberuf_id}/edit', [StammdatenLehrberufeController::class, 'edit'])
            ->name('master-data.professions.edit');
        Route::put('/master-data/professions/{lehrberuf_id}', [StammdatenLehrberufeController::class, 'update'])
            ->name('master-data.professions.update');
        Route::post('/master-data/professions/{lehrberuf_id}/modules', [StammdatenLehrberufeController::class, 'assignModul'])
            ->name('master-data.professions.modules.assign');
        Route::delete('/master-data/professions/{lehrberuf_id}/modules/{modul_id}', [StammdatenLehrberufeController::class, 'removeModul'])
            ->name('master-data.professions.modules.remove');
        Route::patch('/master-data/professions/{lehrberuf_id}/modules/{modul_id}', [StammdatenLehrberufeController::class, 'updateModulKategorie'])
            ->name('master-data.professions.modules.update');
        Route::post('/master-data/professions/{lehrberuf_id}/subjects', [StammdatenLehrberufeController::class, 'assignFach'])
            ->name('master-data.professions.subjects.assign');
        Route::delete('/master-data/professions/{lehrberuf_id}/subjects/{fach_id}', [StammdatenLehrberufeController::class, 'removeFach'])
            ->name('master-data.professions.subjects.remove');

        // Stammdaten: Module
        Route::get('/master-data/modules', [StammdatenModuleController::class, 'index'])
            ->name('master-data.modules.index');
        Route::get('/master-data/modules/create', [StammdatenModuleController::class, 'create'])
            ->name('master-data.modules.create');
        Route::post('/master-data/modules', [StammdatenModuleController::class, 'store'])
            ->name('master-data.modules.store');
        Route::get('/master-data/modules/{modul_id}/edit', [StammdatenModuleController::class, 'edit'])
            ->name('master-data.modules.edit');
        Route::put('/master-data/modules/{modul_id}', [StammdatenModuleController::class, 'update'])
            ->name('master-data.modules.update');

        // Stammdaten: Modulkatalog einlesen – Ernte hochladen, Vorschau prüfen, übernehmen
        Route::get('/master-data/modules/catalog', [ModulkatalogController::class, 'index'])
            ->name('master-data.modules.catalog');
        Route::post('/master-data/modules/catalog', [ModulkatalogController::class, 'lesen'])
            ->middleware('throttle:30,1')->name('master-data.modules.catalog.read');
        Route::post('/master-data/modules/catalog/apply', [ModulkatalogController::class, 'anwenden'])
            ->name('master-data.modules.catalog.apply');
        Route::post('/master-data/modules/catalog/discard', [ModulkatalogController::class, 'verwerfen'])
            ->name('master-data.modules.catalog.discard');

        // Stammdaten: Fächer
        Route::get('/master-data/subjects', [StammdatenFaecherController::class, 'index'])
            ->name('master-data.subjects.index');
        Route::get('/master-data/subjects/create', [StammdatenFaecherController::class, 'create'])
            ->name('master-data.subjects.create');
        Route::post('/master-data/subjects', [StammdatenFaecherController::class, 'store'])
            ->name('master-data.subjects.store');
        Route::get('/master-data/subjects/{fach_id}/edit', [StammdatenFaecherController::class, 'edit'])
            ->name('master-data.subjects.edit');
        Route::put('/master-data/subjects/{fach_id}', [StammdatenFaecherController::class, 'update'])
            ->name('master-data.subjects.update');

        // Stammdaten: Semester
        Route::get('/master-data/semesters', [StammdatenSemesterController::class, 'index'])
            ->name('master-data.semesters.index');
        Route::get('/master-data/semesters/create', [StammdatenSemesterController::class, 'create'])
            ->name('master-data.semesters.create');
        Route::post('/master-data/semesters', [StammdatenSemesterController::class, 'store'])
            ->name('master-data.semesters.store');
        Route::get('/master-data/semesters/{semester_id}/edit', [StammdatenSemesterController::class, 'edit'])
            ->name('master-data.semesters.edit');
        Route::put('/master-data/semesters/{semester_id}', [StammdatenSemesterController::class, 'update'])
            ->name('master-data.semesters.update');
        Route::delete('/master-data/semesters/{semester_id}', [StammdatenSemesterController::class, 'destroy'])
            ->name('master-data.semesters.destroy');

        // Berichte
        Route::get('/reports/grades', [AdminBerichtController::class, 'noten'])
            ->name('reports.grades');
        Route::get('/reports/grades/export', [AdminBerichtController::class, 'notenExport'])
            ->name('reports.grades.export');

        // Aktivitätsprotokoll (Audit-Log)
        Route::get('/activity', [AdminAktivitaetController::class, 'index'])->name('activity.index');

        // Stammdaten: Kategorien
        Route::get('/master-data/categories', [StammdatenKategorieController::class, 'index'])
            ->name('master-data.categories.index');
        Route::get('/master-data/categories/create', [StammdatenKategorieController::class, 'create'])
            ->name('master-data.categories.create');
        Route::post('/master-data/categories', [StammdatenKategorieController::class, 'store'])
            ->name('master-data.categories.store');
        Route::get('/master-data/categories/{kategorie_id}/edit', [StammdatenKategorieController::class, 'edit'])
            ->name('master-data.categories.edit');
        Route::put('/master-data/categories/{kategorie_id}', [StammdatenKategorieController::class, 'update'])
            ->name('master-data.categories.update');

        // Feedback: Meldungen aller Benutzer sichten und bearbeiten
        Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/export', [AdminFeedbackController::class, 'export'])->name('feedback.export');
        Route::patch('/feedback/{feedback_id}', [AdminFeedbackController::class, 'update'])
            ->whereNumber('feedback_id')->name('feedback.update');
        Route::get('/feedback/{feedback_id}/screenshot', [AdminFeedbackController::class, 'screenshot'])
            ->whereNumber('feedback_id')->name('feedback.screenshot');
        Route::patch('/feedback/{feedback_id}/duplicate', [AdminFeedbackController::class, 'duplikat'])
            ->whereNumber('feedback_id')->name('feedback.duplicate');
    });

/**
 * Feedback: für alle eingeloggten Rollen, Berechtigung prüft der Controller selbst
 */
Route::middleware('auth')->group(function () {
    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:10,1')->name('feedback.store');
    Route::post('/feedback/hint', [FeedbackController::class, 'hinweisSchliessen'])->name('feedback.hint.dismiss');
    Route::get('/feedback/similar', [FeedbackController::class, 'aehnliche'])
        ->middleware('throttle:60,1,feedback-similar')->name('feedback.similar');
    Route::post('/feedback/{feedback_id}/vote', [FeedbackController::class, 'stimmen'])
        ->whereNumber('feedback_id')->middleware('throttle:30,1,feedback-vote')->name('feedback.vote');
    Route::delete('/feedback/{feedback_id}/vote', [FeedbackController::class, 'stimmeZurueck'])
        ->whereNumber('feedback_id')->middleware('throttle:30,1,feedback-vote')->name('feedback.vote.destroy');
    Route::get('/feedback/{feedback_id}/anhaenge/{anhang_id}', [FeedbackController::class, 'anhang'])
        ->whereNumber('feedback_id')->whereNumber('anhang_id')->name('feedback.attachment');
});

/**
 * Systemhinweis-Banner (Wegklicken) und Sitzungs-Timeout (Keep-Alive): für alle eingeloggten
 * Rollen, keine eigene Berechtigung nötig.
 */
Route::middleware('auth')->group(function () {
    Route::post('/system-notice/dismiss', [SystemhinweisController::class, 'schliessen'])
        ->middleware('throttle:20,1,system-notice')->name('system-notice.dismiss');
    Route::post('/session/keep-alive', [SitzungController::class, 'verlaengern'])
        ->middleware('throttle:10,1,keep-alive')->name('session.keep-alive');
});

Route::get('/search', SucheController::class)->middleware(['auth', 'throttle:60,1,search'])->name('search');

/**
 * Kommentare: zugänglich für Lernende und Berufsbildner (Zugriffskontrolle im Controller)
 */
Route::middleware('auth')->group(function () {
    Route::post('/grades/{note_id}/comments', [KommentarController::class, 'store'])
        ->name('comments.store');
    Route::delete('/comments/{kommentar_id}', [KommentarController::class, 'destroy'])
        ->name('comments.destroy');
});

/**
 * Profil (Breeze)
 */
Route::middleware('auth')->group(function () {
    // Alte URL (vor der Seite «Einstellungen»): dauerhaft auf /settings/profile umleiten.
    // Kein Route::redirect() – das würde mit der PATCH-Route auf derselben URI kollidieren.
    Route::get('/profile', fn () => redirect()->route('settings.profile', [], 301))->name('profile.legacy');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/appearance', [ProfileController::class, 'darstellung'])->name('profile.appearance');
    Route::patch('/profile/preferences', [ProfileController::class, 'preferences'])
        ->middleware('throttle:30,1,preferences')->name('profile.preferences');
    Route::delete('/profile/preferences', [ProfileController::class, 'resetPreferences'])
        ->middleware('throttle:30,1,preferences')->name('profile.preferences.reset');
    Route::get('/profile/data-export', [DatenauskunftController::class, 'eigene'])
        ->middleware(['password.confirm', 'throttle:3,1,data-export'])->name('profile.data-export');
    // Selbst-Löschung ist deaktiviert: Accounts werden ausschliesslich vom Admin verwaltet
});

/**
 * Einstellungen (/settings/…, alle Rollen, ein Tab je Route – siehe settings/_tabs.blade.php)
 */
Route::middleware('auth')->prefix('settings')->name('settings.')->group(function () {
    Route::get('/', fn () => redirect()->route('settings.profile'))->name('index');
    Route::get('/profile', [SettingsController::class, 'profile'])->name('profile');
    Route::get('/calendar', [SettingsController::class, 'calendar'])->name('calendar');
    Route::post('/calendar/token', [SettingsController::class, 'calendarTokenReset'])
        ->middleware('throttle:10,1,calendar-token')->name('calendar.token.reset');
    Route::get('/data', [SettingsController::class, 'data'])->name('data');
});

/*
 * Ersteinrichtung und Betriebseinstellungen (Admin)
 */
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/setup/{schritt?}', [EinrichtungController::class, 'show'])
        ->name('setup');
    foreach (['operations' => 'betrieb', 'categories' => 'kategorien', 'semesters' => 'semester', 'professions' => 'lehrberufe',
        'modules' => 'module', 'people' => 'personen', 'learners' => 'lernende', 'mail' => 'mail', 'finish' => 'abschliessen'] as $pfad => $aktion) {
        Route::post('/setup/'.$pfad, [EinrichtungController::class, $aktion])->name('setup.'.$pfad);
    }
    Route::get('/operations', [BetriebController::class, 'edit'])->name('operations.edit');
    Route::put('/operations', [BetriebController::class, 'update'])->name('operations.update');
    Route::put('/operations/theme', [BetriebController::class, 'themeSpeichern'])->name('operations.theme.update');
    Route::post('/operations/backups', [BetriebController::class, 'sicherungErstellen'])->name('operations.backups.store');
    Route::get('/operations/backups/{name}', [BetriebController::class, 'sicherungHerunterladen'])->name('operations.backups.show');
    Route::delete('/operations/backups/{name}', [BetriebController::class, 'sicherungLoeschen'])->name('operations.backups.destroy');
    Route::put('/operations/offsite', [BetriebController::class, 'kopieSpeichern'])->name('operations.offsite.update');
    Route::post('/operations/offsite', [BetriebController::class, 'kopieAusfuehren'])->middleware('throttle:6,1,sicherung-kopie')->name('operations.offsite.run');
    Route::put('/operations/notice', [BetriebController::class, 'hinweisSpeichern'])->name('operations.notice.update');
    Route::put('/operations/feedback-button', [BetriebController::class, 'feedbackKnopfSpeichern'])->name('operations.feedback-button.update');
    Route::put('/operations/logo', [BetriebController::class, 'logoSpeichern'])->name('operations.logo.update');
    Route::put('/operations/session', [BetriebController::class, 'sitzungSpeichern'])->name('operations.session.update');
});

require __DIR__.'/auth.php';
require __DIR__.'/notifications.php';

/*
 * Old German paths (before 11.09.2026): 301 to the English path, otherwise 404.
 */
Route::fallback(function (Request $request) {
    $target = LegacyPaths::redirectTarget($request);
    abort_if($target === null, 404);

    return redirect($target, 301);
});

/*
 * Sprache (Benutzermenü, Profil; Gäste nur Session) und Sprachwahl des Betriebs (Seite Betrieb)
 */
Route::put('/profile/locale', [ProfileController::class, 'locale'])->middleware('throttle:20,1,locale')->name('profile.locale');
Route::put('/admin/operations/language', SprachwahlController::class)->middleware(['auth', 'role:Admin'])->name('admin.operations.language.update');
