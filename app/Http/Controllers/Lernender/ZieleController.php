<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\Ziel;
use App\Services\Auswertung\Rechner;
use App\Services\Auswertung\Zielgroesse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Ziele des Lernenden (je Zielgrösse höchstens eines). */
class ZieleController extends Controller
{
    public function __construct(private readonly Rechner $rechner) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $daten = $request->validate([
            'ziel' => ['required', 'string', 'max:40'],
            'zielwert' => ['required', 'numeric', 'min:1', 'max:6'],
        ]);

        try {
            $z = Zielgroesse::parse($daten['ziel']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['ziel' => __('Ungültige Auswahl.')]);
        }

        $katalog = $this->rechner->katalog($lernender);
        $gueltig = $z->semesterId === null && in_array($z->ebene, Ziel::EBENEN, true) && match ($z->ebene) {
            'kategorie' => collect($katalog['kategorien'])->contains('id', $z->id),
            'fach' => collect($katalog['faecher'])->contains('id', $z->id),
            'modul' => collect($katalog['module'])->contains('id', $z->id),
            default => true,
        };
        if (! $gueltig) {
            throw ValidationException::withMessages(['ziel' => __('Als Ziel lassen sich Gesamtschnitt, Kategorie, Fach oder Modul speichern.')]);
        }

        $zielwert = round((float) $daten['zielwert'], 2);
        Ziel::updateOrCreate(
            ['lernender_id' => $lernender->lernender_id, ...Ziel::spaltenFuer($z)],
            ['zielwert' => $zielwert]
        );

        // Der Rechner speichert per fetch und führt seine Zielliste selbst nach (das Szenario bleibt erhalten)
        if ($request->expectsJson()) {
            return response()->json(['message' => __('Ziel gespeichert.'), 'ziele' => $this->rechner->ziele($lernender)]);
        }

        // Ohne JavaScript zurück zum Rechner mit dem soeben gespeicherten Wert (nicht auf eine ältere URL mit anderem zielwert)
        return redirect()->route('learner.grades.calculator', ['ziel' => (string) $z, 'zielwert' => $zielwert])
            ->with('success', __('Ziel gespeichert.'));
    }

    public function destroy(Request $request, int $ziel_id): RedirectResponse|JsonResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->ziele()->whereKey($ziel_id)->firstOrFail()->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('Ziel entfernt.'), 'ziele' => $this->rechner->ziele($lernender)]);
        }

        return back()->with('success', __('Ziel entfernt.'));
    }
}
