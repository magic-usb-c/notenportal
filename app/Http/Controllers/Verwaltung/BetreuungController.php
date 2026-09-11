<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Berufsbildner;
use App\Models\Betreuung;
use App\Services\Notifications\Messages\LearnerAssigned;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Betreuung zuweisen/übergeben und beenden. Ein BB darf an einen anderen BB übergeben. */
class BetreuungController extends VerwaltungController
{
    public function store(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('betreuungVerwalten', $lernender);

        $daten = $request->validate([
            'berufsbildner_id' => ['required', 'integer', Rule::exists('berufsbildner', 'berufsbildner_id')->whereNull('geloescht_am')],
            'gueltig_von' => ['required', 'date'],
        ]);

        $von = Carbon::parse($daten['gueltig_von'])->startOfDay();

        // Die neue Betreuung ersetzt alles ab $von: frühere werden am Vortag beendet,
        // solche, die erst ab $von beginnen würden, entfallen.
        DB::transaction(function () use ($lernender, $daten, $von) {
            $lernender->betreuungen()
                ->where(fn ($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $von->toDateString()))
                ->get()
                ->each(fn (Betreuung $b) => $b->gueltig_von->lt($von)
                    ? $b->update(['gueltig_bis' => $von->copy()->subDay()->toDateString()])
                    : $b->delete());

            $lernender->betreuungen()->create([
                'berufsbildner_id' => (int) $daten['berufsbildner_id'],
                'gueltig_von' => $von->toDateString(),
                'gueltig_bis' => null,
            ]);
        });

        $neuerBerufsbildner = Berufsbildner::with('benutzer')->find($daten['berufsbildner_id']);
        if ($neuerBerufsbildner?->benutzer) {
            $lernender->loadMissing('benutzer', 'lehrberuf');
            Notifier::send($neuerBerufsbildner->benutzer, NotificationCatalog::LEARNER_ASSIGNED, fn () => LearnerAssigned::content($lernender));
        }

        return $this->zurueckZumLernenden($request, $lernender_id, __('Betreuung eingetragen.'));
    }

    public function beenden(Request $request, int $lernender_id, int $betreuung_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('betreuungVerwalten', $lernender);

        $betreuung = $lernender->betreuungen()->whereKey($betreuung_id)->firstOrFail();
        $heute = today();

        if ($betreuung->gueltig_bis && $betreuung->gueltig_bis->lt($heute)) {
            return back()->with('error', __('Betreuung ist bereits beendet.'));
        }

        // Sofort wirksam: endet gestern; eine noch nicht begonnene Betreuung entfällt.
        $betreuung->gueltig_von->lt($heute)
            ? $betreuung->update(['gueltig_bis' => $heute->copy()->subDay()->toDateString()])
            : $betreuung->delete();

        return $this->zurueckZumLernenden($request, $lernender_id, __('Betreuung beendet.'));
    }
}
