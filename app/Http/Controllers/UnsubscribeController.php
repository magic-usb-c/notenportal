<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Abbestellen aus der Mail: signierter Link, kein Login nötig. GET zeigt eine
 * Bestätigungsseite, POST setzt die Präferenz – auch als RFC-8058-One-Click
 * (Mailprogramm postet ohne CSRF-Token und ohne die Seite zu öffnen).
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, int $user, string $type): View
    {
        $benutzer = User::findOrFail($user);
        abort_unless(NotificationCatalog::exists($type), 404);

        $def = NotificationCatalog::get($type);

        return view('notifications.abbestellen', [
            'label' => $def['label'],
            'mandatory' => ! $this->abbestellbar($type),
        ]);
    }

    public function store(Request $request, int $user, string $type): View|Response
    {
        $benutzer = User::findOrFail($user);
        abort_unless(NotificationCatalog::exists($type), 404);

        $def = NotificationCatalog::get($type);

        // RFC 8058: Mailprogramme senden den Body "List-Unsubscribe=One-Click" statt die Seite zu öffnen.
        $oneClick = str_contains((string) $request->getContent(), 'List-Unsubscribe=One-Click');

        // Der Link steht in älteren Mails: hat der Betrieb den Anlass inzwischen zur Pflicht gemacht, ändert sich nichts,
        // und das darf nicht als «Abbestellt» erscheinen.
        if (! $this->abbestellbar($type)) {
            return $oneClick
                ? response(__('Nicht abbestellbar.'), 409)->header('Content-Type', 'text/plain; charset=UTF-8')
                : view('notifications.abbestellen', ['label' => $def['label'], 'mandatory' => true]);
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $benutzer->benutzer_id, 'type' => $type],
            ['frequency' => NotificationCatalog::NEVER],
        );

        if ($oneClick) {
            return response(__('Abbestellt.'), 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return view('notifications.abbestellt', ['label' => $def['label']]);
    }

    /** Nur, wenn der Anlass nicht verpflichtend ist und «nie» als Frequenz zulässt. */
    private function abbestellbar(string $type): bool
    {
        return ! NotificationCatalog::policy($type)['mandatory']
            && in_array(NotificationCatalog::NEVER, NotificationCatalog::get($type)['frequencies'], true);
    }
}
