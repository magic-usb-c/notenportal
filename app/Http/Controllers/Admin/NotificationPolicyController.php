<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationPolicy;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin-Regeln je Benachrichtigungsanlass: aktiv, verpflichtend, Standard-Frequenz, Parameter. */
class NotificationPolicyController extends Controller
{
    public function index(): View
    {
        $katalog = NotificationCatalog::all();
        $anlaesse = [];
        foreach ($katalog as $type => $def) {
            $policy = NotificationCatalog::policy($type);
            $anlaesse[$type] = [
                ...$def,
                'enabled' => $policy['enabled'],
                'mandatory' => $policy['mandatory'],
                'frequency' => $policy['frequency'],
                'paramWerte' => $policy['params'],
            ];
        }

        return view('admin.notifications.index', [
            'gruppen' => NotificationCatalog::GROUPS,
            'anlaesse' => $anlaesse,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $katalog = NotificationCatalog::all();
        $rules = ['policies' => ['required', 'array']];
        foreach ($katalog as $type => $def) {
            if ($def['locked'] ?? false) {
                continue;
            }
            $rules["policies.{$type}.enabled"] = ['nullable', 'boolean'];
            $rules["policies.{$type}.mandatory"] = ['nullable', 'boolean'];
            $rules["policies.{$type}.frequency"] = ['nullable', Rule::in($def['frequencies'])];
            foreach ($def['params'] as $name => $p) {
                $rules["policies.{$type}.params.{$name}"] = ['nullable', 'integer', 'min:'.$p['min'], 'max:'.$p['max']];
            }
        }

        $validated = $request->validate($rules);

        foreach ($validated['policies'] ?? [] as $type => $daten) {
            if (! NotificationCatalog::exists($type)) {
                continue;
            }
            $def = $katalog[$type];
            if ($def['locked'] ?? false) {
                continue;
            }

            $params = [];
            foreach ($def['params'] as $name => $p) {
                $params[$name] = (int) ($daten['params'][$name] ?? $p['default']);
            }

            NotificationPolicy::updateOrCreate(['type' => $type], [
                'enabled' => (bool) ($daten['enabled'] ?? false),
                'mandatory' => (bool) ($daten['mandatory'] ?? false),
                'frequency' => $daten['frequency'] ?? $def['frequency'],
                'params' => $params,
            ]);
        }
        NotificationCatalog::forget();

        return redirect()->route('admin.notifications.index')->with('success', __('Benachrichtigungen gespeichert.'));
    }
}
