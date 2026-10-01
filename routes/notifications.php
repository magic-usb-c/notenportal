<?php

use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\NotificationPolicyController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * E-Mail und Benachrichtigungen
 */

// Eigene Benachrichtigungen (alle Rollen)
Route::middleware('auth')->group(function () {
    Route::get('/settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.settings');
    Route::put('/settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.settings.update');
});

// Abbestellen aus der Mail: signierter Link ohne Login; POST = One-Click nach RFC 8058 (Mailprogramm, ohne CSRF-Token)
Route::middleware(['signed', 'throttle:30,1,unsubscribe'])->group(function () {
    Route::get('/notifications/unsubscribe/{user}/{type}', [UnsubscribeController::class, 'show'])
        ->whereNumber('user')->name('notifications.unsubscribe');
    Route::post('/notifications/unsubscribe/{user}/{type}', [UnsubscribeController::class, 'store'])
        ->whereNumber('user')->withoutMiddleware(ValidateCsrfToken::class)->name('notifications.unsubscribe.store');
});

// Passwort vergessen / festlegen per Mail-Link (auch für neu eröffnete Konten)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1,forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:10,1,reset-password')->name('password.store');
});

// Admin: Mail-Einstellungen (Teil der Seite Betrieb), Versandprotokoll, Regeln je Anlass
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::put('/operations/mail', [MailSettingsController::class, 'update'])->name('mail.update');
    // Eigener Präfix: throttle:max,decay ohne Präfix schlüsselt nur nach Benutzer-ID (nicht nach Route) –
    // ohne diesen Präfix würde dieses niedrige Limit mit anderen throttle:-Routen desselben Benutzers geteilt.
    Route::post('/operations/mail/test', [MailSettingsController::class, 'test'])->middleware('throttle:6,1,mail-test')->name('mail.test');
    Route::get('/mail-log', [MailLogController::class, 'index'])->name('mail-log.index');
    Route::post('/mail-log/{id}/retry', [MailLogController::class, 'retry'])->whereNumber('id')->name('mail-log.retry');
    Route::get('/notifications', [NotificationPolicyController::class, 'index'])->name('notifications.index');
    Route::put('/notifications', [NotificationPolicyController::class, 'update'])->name('notifications.update');
});
