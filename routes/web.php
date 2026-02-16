<?php

use App\Http\Controllers\ProfileController;
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
        return redirect()->route('trainer');
    }

    return redirect()->route('lernender');
})->middleware(['auth'])->name('dashboard');

Route::get('/lernender', function () {
    return view('dashboards.lernender');
})->middleware(['auth', 'role:Lernender'])->name('lernender');

Route::get('/trainer', function () {
    return view('dashboards.trainer');
})->middleware(['auth', 'role:Berufsbildner,Admin'])->name('trainer');

Route::get('/admin', function () {
    return view('dashboards.admin');
})->middleware(['auth', 'role:Admin'])->name('admin');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
