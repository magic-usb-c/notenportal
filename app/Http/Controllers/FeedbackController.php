<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\NotificationMark;
use App\Services\Feedback\Screenshot;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\Messages\FeedbackReceived;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Browser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    /** Merker «Feedback-Hinweis weggeklickt», damit er nach dem Login nicht erneut erscheint. */
    public const string HINWEIS_TYP = 'feedback_hinweis';

    public const string HINWEIS_SCHLUESSEL = 'weggeklickt';

    /**
     * «Meine Meldungen»: nur die eigenen Feedback-Einträge, neueste zuerst.
     */
    public function index(Request $request): View
    {
        $meldungen = Feedback::query()
            ->where('benutzer_id', (int) $request->user()->benutzer_id)
            ->orderByDesc('erstellt_am')
            ->paginate(25);

        return view('feedback.index', compact('meldungen'));
    }

    public function store(Request $request, Screenshot $screenshotService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'kategorie' => ['required', 'in:'.implode(',', array_keys(Feedback::KATEGORIEN))],
            'text' => ['required', 'string', 'min:3', 'max:5000'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:500'],
            'viewport' => ['nullable', 'regex:/^\d{2,5}x\d{2,5}$/'],
            'js_fehler' => ['nullable', 'string', 'max:3000'],
        ], Screenshot::regeln()));

        $letzteFehler = $this->letzteJsFehler($validated['js_fehler'] ?? null);

        $feedback = Feedback::create([
            'benutzer_id' => (int) $request->user()->benutzer_id,
            'rolle' => $request->user()->rollen()->pluck('name')->first(),
            'kategorie' => $validated['kategorie'],
            'text' => $validated['text'],
            'route_name' => $validated['route_name'] ?? null,
            'url' => $validated['url'] ?? null,
            'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 255) : null,
            'browser' => Browser::kurz($request->userAgent()),
            'viewport' => $validated['viewport'] ?? null,
            'js_fehler' => $letzteFehler,
        ]);

        if ($request->hasFile('screenshot')) {
            $gespeichert = $screenshotService->speichern($request->file('screenshot'));
            $feedback->update([
                'screenshot_pfad' => $gespeichert['pfad'],
                'screenshot_mime' => $gespeichert['mime'],
                'screenshot_groesse' => $gespeichert['groesse'],
            ]);
        }

        foreach (Empfaenger::aktiveAdmins() as $admin) {
            Notifier::send($admin, NotificationCatalog::FEEDBACK_RECEIVED, FeedbackReceived::content($feedback, $request->user()));
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return redirect()->back()->with('success', 'Danke, deine Meldung ist eingegangen.');
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
