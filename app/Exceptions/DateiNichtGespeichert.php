<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Schreiben auf die Disk ist fehlgeschlagen (volle Platte, fehlende Rechte). Die Disks melden das nur
 * mit false (throw => false), darum prüfen alle Upload-Stellen das Ergebnis über pruefen(). Laravel
 * protokolliert die Ausnahme und schickt die Person mit Meldung zurück zum Formular statt auf eine 500er-Seite.
 */
class DateiNichtGespeichert extends RuntimeException
{
    /**
     * @template T
     *
     * @param  T|false  $ergebnis
     * @return T
     */
    public static function pruefen(mixed $ergebnis, string $pfad): mixed
    {
        if ($ergebnis === false) {
            throw new self('Datei liess sich nicht speichern: '.$pfad);
        }

        return $ergebnis;
    }

    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return back()
            ->with('error', __('Die Datei liess sich nicht speichern. Bitte später erneut versuchen.'))
            ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']));
    }
}
