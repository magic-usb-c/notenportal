<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wechsel des aktiven Notenbaums eines Lehrberufs bzw. Bildungsgangs. Erfasste Positionen (IPA, AB …)
 * hängen an Knoten-IDs; beim Wechsel ziehen sie über den Knoten-Code in den neuen Baum um, damit ein
 * Reimport oder das Zurückschalten auf einen alten Stand keine Noten verliert.
 *
 * Beim Aktivieren sammelt der Baum für jeden seiner Codes die Positionen aus allen Bäumen desselben
 * Ziels ein, nicht nur aus dem Vorgänger: Kannte ein Zwischenstand einen Code nicht, liegt die Note noch
 * im Baum davor. Gibt es für eine Lernende denselben Code mehrfach, gilt der zuletzt erfasste Wert
 * (aktualisiert_am der Position – nicht des Baums, das ändert auch das Umbenennen); die überholten
 * fallen weg, sonst tauchten sie beim nächsten Wechsel wieder auf.
 *
 * Umziehen statt kopieren: Eine Position liegt danach nur im aktiven Baum. Eine dort geleerte Note
 * bleibt beim Zurückschalten leer. Codes, die es im aktiven Baum nicht gibt, bleiben im alten Baum und
 * sperren dessen Löschen (verlorenePositionen).
 */
final class BaumWechsel
{
    /**
     * Baum aktivieren und den bisher aktiven Baum desselben Ziels deaktivieren. Läuft in einer Transaktion.
     *
     * @return int umgezogene Positionen
     */
    public static function aktivieren(int $baumId): int
    {
        // Alle Bäume des Ziels in einem Zug und nach baum_id sperren: erst den Zielbaum und dann den Rest
        // zu sperren, liess zwei gleichzeitige Wechsel über Kreuz aufeinander warten (Deadlock). Was dann
        // noch kollidiert, etwa zwei Importe, wiederholt DB::transaction.
        return DB::transaction(function () use ($baumId) {
            $baum = DB::table('notenbaeume')->where('baum_id', $baumId)->first()
                ?? throw new RuntimeException('Notenbaum '.$baumId.' fehlt.');
            $baeume = self::gleichesZiel($baum)->orderBy('baum_id')->lockForUpdate()->get(['baum_id', 'aktiv']);
            $vorher = $baeume->where('aktiv', true)->where('baum_id', '!=', $baumId)->pluck('baum_id')->map(fn ($id) => (int) $id);

            DB::table('notenbaeume')->whereIn('baum_id', $vorher->all())->update(['aktiv' => false, 'aktualisiert_am' => now()]);
            DB::table('notenbaeume')->where('baum_id', $baumId)->update(['aktiv' => true, 'aktualisiert_am' => now()]);

            return self::positionenSammeln($baumId, $baeume->pluck('baum_id')->map(fn ($id) => (int) $id)->all(), $vorher->all());
        }, 3);
    }

    /**
     * Positionen, die beim Löschen verloren gingen: erfasst in diesem Baum und im aktiven Baum desselben
     * Ziels nicht unter demselben Code vorhanden. Ein aktiver Baum verliert alle seine Positionen.
     */
    public static function verlorenePositionen(int $baumId): int
    {
        $baum = DB::table('notenbaeume')->where('baum_id', $baumId)->first();
        if ($baum === null) {
            return 0;
        }
        $positionen = DB::table('notenbaum_positionen as p')->join('notenbaum_knoten as k', 'k.knoten_id', '=', 'p.knoten_id')
            ->where('k.baum_id', $baumId)->get(['p.lernender_id', 'p.note_wert', 'k.code']);
        if ($baum->aktiv || $positionen->isEmpty()) {
            return $positionen->count();
        }

        $aktiv = self::gleichesZiel($baum)->where('aktiv', true)->value('baum_id');
        $dort = $aktiv === null ? collect() : DB::table('notenbaum_positionen as p')->join('notenbaum_knoten as k', 'k.knoten_id', '=', 'p.knoten_id')
            ->where('k.baum_id', $aktiv)->get(['p.lernender_id', 'k.code'])->map(fn ($p) => $p->lernender_id.':'.$p->code)->flip();

        return $positionen->reject(fn ($p) => $dort->has($p->lernender_id.':'.$p->code))->count();
    }

    /** Bäume desselben Lehrberufs bzw. Bildungsgangs. */
    private static function gleichesZiel(object $baum): Builder
    {
        return DB::table('notenbaeume')->where('bezug', $baum->bezug)
            ->when($baum->bezug === Baum::LEHRBERUF, fn ($q) => $q->where('lehrberuf_id', $baum->lehrberuf_id))
            ->when($baum->bezug === Baum::BILDUNGSGANG, fn ($q) => $q->where('track_typ', $baum->track_typ));
    }

    /**
     * Für jeden manuellen Code des Zielbaums je Lernende/r die zuletzt erfasste Position aus allen Bäumen
     * des Ziels in den Zielbaum holen und die überholten löschen. Gleichstand (gleiche Sekunde): der bisher
     * aktive Baum vor dem Zielbaum vor den übrigen, dann die jüngere Position.
     *
     * @param  list<int>  $baeume  alle Bäume desselben Ziels, der Zielbaum eingeschlossen
     * @param  list<int>  $vorher  bisher aktive Bäume
     */
    private static function positionenSammeln(int $nach, array $baeume, array $vorher): int
    {
        $ziel = DB::table('notenbaum_knoten')->where('baum_id', $nach)->where('typ', Knoten::MANUELL)->pluck('knoten_id', 'code');
        if ($ziel->isEmpty()) {
            return 0;
        }
        // Sperrend lesen: eine gewöhnliche Abfrage sähe den Stand vom Beginn der Transaktion (REPEATABLE READ),
        // beim Import also vor dessen erster Abfrage. Eine Note, die eine Lernende inzwischen gespeichert hat,
        // bliebe dann im abgelösten Baum liegen. Die sperrende Abfrage liest den neuesten bestätigten Stand.
        $positionen = DB::table('notenbaum_positionen as p')->join('notenbaum_knoten as k', 'k.knoten_id', '=', 'p.knoten_id')
            ->whereIn('k.baum_id', $baeume)->where('k.typ', Knoten::MANUELL)->whereIn('k.code', $ziel->keys()->all())
            ->lockForUpdate()->get(['p.position_id', 'p.lernender_id', 'p.knoten_id', 'p.aktualisiert_am', 'k.baum_id', 'k.code']);
        $rang = fn (object $p) => in_array((int) $p->baum_id, $vorher, true) ? 2 : ((int) $p->baum_id === $nach ? 1 : 0);
        $anzahl = 0;

        foreach ($positionen->groupBy(fn ($p) => $p->lernender_id.'|'.$p->code) as $gruppe) {
            $sieger = $gruppe->sort(fn ($x, $y) => [$y->aktualisiert_am, $rang($y), $y->position_id] <=> [$x->aktualisiert_am, $rang($x), $x->position_id])->first();
            $ueberholt = $gruppe->pluck('position_id')->reject(fn ($id) => $id === $sieger->position_id)->all();
            // Zuerst die überholten, sonst verletzt der Umzug den Schlüssel (Lernende, Knoten)
            DB::table('notenbaum_positionen')->whereIn('position_id', $ueberholt)->delete();
            $zielId = (int) $ziel[$sieger->code];
            if ((int) $sieger->knoten_id !== $zielId) {
                // aktualisiert_am ausdrücklich behalten: Umziehen ist kein Erfassen, und es entscheidet den nächsten Wechsel
                $anzahl += DB::table('notenbaum_positionen')->where('position_id', $sieger->position_id)
                    ->update(['knoten_id' => $zielId, 'aktualisiert_am' => DB::raw('aktualisiert_am')]);
            }
        }

        return $anzahl;
    }
}
