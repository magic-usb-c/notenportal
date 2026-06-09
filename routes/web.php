<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KommentarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Lernender\NotenController as LernenderNotenController;
use App\Http\Controllers\Berufsbildner\NotenController as BerufsbildnerNotenController;
use App\Http\Controllers\Berufsbildner\LernendeController as BerufsbildnerLernendeController;
use App\Http\Controllers\Admin\BenutzerController as AdminBenutzerController;
use App\Http\Controllers\Admin\LernendeController as AdminLernendeController;
use App\Http\Controllers\Admin\NotenController as AdminNotenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Safety-Net: Registrierung ist deaktiviert
Route::get('/register', fn () => redirect()->route('login'));

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
        Route::get('/create', [LernenderNotenController::class, 'create'])->name('create');
        Route::post('/', [LernenderNotenController::class, 'store'])->name('store');

        Route::get('/{note_id}/edit', [LernenderNotenController::class, 'edit'])->name('edit');
        Route::put('/{note_id}', [LernenderNotenController::class, 'update'])->name('update');
        Route::delete('/{note_id}', [LernenderNotenController::class, 'destroy'])->name('destroy');

        // AJAX: Note als gelesen markieren (beim Öffnen des Detail-Accordions)
        Route::post('/{note_id}/gesehen', [LernenderNotenController::class, 'markGesehen'])->name('gesehen.mark');
    });

/**
 * Berufsbildner: Lernende auswählen + Noten je Lernender (read-only MVP)
 */
Route::middleware(['auth', 'role:Berufsbildner'])
    ->prefix('berufsbildner')
    ->name('berufsbildner.')
    ->group(function () {
        Route::get('/lernende', [BerufsbildnerLernendeController::class, 'index'])->name('lernende.index');

        Route::get('/lernende/{lernender_id}/noten', [BerufsbildnerNotenController::class, 'index'])
            ->name('lernende.noten.index');

        // BB markiert eine Note als gesehen
        Route::post('/lernende/{lernender_id}/noten/{note_id}/gesehen', [BerufsbildnerNotenController::class, 'markGesehen'])
            ->name('noten.gesehen');
    });

/**
 * Admin: Lernende auswählen + Noten je Lernender (read-only MVP, getrennt von Berufsbildner)
 */
Route::middleware(['auth', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Lernende: Übersicht + Noten
        Route::get('/lernende', [AdminLernendeController::class, 'index'])->name('lernende.index');
        Route::get('/lernende/{lernender_id}/noten', [AdminNotenController::class, 'index'])
            ->name('lernende.noten.index');

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

        // Benutzerverwaltung
        Route::get('/benutzer', [AdminBenutzerController::class, 'index'])->name('benutzer.index');
        Route::get('/benutzer/create', [AdminBenutzerController::class, 'create'])->name('benutzer.create');
        Route::post('/benutzer', [AdminBenutzerController::class, 'store'])->name('benutzer.store');
        Route::post('/benutzer/{benutzer_id}/toggle-aktiv', [AdminBenutzerController::class, 'toggleAktiv'])
            ->name('benutzer.toggle-aktiv');
    });

/**
 * Kommentare: zugänglich für Lernende und Berufsbildner (Zugriffskontrolle im Controller)
 */
Route::middleware('auth')
    ->post('/noten/{note_id}/kommentare', [KommentarController::class, 'store'])
    ->name('noten.kommentare.store');

/**
 * Profil (Breeze)
 */
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';