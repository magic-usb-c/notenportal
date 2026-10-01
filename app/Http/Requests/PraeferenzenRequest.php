<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Darstellung;
use App\Support\DashboardKarten;
use App\Support\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Einzelne Darstellungs-Einstellungen, wie sie die Einstellungen (jede Änderung gilt sofort) und die
 * Befehlspalette senden: geprüft werden nur die übergebenen Schlüssel. Ohne bekannten Schlüssel
 * weist ProfileController::preferences() die Anfrage ab.
 */
class PraeferenzenRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();
        $rolle = DashboardKarten::rolleFuer($user);
        $alle = $this->feldRegeln($rolle);
        $regeln = array_intersect_key($alle, $this->all());

        if (isset($regeln['karten'])) {
            $regeln['karten.*'] = ['string', Rule::in(array_keys(DashboardKarten::fuerRolle($rolle)))];
        }

        // «Eigene Farbe» braucht eine Farbe: die mitgesendete oder die schon gespeicherte
        if ($this->input('akzent') === Darstellung::AKZENT_EIGEN && Darstellung::fuer($user)['akzent_eigen'] === null) {
            $regeln['akzent_eigen'] = $alle['akzent_eigen'];
        }

        return $regeln;
    }

    /** @return array<string, list<mixed>> */
    private function feldRegeln(?string $rolle): array
    {
        return [
            'darstellung' => ['required', Rule::in(['system', 'hell', 'dunkel'])],
            'theme' => ['nullable', Rule::in(array_keys(Theme::THEMES))],
            'akzent' => ['nullable', Rule::in([...array_keys(Darstellung::AKZENTE), Darstellung::AKZENT_EIGEN])],
            'akzent_eigen' => ['required', 'regex:/^#[0-9a-f]{6}$/i'],
            'schrift' => ['required', Rule::in(Darstellung::SCHRIFTGROESSEN)],
            'schriftart' => ['required', Rule::in(Darstellung::SCHRIFTARTEN)],
            'bewegung' => ['required', Rule::in(Darstellung::BEWEGUNGEN)],
            'dichte' => ['required', Rule::in(Darstellung::DICHTEN)],
            'diagramm' => ['required', Rule::in(Darstellung::DIAGRAMME)],
            'notenanzeige' => ['required', Rule::in(Darstellung::NOTENANZEIGEN)],
            'ecken' => ['required', Rule::in(Darstellung::ECKEN)],
            'transparenz' => ['required', Rule::in(Darstellung::TRANSPARENZEN)],
            'tastenkuerzel' => ['required', Rule::in(Darstellung::TASTENKUERZEL)],
            'navigation' => ['required', Rule::in(Darstellung::NAVIGATIONEN)],
            'startseite' => ['required', Rule::in(array_keys(Darstellung::STARTSEITEN[$rolle] ?? ['dashboard' => 'dashboard']))],
            // Mindestens eine Karte bleibt sichtbar, sonst stünde das Dashboard leer da
            'karten' => ['required', 'array', 'min:1'],
        ];
    }
}
