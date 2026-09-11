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
        $policy = NotificationCatalog::policy($type);

        return view('notifications.abbestellen', [
            'label' => $def['label'],
            'mandatory' => $policy['mandatory'],
        ]);
    }

    public function store(Request $request, int $user, string $type): View|Response
    {
        $benutzer = User::findOrFail($user);
        abort_unless(NotificationCatalog::exists($type), 404);

        $def = NotificationCatalog::get($type);
        $policy = NotificationCatalog::policy($type);

        if (! $policy['mandatory'] && in_array(NotificationCatalog::NEVER, $def['frequencies'], true)) {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $benutzer->benutzer_id, 'type' => $type],
                ['frequency' => NotificationCatalog::NEVER],
            );
        }

        // RFC 8058: Mailprogramme senden den Body "List-Unsubscribe=One-Click" statt die Seite zu öffnen.
        if (str_contains((string) $request->getContent(), 'List-Unsubscribe=One-Click')) {
            return response('Abbestellt.', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return view('notifications.abbestellt', ['label' => $def['label']]);
    }
}
