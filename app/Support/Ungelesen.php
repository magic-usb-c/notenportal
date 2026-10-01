<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Note;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Wann eine Note für eine Person «neu» ist – an einer Stelle, damit Zähler im Dashboard, in der
 * Lernendenliste und auf der Notenseite dieselbe Menge meinen wie die Markierung in der Liste:
 * noch nie gesehen, seither von jemand anderem geändert oder von jemand anderem kommentiert.
 * Was die Person selbst erfasst, korrigiert oder kommentiert hat, ist für sie nicht neu.
 */
final class Ungelesen
{
    /**
     * Bedingung für eine Abfrage über `noten as {$n}` mit links verbundenem `noten_gesehen as {$g}` des Betrachters.
     *
     * @param  int|string  $betrachter  Benutzer-ID oder – für Zählungen über mehrere Personen – die Spalte mit ihr
     * @return Closure(Builder): void
     */
    public static function bedingung(int|string $betrachter, string $n = 'n', string $g = 'g'): Closure
    {
        $nichtVon = static function (Builder $q, string $spalte) use ($betrachter): void {
            is_int($betrachter) ? $q->where($spalte, '!=', $betrachter) : $q->whereColumn($spalte, '!=', $betrachter);
        };

        return static function (Builder $q) use ($n, $g, $nichtVon): void {
            $q->where(function (Builder $nie) use ($n, $g, $nichtVon) {
                $nie->whereNull("{$g}.gesehen_am");
                $nichtVon($nie, "{$n}.erfasst_von_benutzer_id");
            })->orWhere(function (Builder $geaendert) use ($n, $g, $nichtVon) {
                $geaendert->whereColumn("{$n}.aktualisiert_am", '>', "{$g}.gesehen_am")
                    ->where(fn (Builder $von) => $von->whereNull("{$n}.aktualisiert_von_benutzer_id")
                        ->orWhere(fn (Builder $andere) => $nichtVon($andere, "{$n}.aktualisiert_von_benutzer_id")));
            })->orWhereExists(function (Builder $k) use ($n, $g, $nichtVon) {
                $k->select(DB::raw(1))->from('noten_kommentare as k')
                    ->whereColumn('k.note_id', "{$n}.note_id")
                    ->where(fn (Builder $seit) => $seit->whereNull("{$g}.gesehen_am")->orWhereColumn('k.erstellt_am', '>', "{$g}.gesehen_am"));
                $nichtVon($k, 'k.autor_benutzer_id');
            });
        };
    }

    /** Dieselbe Regel für eine geladene Note (Relation `kommentare` geladen). */
    public static function istNeu(Note $note, ?Carbon $gesehenAm, int $betrachter): bool
    {
        if ($gesehenAm === null && (int) $note->erfasst_von_benutzer_id !== $betrachter) {
            return true;
        }
        if ($gesehenAm !== null && $note->aktualisiert_am?->gt($gesehenAm) && (int) $note->aktualisiert_von_benutzer_id !== $betrachter) {
            return true;
        }

        return $note->kommentare->contains(fn ($k) => (int) $k->autor_benutzer_id !== $betrachter
            && ($gesehenAm === null || $k->erstellt_am->gt($gesehenAm)));
    }
}
