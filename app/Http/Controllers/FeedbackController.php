<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\FeedbackAnhang;
use App\Models\FeedbackStimme;
use App\Models\NotificationMark;
use App\Models\User;
use App\Services\Feedback\Anhang;
use App\Services\Feedback\Screenshot;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\Messages\FeedbackReceived;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\AppVersion;
use App\Support\Browser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    /** Merker «Feedback-Hinweis weggeklickt», damit er nach dem Login nicht erneut erscheint. */
    public const string HINWEIS_TYP = 'feedback_hinweis';

    public const string HINWEIS_SCHLUESSEL = 'weggeklickt';

    /**
     * Schwebt der einmalige Feedback-Hinweis für die angemeldete Person? Nicht während der Einrichtung:
     * das ist ein geführter Ablauf, und der Hinweis lag dort über «Speichern und weiter».
     */
    public static function hinweisOffen(): bool
    {
        if (! auth()->check() || request()->routeIs('admin.setup')) {
            return false;
        }

        return ! NotificationMark::query()
            ->where('user_id', (int) auth()->id())
            ->where('type', self::HINWEIS_TYP)
            ->where('subject_key', self::HINWEIS_SCHLUESSEL)
            ->exists();
    }

    /**
     * «Meine Meldungen»: nur die eigenen Feedback-Einträge, neueste zuerst.
     */
    public function index(Request $request): View
    {
        $mitStimmen = FeedbackStimme::tabelleVorhanden();

        $meldungen = Feedback::query()
            ->where('benutzer_id', (int) $request->user()->benutzer_id)
            ->when($mitStimmen, fn ($q) => $q->withCount('stimmen'))
            ->orderByDesc('erstellt_am')
            ->paginate(25);

        return view('feedback.index', compact('meldungen', 'mitStimmen'));
    }

    public function store(Request $request, Screenshot $screenshotService, Anhang $anhangService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'kategorie' => ['required', 'in:'.implode(',', array_keys(Feedback::KATEGORIEN))],
            'text' => ['required', 'string', 'min:3', 'max:5000'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:500'],
            'viewport' => ['nullable', 'regex:/^\d{2,5}x\d{2,5}$/'],
            'js_fehler' => ['nullable', 'string', 'max:3000'],
            'technik' => ['nullable', 'string', 'max:2000'],
        ], Screenshot::regeln(), Anhang::regeln()));

        $letzteFehler = $this->letzteJsFehler($validated['js_fehler'] ?? null);

        // Solange die Spalte kategorie den Wert «sonstiges» noch nicht kennt (Migration 2026_09_12_000012
        // auf Prod nicht gelaufen), muss weiterhin der alte Wert «lob» geschrieben werden.
        $kategorie = $validated['kategorie'];
        if ($kategorie === Feedback::KATEGORIE_SONSTIGES && ! Feedback::hatSonstigesWert()) {
            $kategorie = Feedback::KATEGORIE_LOB;
        }

        // Erst alle Dateien ablegen, dann den Datensatz anlegen: Anhang::speichern() weist ein nicht
        // neu kodierbares Bild mit einer ValidationException ab. Lief Feedback::create() vorher,
        // bliebe die Meldung ohne den Anhang zurück, obwohl der Nutzer einen Fehler sieht.
        // Scheitert eine Datei, verschwinden die bereits abgelegten wieder.
        $gespeicherteDateien = [];
        try {
            $screenshot = $request->hasFile('screenshot')
                ? $screenshotService->speichern($request->file('screenshot'))
                : null;
            if ($screenshot !== null) {
                $gespeicherteDateien[] = $screenshot['pfad'];
            }

            $anhaenge = [];
            if (Feedback::hatAnhaengeTabelle()) {
                foreach ($request->file('anhaenge', []) as $datei) {
                    $anhaenge[] = $gespeichert = $anhangService->speichern($datei);
                    $gespeicherteDateien[] = $gespeichert['pfad'];
                }
            }
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($gespeicherteDateien);

            throw $e;
        }

        $feedback = Feedback::create([
            'benutzer_id' => (int) $request->user()->benutzer_id,
            'rolle' => $request->user()->rollen()->pluck('name')->first(),
            'kategorie' => $kategorie,
            'text' => $validated['text'],
            'route_name' => $validated['route_name'] ?? null,
            'url' => $validated['url'] ?? null,
            'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 255) : null,
            'browser' => Browser::kurz($request->userAgent()),
            'viewport' => $validated['viewport'] ?? null,
            'js_fehler' => $letzteFehler,
            'technik_details' => Feedback::hatTechnikSpalte() ? $this->technikDetails($validated['technik'] ?? null) : null,
            'screenshot_pfad' => $screenshot['pfad'] ?? null,
            'screenshot_mime' => $screenshot['mime'] ?? null,
            'screenshot_groesse' => $screenshot['groesse'] ?? null,
        ]);

        foreach ($anhaenge as $gespeichert) {
            FeedbackAnhang::create(['feedback_id' => $feedback->feedback_id, ...$gespeichert]);
        }

        $melder = $request->user();
        foreach (Empfaenger::aktiveAdmins() as $admin) {
            Notifier::send($admin, NotificationCatalog::FEEDBACK_RECEIVED, fn () => FeedbackReceived::content($feedback, $melder));
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return redirect()->back()->with('success', __('Danke, deine Meldung ist eingegangen.'));
    }

    /**
     * Ähnliche offene Meldungen zur aktuellen Seite (fürs Widget). Antwortet für fremde/unbekannte
     * Routen immer gleich (0, kein 403), damit sich Route-Namen nicht erraten lassen.
     */
    public function aehnliche(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_name' => ['required', 'string', 'max:150'],
        ]);

        $user = $request->user();

        if (! FeedbackStimme::tabelleVorhanden() || ! $this->seiteErlaubt($user, $validated['route_name'])) {
            return response()->json(['anzahl' => 0, 'meldungen' => []]);
        }

        $meldungen = Feedback::query()
            ->hauptmeldungen()
            ->where('route_name', $validated['route_name'])
            ->whereIn('status', [Feedback::STATUS_OFFEN, Feedback::STATUS_IN_ARBEIT])
            ->where('benutzer_id', '!=', (int) $user->benutzer_id)
            ->withCount('stimmen')
            ->withExists(['stimmen as meine_stimme' => fn ($q) => $q->where('benutzer_id', (int) $user->benutzer_id)])
            ->orderByDesc('stimmen_count')
            ->limit(3)
            ->get();

        return response()->json([
            'anzahl' => $meldungen->count(),
            'meldungen' => $meldungen->map(fn (Feedback $f) => [
                'id' => $f->feedback_id,
                'kategorie_label' => __(Feedback::kategorieLabel($f->kategorie)),
                'datum' => $f->erstellt_am->format('d.m.Y'),
                'stimmen' => (int) $f->stimmen_count,
                'meine' => (bool) $f->meine_stimme,
            ])->values(),
        ]);
    }

    /** Stimme «Betrifft mich auch» für eine fremde, offene Hauptmeldung – idempotent. */
    public function stimmen(Request $request, int $feedback_id): JsonResponse
    {
        abort_unless(FeedbackStimme::tabelleVorhanden(), 404);

        $user = $request->user();

        $feedback = Feedback::query()
            ->hauptmeldungen()
            ->whereIn('status', [Feedback::STATUS_OFFEN, Feedback::STATUS_IN_ARBEIT])
            ->find($feedback_id);

        abort_if($feedback === null, 404);
        abort_if((int) $feedback->benutzer_id === (int) $user->benutzer_id, 422, __('Eigene Meldung'));
        abort_if(! $this->seiteErlaubt($user, $feedback->route_name), 404);

        FeedbackStimme::query()->insertOrIgnore([
            'feedback_id' => $feedback_id,
            'benutzer_id' => (int) $user->benutzer_id,
            'erstellt_am' => now(),
        ]);

        return response()->json([
            'stimmen' => FeedbackStimme::where('feedback_id', $feedback_id)->count(),
            'meine' => true,
        ]);
    }

    /** Eigene Stimme zurückziehen. */
    public function stimmeZurueck(Request $request, int $feedback_id): JsonResponse
    {
        abort_unless(FeedbackStimme::tabelleVorhanden(), 404);

        FeedbackStimme::query()
            ->where('feedback_id', $feedback_id)
            ->where('benutzer_id', (int) $request->user()->benutzer_id)
            ->delete();

        return response()->json([
            'stimmen' => FeedbackStimme::where('feedback_id', $feedback_id)->count(),
            'meine' => false,
        ]);
    }

    /**
     * Hat der Benutzer Zugriff auf die zur Route gehörende Seite? Geprüft über die role:-Middleware
     * der Route (kein Rollen-Middleware = allen eingeloggten Benutzern zugänglich). Ohne Route-Namen
     * oder eine unbekannte Route: nein – das verhindert, dass sich über die Reaktion Route-Namen erraten lassen.
     */
    private function seiteErlaubt(?User $user, ?string $routeName): bool
    {
        if ($user === null || blank($routeName)) {
            return false;
        }

        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return false;
        }

        $rollenMiddleware = collect($route->gatherMiddleware())
            ->first(fn ($m) => is_string($m) && str_starts_with($m, 'role:'));

        if ($rollenMiddleware === null) {
            return true;
        }

        foreach (array_filter(array_map('trim', explode(',', substr($rollenMiddleware, 5)))) as $rolle) {
            if ($user->hasRole($rolle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anhang einer Meldung – nur für Admins und die meldende Person selbst. Content-Disposition
     * attachment, nosniff und eine sandboxende CSP kommen aus App\Services\Feedback\Anhang::ausliefern().
     */
    public function anhang(Request $request, int $feedback_id, int $anhang_id, Anhang $anhangService): StreamedResponse
    {
        abort_unless(Feedback::hatAnhaengeTabelle(), 404);

        $feedback = Feedback::query()->findOrFail($feedback_id);
        $user = $request->user();
        $eigeneMeldung = (int) $feedback->benutzer_id === (int) $user->benutzer_id;
        abort_unless($eigeneMeldung || $user->hasRole('Admin'), 403);

        $anhang = FeedbackAnhang::where('feedback_id', $feedback_id)->findOrFail($anhang_id);

        return $anhangService->ausliefern($anhang);
    }

    /**
     * Bereinigte technische Angaben (Block G): nur bekannte Felder, harte Längenlimiten, ergänzt um die
     * App-Version. Browser/Betriebssystem stehen schon in der eigenen Spalte «browser» (aus der
     * User-Agent-Kennung des Servers), Route/URL/Viewport/js_fehler in ihren eigenen Spalten.
     *
     * @return array<string, mixed>|null
     */
    private function technikDetails(?string $roh): ?array
    {
        if (blank($roh)) {
            return null;
        }

        $daten = json_decode($roh, true);
        if (! is_array($daten)) {
            return null;
        }

        $ergebnis = [];
        foreach (['bildschirm', 'pixelverhaeltnis', 'sprache', 'zeitzone', 'darstellung'] as $schluessel) {
            if (isset($daten[$schluessel]) && is_scalar($daten[$schluessel])) {
                $ergebnis[$schluessel] = mb_substr((string) $daten[$schluessel], 0, 60);
            }
        }
        if (array_key_exists('online', $daten)) {
            $ergebnis['online'] = (bool) $daten['online'];
        }
        if (isset($daten['fehlgeschlagene_requests']) && is_array($daten['fehlgeschlagene_requests'])) {
            $ergebnis['fehlgeschlagene_requests'] = collect($daten['fehlgeschlagene_requests'])
                ->filter(fn ($r) => is_array($r) && isset($r['pfad'], $r['status']) && is_scalar($r['pfad']) && is_scalar($r['status']))
                ->map(fn ($r) => ['pfad' => mb_substr((string) $r['pfad'], 0, 200), 'status' => (int) $r['status']])
                ->take(3)
                ->values()
                ->all();
        }

        $version = AppVersion::commit();
        if ($version !== null) {
            $ergebnis['app_version'] = $version;
        }

        return $ergebnis === [] ? null : $ergebnis;
    }

    /** Login-Hinweis dauerhaft ausblenden (einmal pro Benutzer, serverseitig gemerkt). */
    public function hinweisSchliessen(Request $request): JsonResponse
    {
        NotificationMark::query()->insertOrIgnore([
            'user_id' => (int) $request->user()->benutzer_id,
            'type' => self::HINWEIS_TYP,
            'subject_key' => self::HINWEIS_SCHLUESSEL,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** @return list<string>|null höchstens 3 der letzten JS-Fehler, je auf 300 Zeichen gekürzt. */
    private function letzteJsFehler(?string $roh): ?array
    {
        if (blank($roh)) {
            return null;
        }

        $liste = json_decode($roh, true);
        if (! is_array($liste) || $liste === []) {
            return null;
        }

        $gekuerzt = array_map(
            fn ($eintrag) => mb_substr((string) $eintrag, 0, 300),
            array_slice(array_values(array_filter($liste, 'is_scalar')), -3),
        );

        return $gekuerzt === [] ? null : $gekuerzt;
    }
}
