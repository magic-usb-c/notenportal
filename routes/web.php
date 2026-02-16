<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Noten\NoteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function (Request $request) {
    $u = $request->user();

    if ($u && $u->hasRole('Admin')) {
        return redirect()->route('admin');
    }

    if ($u && $u->hasRole('Berufsbildner')) {
        return redirect()->route('berufsbildner');
    }

    return redirect()->route('lernender');
})->middleware(['auth'])->name('dashboard');

Route::get('/lernender', function () {
    return view('dashboards.lernender');
})->middleware(['auth', 'role:Lernender'])->name('lernender');

Route::get('/berufsbildner', function () {
    return view('dashboards.trainer');
})->middleware(['auth', 'role:Berufsbildner,Admin'])->name('berufsbildner');

Route::get('/admin', function () {
    return view('dashboards.admin');
})->middleware(['auth', 'role:Admin'])->name('admin');

/**
 * Noten:
 * - Index: Lernender, Berufsbildner, Admin
 * - CRUD: nur Lernender (Admin später separat sauber als "Admin verwaltet alle Noten")
 * - Berufsbildner: kann Noten als gelesen markieren
 */
Route::middleware(['auth', 'role:Lernender,Berufsbildner,Admin'])
    ->prefix('noten')
    ->name('noten.')
    ->group(function () {
        Route::get('/', [NoteController::class, 'index'])->name('index');

        Route::middleware(['role:Lernender'])->group(function () {
            Route::get('/create', [NoteController::class, 'create'])->name('create');
            Route::post('/', [NoteController::class, 'store'])->name('store');

            Route::get('/{note_id}/edit', [NoteController::class, 'edit'])->name('edit');
            Route::put('/{note_id}', [NoteController::class, 'update'])->name('update');
            Route::delete('/{note_id}', [NoteController::class, 'destroy'])->name('destroy');
        });

        Route::middleware(['role:Berufsbildner'])->group(function () {
            Route::post('/{note_id}/gesehen', [NoteController::class, 'gesehen'])->name('gesehen');
        });
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
