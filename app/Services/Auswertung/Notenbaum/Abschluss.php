<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use App\Models\Lernender;
use App\Models\NotenbaumPosition;
use App\Services\Auswertung\Auswertung;
use App\Services\Auswertung\NotenQuelle;
use App\Support\Protokoll;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abschluss eines Lernenden: Ergebnisse seiner Notenbäume (QV, Berufsmaturität) und die von Hand
 * erfassten Positionen (IPA, Schlussarbeit, Abschlussprüfungen). Rechnet nichts selbst – die Werte
 * kommen aus der Auswertung, damit Dashboard, Cockpit und diese Seite dieselbe Zahl zeigen.
 */
final class Abschluss
{
    public function __construct(
        private readonly NotenQuelle $quelle,
        private readonly BaumLader $lader,
    ) {}

    public function auswertung(int $lernenderId): Auswertung
    {
        return $this->quelle->auswertung($lernenderId);
    }

    /**
     * Daten der Abschluss-Seite.
     *
     * @return array{ergebnisse: list<BaumErgebnis>, positionen: array<int, NotenbaumPosition>}
     */
    public function seite(int $lernenderId): array
    {
        return [
            'ergebnisse' => array_values($this->auswertung($lernenderId)->baeume),
            'positionen' => $this->positionen($lernenderId),
        ];
    }

    /** Hauptbaum (Lehrberuf) aus einer Auswertung – für die Beschriftung «QV-Prognose» in Übersichten. */
    public static function hauptergebnis(Auswertung $a): ?BaumErgebnis
    {
        foreach ($a->baeume as $e) {
            if ($e->baum->bezug === Baum::LEHRBERUF) {
                return $e;
            }
        }

        return null;
    }

    /** @return array<int, NotenbaumPosition> nach knoten_id */
    public function positionen(int $lernenderId): array
    {
        return NotenbaumPosition::where('lernender_id', $lernenderId)->get()->keyBy('knoten_id')->all();
    }

    /**
     * Manuelle Blätter der aktiven Bäume dieses Lernenden – nur diese darf ein Formular setzen.
     *
     * @return array<int, Knoten> nach knoten_id
     */
    public function manuelleKnoten(int $lernenderId): array
    {
        $out = [];
        foreach ($this->lader->fuerLernende([$lernenderId])[$lernenderId] ?? [] as $baum) {
            foreach ($baum->alle() as $k) {
                if ($k->typ === Knoten::MANUELL && ! $k->entfaellt) {
                    $out[$k->id] = $k;
                }
            }
        }

        return $out;
    }

    /**
     * Werte übernehmen: Zahl setzt die Position, leer entfernt sie. Knoten ausserhalb der aktiven Bäume
     * des Lernenden werden abgelehnt, nicht still übergangen.
     *
     * @param  array<int|string, mixed>  $werte  knoten_id => Eingabe
     * @return int Anzahl geänderter Positionen
     */
    public function speichern(int $lernenderId, array $werte, int $benutzerId): int
    {
        $erlaubt = $this->manuelleKnoten($lernenderId);
        $fehler = [];
        $sauber = [];
        foreach ($werte as $knotenId => $eingabe) {
            $knotenId = (int) $knotenId;
            $feld = 'werte.'.$knotenId;
            if (! isset($erlaubt[$knotenId])) {
                $fehler[$feld] = __('Diese Position gehört nicht zum Abschluss dieser Person.');

                continue;
            }
            if ($eingabe !== null && ! is_scalar($eingabe)) {
                $fehler[$feld] = __('Bitte eine Note zwischen 1 und 6 eingeben.');

                continue;
            }
            $text = str_replace(',', '.', trim((string) $eingabe));
            if ($text === '') {
                $sauber[$knotenId] = null;

                continue;
            }
            $zahl = is_numeric($text) ? (float) $text : null;
            if ($zahl === null || $zahl < 1 || $zahl > 6 || abs(round($zahl * 20) - $zahl * 20) > 1e-6) {
                $fehler[$feld] = __('Bitte eine Note zwischen 1 und 6 eingeben.');

                continue;
            }
            $sauber[$knotenId] = round($zahl, 2);
        }
        if ($fehler !== []) {
            throw ValidationException::withMessages($fehler);
        }

        $geaendert = [];
        DB::transaction(function () use ($sauber, $lernenderId, $benutzerId, &$geaendert) {
            // Die Bäume der Knoten sperren (geteilt) und prüfen, ob sie noch aktiv sind: schaltet ein Admin
            // zwischen Formular und Speichern um, landete der Wert sonst unsichtbar im abgelösten Baum.
            // Ein Wechsel, der erst jetzt kommt, wartet auf dieses Speichern und nimmt den Wert mit.
            $baeume = DB::table('notenbaum_knoten')->whereIn('knoten_id', array_keys($sauber))->distinct()->pluck('baum_id')->all();
            $aktiv = DB::table('notenbaeume')->whereIn('baum_id', $baeume)->orderBy('baum_id')->sharedLock()->pluck('aktiv');
            if ($aktiv->contains(fn ($a) => ! $a)) {
                throw new AbschlussVeraltet;
            }
            $bestehend = $this->positionen($lernenderId);
            foreach ($sauber as $knotenId => $wert) {
                $alt = $bestehend[$knotenId] ?? null;
                $vorher = $alt?->note_wert;
                if ($wert === null) {
                    if ($alt) {
                        $alt->delete();
                        $geaendert[$knotenId] = [$vorher, null];
                    }

                    continue;
                }
                if ($alt && abs($alt->note_wert - $wert) < 1e-9) {
                    continue;
                }
                if ($alt) {
                    $alt->update(['note_wert' => $wert, 'aktualisiert_von_benutzer_id' => $benutzerId]);
                } else {
                    NotenbaumPosition::create(['lernender_id' => $lernenderId, 'knoten_id' => $knotenId, 'note_wert' => $wert,
                        'datum' => now()->toDateString(), 'erfasst_von_benutzer_id' => $benutzerId]);
                }
                $geaendert[$knotenId] = [$vorher, $wert];
            }
        });

        if ($geaendert !== []) {
            $namen = array_map(fn (Knoten $k) => $k->name, $erlaubt);
            Protokoll::schreiben(Protokoll::NOTENBAUM_POSITION_GEAENDERT, Lernender::find($lernenderId), [
                'positionen' => array_map(fn ($id, $w) => ['position' => $namen[$id], 'vorher' => $w[0], 'nachher' => $w[1]],
                    array_keys($geaendert), $geaendert),
            ]);
        }

        return count($geaendert);
    }
}
