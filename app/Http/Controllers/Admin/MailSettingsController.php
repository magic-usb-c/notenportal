<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\MailSettings;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Mail-Einstellungen (Teil der Seite Betrieb) und Testmail. */
class MailSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(MailSettings::rules());
        MailSettings::save($validated);

        return redirect()->route('admin.operations.edit')->with('success', __('Mail-Einstellungen gespeichert.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate(['test_to' => ['required', 'email', 'max:190']]);
        $to = $validated['test_to'];

        $werte = MailSettings::values();
        $content = new MailContent(
            subject: __('Testmail aus dem Notenportal'),
            lines: [__('Diese Mail bestätigt, dass der Mailversand des Notenportals eingerichtet ist.')],
            facts: [
                __('Server') => $werte[MailSettings::HOST] ?: ($werte['env_host'] ?? '–'),
                __('Absender') => $werte[MailSettings::FROM_ADDRESS] ?: ($werte['env_from'] ?? '–'),
                __('Zeitpunkt') => now()->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            ],
        );

        $log = Notifier::dispatch($to, NotificationCatalog::TEST, $content, now: true);

        if ($log->status === MailLog::SKIPPED) {
            $domain = mb_substr((string) strrchr($to, '@'), 1) ?: $to;

            return redirect()->route('admin.operations.edit')
                ->with('error', __(':to ist eine Testadresse (:domain) – Umleitung setzen oder echte Adresse verwenden.', ['to' => $to, 'domain' => $domain]));
        }
        if ($log->status === MailLog::FAILED) {
            return redirect()->route('admin.operations.edit')
                ->with('error', __('Testmail fehlgeschlagen: :fehler', ['fehler' => mb_substr((string) $log->error, 0, 150)]));
        }

        return redirect()->route('admin.operations.edit')->with('success', __('Testmail an :to verschickt.', ['to' => $to]));
    }
}
