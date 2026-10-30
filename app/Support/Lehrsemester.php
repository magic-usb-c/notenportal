<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Lernender;
use App\Services\Auswertung\Konfiguration;
use Illuminate\Support\Facades\DB;

/**
 * Relative Semesternummer eines Lernenden: sein 1. Semester ist dasjenige, das den
 * Lehrbeginn enthält (oder, falls der Lehrbeginn zwischen zwei Semestern liegt, das
 * erste danach beginnende). Jedes weitere Semester zählt danach hoch.
 *
 * Wer 24/25-1 begonnen hat, ist in 26/27-1 im 5. Semester; wer 26/27-1 begonnen hat,
 * ist dort im 1. Semester. Semester vor dem Lehrbeginn liefern null (neutraler Name
 * als Fallback, siehe Konfiguration::semesterName()).
 */
final class Lehrsemester
{
    /** @var array<int, ?int> Cache je Lernender: Sortierung von dessen 1. Semester. */
    private static array $ersteSortierung = [];

    public static function nummer(Lernender|int $lernender, int $semesterId): ?int
    {
        $lernenderId = $lernender instanceof Lernender ? (int) $lernender->lernender_id : $lernender;

        if (! array_key_exists($lernenderId, self::$ersteSortierung)) {
            $lehrbeginn = $lernender instanceof Lernender
                ? $lernender->lehrbeginn?->toDateString()
                : DB::table('lernende')->where('lernender_id', $lernenderId)->value('lehrbeginn');

            self::$ersteSortierung[$lernenderId] = $lehrbeginn !== null ? self::sortierungDesErstenSemesters((string) $lehrbeginn) : null;
        }

        $erste = self::$ersteSortierung[$lernenderId];
        $k = Konfiguration::ausDb();

        if ($erste === null || ! isset($k->semester[$semesterId])) {
            return null;
        }

        // Die Anlege-Wege vergeben Zehnerschritte: Differenz durch die kleinste Schrittweite teilen. Eine gelöschte
        // Lücke (z. B. Sortierung 1, 3, 4) zählt weiterhin als Semester, solange irgendwo zwei Nachbarn den Schritt zeigen.
        $aktuelle = $k->semesterSortierung($semesterId);
        if ($aktuelle < $erste) {
            return null;
        }
        $n = intdiv($aktuelle - $erste, self::schrittweite($k->semester)) + 1;

        return $n >= 1 ? $n : null;
    }

    public static function name(int $n): string
    {
        return __(':n. Semester', ['n' => $n]);
    }

    /**
     * Lehrbeginn für mehrere Lernende in einem Rutsch vorladen (ein Query statt einem pro
     * Person), bevor nummer() in einer Schleife über eine Liste aufgerufen wird (z. B.
     * LernstandRechner::fuer()) – verhindert N+1 bei personalisierten Semesternamen in Listen.
     *
     * @param  array<int, ?string>  $lehrbeginnJeLernender  lernender_id => lehrbeginn (Datum) oder null
     */
    public static function vorladen(array $lehrbeginnJeLernender): void
    {
        foreach ($lehrbeginnJeLernender as $lernenderId => $lehrbeginn) {
            $lernenderId = (int) $lernenderId;
            if (array_key_exists($lernenderId, self::$ersteSortierung)) {
                continue;
            }
            self::$ersteSortierung[$lernenderId] = $lehrbeginn !== null ? self::sortierungDesErstenSemesters((string) $lehrbeginn) : null;
        }
    }

    /** Innerhalb desselben Requests neu berechnen (Tests, Stammdaten-Änderungen). */
    public static function vergessen(): void
    {
        self::$ersteSortierung = [];
    }

    /** @param  array<int, array{sortierung: int}>  $semester */
    private static function schrittweite(array $semester): int
    {
        $werte = array_values(array_unique(array_column($semester, 'sortierung')));
        sort($werte);

        $schritt = 0;
        for ($i = 1; $i < count($werte); $i++) {
            $d = $werte[$i] - $werte[$i - 1];
            $schritt = $schritt === 0 ? $d : min($schritt, $d);
        }

        return max(1, $schritt);
    }

    private static function sortierungDesErstenSemesters(string $lehrbeginn): ?int
    {
        $k = Konfiguration::ausDb();

        $enthalten = $k->semesterFuerDatum($lehrbeginn);
        if ($enthalten !== null) {
            return $k->semesterSortierung($enthalten);
        }

        $kandidaten = array_values(array_filter($k->semester, fn (array $s) => $s['start'] >= $lehrbeginn));
        if ($kandidaten === []) {
            return null;
        }
        usort($kandidaten, fn (array $a, array $b) => $a['sortierung'] <=> $b['sortierung']);

        return $kandidaten[0]['sortierung'];
    }
}
