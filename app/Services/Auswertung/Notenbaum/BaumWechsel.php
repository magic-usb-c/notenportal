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
 * Umziehen statt kopieren: Eine Position liegt immer nur in einem Baum. So gilt der zuletzt aktive Stand
 * (eine dort geleerte Note taucht beim Zurückschalten nicht wieder auf), und ein Baum, der nie Noten
 * bekommen hat, kann beim Zurückschalten keine löschen. Liegen im Zielbaum Positionen, die die Quelle
 * nicht kennt, bleiben sie. Codes, die es im Zielbaum nicht gibt, bleiben im alten Baum und sperren
 * dessen Löschen (verlorenePositionen).
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
        return DB::transaction(function () use ($baumId) {
            $baum = DB::table('notenbaeume')->where('baum_id', $baumId)->lockForUpdate()->first()
                ?? throw new RuntimeException('Notenbaum '.$baumId.' fehlt.');
            $vorher = self::gleichesZiel($baum)->where('aktiv', true)->where('baum_id', '!=', $baumId)->pluck('baum_id')->map(fn ($id) => (int) $id);
            // Ist gerade keiner aktiv (von Hand abgeschaltet), ziehen die Noten aus den abgeschalteten
            // Bäumen um, ältester zuerst, damit der zuletzt abgeschaltete gewinnt.
            $quellen = $vorher->isNotEmpty() ? $vorher : self::gleichesZiel($baum)->where('baum_id', '!=', $baumId)
                ->whereExists(fn ($q) => $q->from('notenbaum_positionen as p')->join('notenbaum_knoten as k', 'k.knoten_id', '=', 'p.knoten_id')
                    ->whereColumn('k.baum_id', 'notenbaeume.baum_id'))
                ->orderBy('aktualisiert_am')->orderBy('baum_id')->pluck('baum_id')->map(fn ($id) => (int) $id);

            DB::table('notenbaeume')->whereIn('baum_id', $vorher->all())->update(['aktiv' => false, 'aktualisiert_am' => now()]);
            DB::table('notenbaeume')->where('baum_id', $baumId)->update(['aktiv' => true, 'aktualisiert_am' => now()]);

            return $quellen->sum(fn (int $von) => self::positionenUmziehen($von, $baumId));
        });
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
     * Manuelle Knoten mit gleichem Code: Positionen des alten Baums in den neuen verschieben. Je Lernende/r
     * ersetzt eine Position der Quelle die des Ziels; was die Quelle nicht hat, bleibt im Ziel stehen.
     */
    private static function positionenUmziehen(int $von, int $nach): int
    {
        $quelle = DB::table('notenbaum_knoten')->where('baum_id', $von)->where('typ', Knoten::MANUELL)->pluck('knoten_id', 'code');
        $ziel = DB::table('notenbaum_knoten')->where('baum_id', $nach)->where('typ', Knoten::MANUELL)->pluck('knoten_id', 'code');
        $anzahl = 0;

        foreach ($ziel as $code => $zielId) {
            if (! isset($quelle[$code])) {
                continue;
            }
            $lernende = DB::table('notenbaum_positionen')->where('knoten_id', $quelle[$code])->pluck('lernender_id')->all();
            if ($lernende === []) {
                continue;
            }
            DB::table('notenbaum_positionen')->where('knoten_id', $zielId)->whereIn('lernender_id', $lernende)->delete();
            $anzahl += DB::table('notenbaum_positionen')->where('knoten_id', $quelle[$code])->update(['knoten_id' => (int) $zielId]);
        }

        return $anzahl;
    }
}
