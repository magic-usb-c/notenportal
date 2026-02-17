<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Lernender\NotenController as LernenderNotenController;
use App\Http\Controllers\Berufsbildner\NotenController as BerufsbildnerNotenController;
use App\Http\Controllers\Berufsbildner\LernendeController as BerufsbildnerLernendeController;
use App\Http\Controllers\Admin\LernendeController as AdminLernendeController;
use App\Http\Controllers\Admin\NotenController as AdminNotenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
 * Rollen-Dashboards (Landing-Pages)
 */
Route::get('/lernender', function () {
    return view('dashboards.lernender');
})->middleware(['auth', 'role:Lernender'])->name('lernender.dashboard');

Route::get('/berufsbildner', function () {
    return view('dashboards.berufsbildner');
})->middleware(['auth', 'role:Berufsbildner'])->name('berufsbildner.dashboard');

Route::get('/admin', function () {
    return view('dashboards.admin');
})->middleware(['auth', 'role:Admin'])->name('admin.dashboard');

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
    });

/**
 * Admin: Lernende auswählen + Noten je Lernender (read-only MVP, getrennt von Berufsbildner)
 */
Route::middleware(['auth', 'role:Admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/lernende', [AdminLernendeController::class, 'index'])->name('lernende.index');

        Route::get('/lernende/{lernender_id}/noten', [AdminNotenController::class, 'index'])
            ->name('lernende.noten.index');
    });

/**
 * Profil (Breeze)
 */
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
