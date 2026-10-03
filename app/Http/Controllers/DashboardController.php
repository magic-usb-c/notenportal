<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Noten\NoteService;
use App\Services\Uebersicht;
use App\Support\Darstellung;
use App\Support\DashboardKarten;
use App\Support\Einrichtung;
use App\Support\StatistikAntwort;
use App\Support\StatistikDaten;
use App\Support\StatistikFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly Uebersicht $uebersicht,
        private readonly NoteService $noteService,
        private readonly StatistikDaten $statistik,
    ) {}

    /** Verlauf (S1) und Wo stehe ich (S2): ein Filterformular, ein Endpunkt; JSON nur mit den Statistikdaten. */
    public function lernender(Request $request): Response|JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);

        // Lernenden-ID nur aus der Session; ?lernender=… und ?lernender_id=… werden nicht gelesen
        $geladen = $this->statistik->lernender((int) $lernender->lernender_id);
        $filter = StatistikFilter::aus($request, $this->statistik->regelnLernender($geladen['auswertung']), StatistikDaten::STANDARD_LERNENDER);
        $paket = StatistikAntwort::paket(
            $filter,
            $this->statistik->verlauf($filter, $geladen['leistungen'], $geladen['auswertung'], $lernender),
            $this->statistik->wostehe($filter, $geladen['auswertung']),
        );
        if ($request->wantsJson()) {
            return StatistikAntwort::json($paket);
        }

        $daten = $this->uebersicht->lernender($lernender->load('lehrberuf'), $request->user());
        $daten['drawerFehler'] = $this->noteService->drawerNachFehler($request, $lernender);
        $daten['sichtbar'] = $this->kartenSichtbar($request->user());
        $daten['statistik'] = $paket;

        return StatistikAntwort::view('dashboards.lernender', $daten);
    }

    /** Wer hat sich bewegt (S7): Hantel je betreute Person, Vorsemester gegen aktuell. */
    public function berufsbildner(Request $request): Response|JsonResponse
    {
        abort_unless($request->user()->berufsbildner, 403);

        $filter = [
            'sort' => in_array($request->input('sort'), Uebersicht::BB_SORTIERUNGEN, true) ? $request->input('sort') : null,
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
        ];

        // Berufsbildner nur über Lernender::sichtbarFuer (in Uebersicht::berufsbildner)
        $daten = $this->uebersicht->berufsbildner($request->user(), $filter['sort'], $filter['dir']);
        $statistikFilter = StatistikFilter::aus($request, [
            'status' => ['alle', 'kritisch'],
            'kategorie' => ['id' => array_map('intval', array_keys(Konfiguration::ausDb()->kategorien))],
            'sort' => [...Uebersicht::BB_SORTIERUNGEN, 'delta'],
        ], ['status' => 'alle', 'sort' => 'delta']);
        $paket = StatistikAntwort::paket($statistikFilter, $this->statistik->hantelPersonen(
            $daten['zeilen'],
            $statistikFilter->wert('kategorie'),
            (string) $statistikFilter->wert('status'),
            $statistikFilter->wert('sort') === 'name' ? 'name' : 'delta',
        ));
        if ($request->wantsJson()) {
            return StatistikAntwort::json($paket);
        }

        return StatistikAntwort::view('dashboards.berufsbildner', [
            ...$daten,
            'filter' => $filter,
            'sichtbar' => $this->kartenSichtbar($request->user()),
            'statistik' => $paket,
        ]);
    }

    /** Erfassung je Woche (S9): nur Betriebssummen, 12, 26 oder 52 Wochen. */
    public function admin(Request $request): Response|JsonResponse|RedirectResponse
    {
        if (Einrichtung::offen()) {
            return redirect()->route('admin.setup');
        }

        $filter = StatistikFilter::aus($request, ['zeitraum' => ['12w', '26w', '52w']], ['zeitraum' => '12w']);
        $daten = $this->uebersicht->admin((int) $filter->wert('zeitraum'));
        $paket = StatistikAntwort::paket($filter, $this->statistik->erfassung($daten['aktivitaet']));
        if ($request->wantsJson()) {
            return StatistikAntwort::json($paket);
        }

        return StatistikAntwort::view('dashboards.admin', [
            ...$daten,
            'sichtbar' => $this->kartenSichtbar($request->user()),
            'statistik' => $paket,
        ]);
    }

    /**
     * Sichtbarkeit je Dashboard-Karte (App\Support\DashboardKarten) nach persönlicher Präferenz.
     * Die Daten werden weiterhin berechnet (Uebersicht liefert sie in einem Rutsch) – nur das
     * Rendern der ausgeblendeten Karte entfällt (siehe docs/audit-backlog.md).
     *
     * @return array<string, bool>
     */
    private function kartenSichtbar(User $user): array
    {
        $rolle = DashboardKarten::rolleFuer($user);
        $ausgeblendet = Darstellung::fuer($user)['karten_ausgeblendet'];

        $sichtbar = [];
        foreach (DashboardKarten::fuerRolle($rolle) as $schluessel => $bezeichnung) {
            $sichtbar[$schluessel] = DashboardKarten::sichtbar($ausgeblendet, $schluessel);
        }

        return $sichtbar;
    }
}
