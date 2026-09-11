<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Eigene Benachrichtigungen: nur Anlässe, die für die Rolle relevant und vom
 * Admin aktiviert sind (NotificationCatalog::forUser). Verpflichtende Anlässe
 * werden angezeigt, aber nie über diesen Weg verändert.
 */
class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $anlaesse = NotificationCatalog::forUser($user);

        $gruppen = [];
        foreach ($anlaesse as $type => $def) {
            $policy = NotificationCatalog::policy($type);
            $gruppen[$def['group']][] = [
                'type' => $type,
                ...$def,
                'mandatory' => $policy['mandatory'],
                'aktuell' => Notifier::frequencyFor($user, $type),
            ];
        }

        return view('notifications.settings', [
            'gruppen' => $gruppen,
            'gruppenLabels' => NotificationCatalog::GROUPS,
            'frequenzen' => NotificationCatalog::FREQUENCIES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $anlaesse = NotificationCatalog::forUser($user);

        $validated = $request->validate([
            'frequenz' => ['array'],
            'frequenz.*' => ['string', Rule::in(array_keys(NotificationCatalog::FREQUENCIES))],
        ]);

        foreach ($validated['frequenz'] ?? [] as $type => $frequenz) {
            $def = $anlaesse[$type] ?? null;
            if (! $def || NotificationCatalog::policy($type)['mandatory'] || ! in_array($frequenz, $def['frequencies'], true)) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->benutzer_id, 'type' => $type],
                ['frequency' => $frequenz],
            );
        }

        return redirect()->route('notifications.settings')->with('success', __('Benachrichtigungen gespeichert.'));
    }
}
