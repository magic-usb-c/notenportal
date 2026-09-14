<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Modulbaukasten;
use App\Support\Modulkatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Ernte des Modulbaukastens → Plan (was würde passieren) → Schreiben. Der Konsolenbefehl
 * `notenportal:modulkatalog` und die Katalogseite in den Stammdaten nutzen beide diesen Dienst:
 * die Kollisionsregel für eigene Modulnummern darf es nur einmal geben, sonst driftet sie auseinander.
 *
 * Grundsätze:
 *  - `planen()` schreibt nie. Erst `anwenden()` schreibt, und dann alles-oder-nichts in einer
 *    Transaktion; ein Fehler lässt die Datenbank unverändert.
 *  - Den Titel eines bestehenden Moduls überschreibt der Import nur, wenn dieses Modul schon aus
 *    dem Katalog stammt: eine eigene Bezeichnung wie «ÜK: Netzwerke» soll er nicht wegwerfen.
 *  - Ein eigenes Modul übernimmt er nur, wenn der Katalog dieselbe Nummer bei einem Beruf führt,
 *    dem das Modul schon zugeordnet ist. Wer «431» mit eigener Kurzbezeichnung erfasst hat, meint
 *    Katalogmodul 431 und soll dessen Angaben bekommen. Wer «801» für ein ABU-Modul erfunden hat,
 *    meint nicht «Grundgesetze der Farbenlehre» – die Nummer ist im Portal eindeutig, also würde
 *    diese Zeile beiden Bedeutungen dienen und jeder andere Beruf die fremde Bezeichnung erben.
 *    Solche Nummern meldet der Import als Konflikt und lässt sie ganz aus; `eigeneUebernehmen`
 *    übergeht das.
 *  - Abschlüsse, die sich nur im Jahrgang der Bildungsverordnung unterscheiden, bleiben getrennte
 *    Lehrberufe: ihre Modulpläne sind verschieden. Der jüngste Jahrgang darf einen bestehenden
 *    Beruf übernehmen, jeder ältere bekommt einen eigenen.
 *  - Lernort (`kategorie_id`) und ein bereits gesetztes Lehrsemester bleiben, wie der Betrieb sie
 *    gesetzt hat; der Katalog kennt den Lernort nicht.
 *
 * Rechtslage, Herkunft der Daten und Ablauf: docs/modulkatalog.md.
 */
final class Katalogimport
{
    /** Wörter, die einen Beruf nicht unterscheiden – für Vergleich und Kürzel. */
    private const array FUELLWORTE = ['in', 'efz', 'eba', 'efa', 'bp', 'hfp', 'und', 'der', 'die', 'das', 'fuer', 'mit'];

    /** @var array<string, list<string>> */
    private array $spalten = [];

    public function __construct(
        private readonly bool $ohneBerufe = false,
        private readonly bool $eigeneUebernehmen = false,
    ) {}

    /** Ohne die Katalogspalten (Migration) lässt sich nichts einlesen. */
    public static function bereit(): bool
    {
        return Schema::hasColumn('module', 'version') && Schema::hasTable('modul_handlungsziele');
    }

    /**
     * Was ein Lauf ändern würde – ohne etwas zu schreiben. Der Plan enthält alles, was `anwenden()`
     * braucht; die Datenbank liest `anwenden()` trotzdem frisch, ein Plan darf also liegen bleiben.
     *
     * @param  list<string>  $nur  Nur diese Abschlüsse (exakter Name); leer = alle.
     * @return array{stand: ?string, lernort: ?int, module: array<string, array<string, mixed>>,
     *     geordnet: list<array{abschluss: array<string, mixed>, eigen: bool}>,
     *     konflikte: array<string, string>, berufe_neu: list<string>, fehler: list<string>,
     *     zahlen: array{module_neu: int, module_alt: int, ziele: int, lbv: int, abschluesse: int,
     *         berufe_neu: int, zuordnungen: int, konflikte: int}}
     *
     * @throws RuntimeException wenn die Ernte nichts Verwertbares enthält.
     */
    public function planen(string $json, array $nur = []): array
    {
        $ernte = Modulkatalog::ausJson($json);
        if ($ernte['module'] === []) {
            throw new RuntimeException(__('Keine verwertbaren Module in der Ernte.'));
        }

        $nur = array_values(array_filter($nur));
        $abschluesse = $nur === []
            ? $ernte['abschluesse']
            : array_values(array_filter($ernte['abschluesse'], fn ($a) => in_array($a['name'], $nur, true)));
        if ($nur !== [] && $abschluesse === []) {
            throw new RuntimeException(__('Kein Abschluss der Ernte passt zur Auswahl.'));
        }

        $module = $this->jeNummer($ernte['module']);
        $geordnet = $this->abschluesseOrdnen($abschluesse);
        $vorhanden = $this->moduleNachNummer();
        $index = $this->berufeIndizieren();
        $konflikte = $this->konflikte($module, $geordnet, $index, $vorhanden);

        $neu = $alt = 0;
        foreach ($module as $nummer => $m) {
            if (isset($konflikte[$nummer])) {
                continue;
            }
            isset($vorhanden[$nummer]) ? $alt++ : $neu++;
        }

        $berufeNeu = [];
        $zuordnungen = 0;
        foreach ($geordnet as $eintrag) {
            $a = $eintrag['abschluss'];
            $fehlt = $this->berufSuchen($a, $eintrag['eigen'], $index) === null;
            if ($fehlt) {
                $berufeNeu[] = $a['name'];
            }
            // Ohne neue Lehrberufe entstehen für einen fehlenden Beruf auch keine Zuordnungen –
            // die Vorschau zählt sie deshalb gar nicht erst mit, sonst verspräche sie zu viel.
            if ($fehlt && $this->ohneBerufe) {
                continue;
            }
            foreach ($a['module'] as $z) {
                if (isset($module[$z['nummer']]) && ! isset($konflikte[$z['nummer']])) {
                    $zuordnungen++;
                }
            }
        }

        return [
            'stand' => $ernte['stand'],
            'lernort' => $this->standardLernort(),
            'module' => $module,
            'geordnet' => $geordnet,
            'konflikte' => $konflikte,
            'berufe_neu' => $berufeNeu,
            'fehler' => $ernte['fehler'],
            'zahlen' => [
                'module_neu' => $neu,
                'module_alt' => $alt,
                'ziele' => array_sum(array_map(fn ($m) => count($m['handlungsziele']), $module)),
                'lbv' => array_sum(array_map(fn ($m) => count($m['lbv_elemente']), $module)),
                'abschluesse' => count($abschluesse),
                'berufe_neu' => count($berufeNeu),
                'zuordnungen' => $zuordnungen,
                'konflikte' => count($konflikte),
            ],
        ];
    }

    /**
     * Den Plan schreiben – alles-oder-nichts.
     *
     * @param  array<string, mixed>  $plan  Ergebnis von planen()
     * @return array{module: int, ziele: int, lbv: int, berufe: int, zuordnungen: int}
     */
    public function anwenden(array $plan): array
    {
        $stand = $plan['stand'] ?? now()->format('Y-m-d H:i:s');

        return DB::transaction(fn () => $this->schreiben(
            $plan['module'], $plan['geordnet'], $plan['lernort'], (string) $stand, $plan['konflikte']
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $module
     * @param  list<array{abschluss: array<string, mixed>, eigen: bool}>  $geordnet
     * @param  array<string, string>  $konflikte
     * @return array{module:int, ziele:int, lbv:int, berufe:int, zuordnungen:int}
     */
    private function schreiben(array $module, array $geordnet, ?int $lernort, string $stand, array $konflikte): array
    {
        $bericht = ['module' => 0, 'ziele' => 0, 'lbv' => 0, 'berufe' => 0, 'zuordnungen' => 0];
        $vorhanden = $this->moduleNachNummer();
        $ids = [];

        foreach ($module as $nummer => $m) {
            // Ohne Eintrag in $ids überspringt die Zuordnungsschleife diese Nummer von selbst.
            if (isset($konflikte[$nummer])) {
                continue;
            }
            $daten = [
                'version' => $m['version'],
                'kompetenzfeld' => $m['kompetenzfeld'],
                'kompetenz' => $m['kompetenz'],
                'objekt' => $m['objekt'],
                'publiziert_am' => $m['publiziert_am'],
                'auslaufend' => $m['auslaufend'] ? 1 : 0,
                'quelle' => Modulbaukasten::QUELLE,
                'quelle_stand' => $stand,
                'aktualisiert_am' => now(),
            ];

            if (isset($vorhanden[$nummer])) {
                $zeile = $vorhanden[$nummer];
                if (($zeile->quelle ?? null) === Modulbaukasten::QUELLE || trim((string) $zeile->titel) === '') {
                    $daten['titel'] = $m['titel'];
                }
                DB::table('module')->where('modul_id', $zeile->modul_id)->update($this->nurSpalten('module', $daten));
                $id = (int) $zeile->modul_id;
            } else {
                $id = (int) DB::table('module')->insertGetId($this->nurSpalten('module', $daten + [
                    'modul_nummer' => 'M'.$nummer,
                    'titel' => $m['titel'],
                    'ziel_gewicht_summe_default' => 100.00,
                    'aktiv' => 1,
                    'erstellt_am' => now(),
                ]));
            }
            $ids[$nummer] = $id;
            $bericht['module']++;

            // Handlungsziele und LBV ersetzen – aber nur, wenn die Ernte dazu etwas enthält
            // (eine Ernte ohne --details liefert nur die Listen und darf nichts löschen).
            if ($m['handlungsziele'] !== []) {
                DB::table('modul_handlungsziele')->where('modul_id', $id)->delete();
                foreach (array_values($m['handlungsziele']) as $i => $z) {
                    DB::table('modul_handlungsziele')->insert([
                        'modul_id' => $id,
                        'nummer' => $z['nummer'],
                        'text' => $z['text'],
                        'sortierung' => $i + 1,
                    ]);
                    $bericht['ziele']++;
                }
            }
            if ($m['lbv_elemente'] !== []) {
                DB::table('modul_lbv_elemente')->where('modul_id', $id)->delete();
                foreach (array_values($m['lbv_elemente']) as $i => $e) {
                    DB::table('modul_lbv_elemente')->insert([
                        'modul_id' => $id,
                        'bezeichnung' => $e['bezeichnung'],
                        'gewichtung_prozent' => $e['gewichtung_prozent'],
                        'richtzeit' => $e['richtzeit'],
                        'pruefungsform' => $e['pruefungsform'],
                        'sozialform' => $e['sozialform'],
                        'beschreibung' => $e['beschreibung'],
                        'sortierung' => $i + 1,
                    ]);
                    $bericht['lbv']++;
                }
            }
        }

        $index = $this->berufeIndizieren();
        foreach ($geordnet as $eintrag) {
            $a = $eintrag['abschluss'];
            $beruf = $this->berufSuchen($a, $eintrag['eigen'], $index);
            if ($beruf === null) {
                if ($this->ohneBerufe) {
                    continue;
                }
                // Das Kürzel muss im Merker landen: sonst hält kuerzel() es für frei und der
                // nächste neue Abschluss bekommt dasselbe – lehrberufe.kuerzel ist eindeutig.
                $kuerzel = $this->kuerzel($a['name'], $index['name']);
                $id = (int) DB::table('lehrberufe')->insertGetId($this->nurSpalten('lehrberufe', [
                    'kuerzel' => $kuerzel,
                    'name' => $a['name'],
                    'bivo_jahr' => $a['bivo_jahr'],
                    'quelle_kennung' => $a['kennung'],
                    'aktiv' => 1,
                    'erstellt_am' => now(),
                    'aktualisiert_am' => now(),
                ]));
                $beruf = (object) [
                    'lehrberuf_id' => $id, 'kuerzel' => $kuerzel,
                    'name' => $a['name'], 'quelle_kennung' => $a['kennung'],
                ];
                $index['name'][$a['name']] = $beruf;
                if ($a['kennung'] !== null) {
                    $index['kennung'][$a['kennung']] = $beruf;
                }
                $bericht['berufe']++;
            } elseif ($a['kennung'] !== null && ($beruf->quelle_kennung ?? null) === null) {
                DB::table('lehrberufe')->where('lehrberuf_id', $beruf->lehrberuf_id)
                    ->update($this->nurSpalten('lehrberufe', ['quelle_kennung' => $a['kennung'], 'bivo_jahr' => $a['bivo_jahr'], 'aktualisiert_am' => now()]));
                // Merken, sonst sucht ein zweiter Abschluss mit derselben Kennung erneut.
                $beruf->quelle_kennung = $a['kennung'];
                $index['kennung'][$a['kennung']] = $beruf;
            }

            foreach ($a['module'] as $z) {
                $modulId = $ids[$z['nummer']] ?? null;
                if ($modulId === null) {
                    continue;
                }
                $semester = $z['lehrjahr'] !== null ? $z['lehrjahr'] * 2 - 1 : null;
                $bestehend = DB::table('lehrberuf_module')
                    ->where('lehrberuf_id', $beruf->lehrberuf_id)->where('modul_id', $modulId)->first();

                if ($bestehend !== null) {
                    $daten = ['pflicht' => $z['pflicht'], 'pflichtgrad' => $z['pflichtgrad']];
                    if ($semester !== null && ($bestehend->empfohlenes_lehrsemester_nr ?? null) === null) {
                        $daten['empfohlenes_lehrsemester_nr'] = $semester;
                    }
                    DB::table('lehrberuf_module')
                        ->where('lehrberuf_id', $beruf->lehrberuf_id)->where('modul_id', $modulId)
                        ->update($this->nurSpalten('lehrberuf_module', $daten));
                } else {
                    DB::table('lehrberuf_module')->insert($this->nurSpalten('lehrberuf_module', [
                        'lehrberuf_id' => $beruf->lehrberuf_id,
                        'modul_id' => $modulId,
                        'kategorie_id' => $lernort,
                        'pflicht' => $z['pflicht'],
                        'pflichtgrad' => $z['pflichtgrad'],
                        'empfohlenes_lehrsemester_nr' => $semester,
                        'aktiv' => 1,
                        'erstellt_am' => now(),
                        'aktualisiert_am' => now(),
                    ]));
                }
                $bericht['zuordnungen']++;
            }
        }

        return $bericht;
    }

    /**
     * Eine Modulnummer, ein Datensatz: die Tabelle `module` hat die Nummer als eindeutigen Schlüssel.
     * Sind mehrere Katalogversionen geerntet (z. B. 117 V4 und V5), führt die höchste – ältere
     * Versionen bleiben über den Katalog erreichbar.
     *
     * @param  list<array<string, mixed>>  $module
     * @return array<string, array<string, mixed>>
     */
    private function jeNummer(array $module): array
    {
        $raus = [];
        foreach ($module as $m) {
            $da = $raus[$m['nummer']] ?? null;
            if ($da === null || (int) ($m['version'] ?? 0) > (int) ($da['version'] ?? 0)) {
                $raus[$m['nummer']] = $m;
            }
        }
        ksort($raus);

        return $raus;
    }

    /** @return array<string, object> */
    private function moduleNachNummer(): array
    {
        $raus = [];
        foreach (DB::table('module')->get(['modul_id', 'modul_nummer', 'titel', 'quelle']) as $zeile) {
            $nummer = Modulbaukasten::nummerNormalisieren($zeile->modul_nummer);
            if ($nummer !== null && ! isset($raus[$nummer])) {
                $raus[$nummer] = $zeile;
            }
        }

        return $raus;
    }

    /**
     * Nummern, die der Katalog kennt und die im Portal schon als eigenes Modul stehen, ohne dass der
     * Katalog sie bei einem Beruf dieses Moduls führt: dann ist die Nummer anders gemeint. Weil
     * `module.modul_nummer` eindeutig ist, müsste eine Zeile sonst beiden Bedeutungen dienen – und
     * der eigene Titel würde bei jedem anderen Beruf des Katalogmoduls erscheinen.
     *
     * @param  array<string, array<string, mixed>>  $module
     * @param  list<array{abschluss: array<string, mixed>, eigen: bool}>  $geordnet
     * @param  array{kennung: array<string, object>, name: array<string, object>, schluessel: array<string, object>}  $index
     * @param  array<string, object>  $vorhanden
     * @return array<string, string> Nummer => eigener Titel
     */
    private function konflikte(array $module, array $geordnet, array $index, array $vorhanden): array
    {
        if ($this->eigeneUebernehmen) {
            return [];
        }

        $eigene = [];
        foreach ($vorhanden as $nummer => $zeile) {
            if (isset($module[$nummer]) && ($zeile->quelle ?? null) === null) {
                $eigene[$nummer] = $zeile;
            }
        }
        if ($eigene === []) {
            return [];
        }

        // Welche Nummern führt der Katalog bei welchem bestehenden Beruf?
        $laut = [];
        foreach ($geordnet as $eintrag) {
            $beruf = $this->berufSuchen($eintrag['abschluss'], $eintrag['eigen'], $index);
            if ($beruf === null) {
                continue;
            }
            foreach ($eintrag['abschluss']['module'] as $z) {
                $laut[(int) $beruf->lehrberuf_id][$z['nummer']] = true;
            }
        }

        $konflikte = [];
        foreach ($eigene as $nummer => $zeile) {
            foreach (DB::table('lehrberuf_module')->where('modul_id', $zeile->modul_id)->pluck('lehrberuf_id') as $id) {
                if (isset($laut[(int) $id][$nummer])) {
                    continue 2;
                }
            }
            $konflikte[$nummer] = (string) $zeile->titel;
        }

        return $konflikte;
    }

    /**
     * Die Lehrberufe dreifach nachschlagbar: über die Kennung des Katalogs, über den genauen Namen
     * und über den unscharfen Vergleichsschlüssel.
     *
     * @return array{kennung: array<string, object>, name: array<string, object>, schluessel: array<string, object>}
     */
    private function berufeIndizieren(): array
    {
        $kennung = $name = $schluessel = [];
        foreach (DB::table('lehrberufe')->get() as $zeile) {
            $name[(string) $zeile->name] = $zeile;
            $schluessel[$this->berufSchluessel((string) $zeile->name)] ??= $zeile;
            if (($zeile->quelle_kennung ?? null) !== null && $zeile->quelle_kennung !== '') {
                $kennung[(string) $zeile->quelle_kennung] = $zeile;
            }
        }

        return ['kennung' => $kennung, 'name' => $name, 'schluessel' => $schluessel];
    }

    /**
     * Mehrere Abschlüsse einer Ernte können sich nur im Jahrgang der Bildungsverordnung
     * unterscheiden – «Informatiker/in EFZ Applikationsentwicklung (2014)» und «(2021)». Ihre
     * Modulpläne sind verschieden: von den Modulnummern beider steht nur ein Bruchteil in beiden.
     * Sie dürfen deshalb nicht in denselben Lehrberuf laufen, sonst sieht ein Lernender Module
     * einer Verordnung, die für ihn nicht gilt. Der jüngste Jahrgang führt: nur er darf einen
     * bestehenden Beruf übernehmen, jeder ältere bekommt einen eigenen.
     *
     * @param  list<array<string, mixed>>  $abschluesse
     * @return list<array{abschluss: array<string, mixed>, eigen: bool}>
     */
    private function abschluesseOrdnen(array $abschluesse): array
    {
        $gruppen = [];
        foreach ($abschluesse as $a) {
            $gruppen[$this->berufSchluessel((string) $a['name'])][] = $a;
        }

        $raus = [];
        foreach ($gruppen as $gruppe) {
            usort($gruppe, fn ($x, $y) => ((int) ($y['bivo_jahr'] ?? 0)) <=> ((int) ($x['bivo_jahr'] ?? 0)));
            foreach ($gruppe as $i => $a) {
                $raus[] = ['abschluss' => $a, 'eigen' => $i > 0];
            }
        }

        return $raus;
    }

    /**
     * Der bestehende Lehrberuf zu einem Abschluss, oder null für «neu anlegen». Die Kennung des
     * Katalogs ist die verlässlichste Identität und hält wiederholte Läufe stabil; danach zählt der
     * genaue Name. Der unscharfe Schlüssel greift nur für den jüngsten Jahrgang einer Gruppe.
     *
     * @param  array<string, mixed>  $a
     * @param  array{kennung: array<string, object>, name: array<string, object>, schluessel: array<string, object>}  $index
     */
    private function berufSuchen(array $a, bool $eigen, array $index): ?object
    {
        if ($a['kennung'] !== null && isset($index['kennung'][$a['kennung']])) {
            return $index['kennung'][$a['kennung']];
        }
        if (isset($index['name'][$a['name']])) {
            return $index['name'][$a['name']];
        }
        $schluessel = $this->berufSchluessel((string) $a['name']);

        return $eigen ? null : ($index['schluessel'][$schluessel] ?? null);
    }

    /**
     * Vergleichsform eines Abschlussnamens. Der Katalog schreibt «Informatiker/in EFZ
     * Applikationsentwicklung (2021)», ein Betrieb vielleicht «Informatiker Applikationsentwickler
     * EFZ» – gemeint ist derselbe Beruf. Darum: Wörter einzeln, ohne Abschluss- und Füllwörter, je
     * auf die ersten sechs Buchstaben gekürzt (fängt Entwickler/Entwicklung) und sortiert, damit die
     * Wortreihenfolge nichts ändert. Ziffern fallen weg: der Jahrgang der Bildungsverordnung soll
     * keinen zweiten Lehrberuf erzeugen, er steht in `bivo_jahr`.
     */
    private function berufSchluessel(string $name): string
    {
        $wert = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'], ['ae', 'oe', 'ue', 'ae', 'oe', 'ue'], mb_strtolower($name));
        $worte = preg_split('/[^a-z]+/', $wert, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $worte = array_filter($worte, fn ($w) => ! in_array($w, self::FUELLWORTE, true));
        $worte = array_unique(array_map(fn ($w) => mb_substr($w, 0, 6), $worte));
        sort($worte);

        return implode('|', $worte);
    }

    /**
     * Kürzel aus den Anfangsbuchstaben: «Entwickler/in digitales Business EFZ» ergibt «EDB».
     *
     * @param  array<string, object>  $berufe
     */
    private function kuerzel(string $name, array $berufe): string
    {
        $vergeben = array_map(fn ($b) => mb_strtolower((string) ($b->kuerzel ?? '')), $berufe);
        $worte = preg_split('/[^\p{L}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $worte = array_values(array_filter($worte, fn ($w) => ! in_array(mb_strtolower($w), self::FUELLWORTE, true)));

        $basis = mb_strtoupper(mb_substr(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), $worte)), 0, 8));
        if ($basis === '') {
            $basis = 'BERUF';
        }

        // Unterscheiden sich zwei Abschlüsse nur im Jahrgang, sagt «IA14» mehr als «IA2».
        if (in_array(mb_strtolower($basis), $vergeben, true) && preg_match('/\(\d{2}(\d{2})/', $name, $j) === 1) {
            $mitJahr = mb_substr(mb_substr($basis, 0, 8).$j[1], 0, 10);
            if (! in_array(mb_strtolower($mitJahr), $vergeben, true)) {
                return $mitJahr;
            }
        }

        // lehrberufe.kuerzel ist varchar(10): der Zähler darf die Spalte nicht überlaufen.
        $kandidat = $basis;
        for ($i = 2; in_array(mb_strtolower($kandidat), $vergeben, true); $i++) {
            $kandidat = mb_substr(mb_substr($basis, 0, 8).$i, 0, 10);
        }

        return $kandidat;
    }

    /** Lernort für neue Zuordnungen: Fachunterricht, sonst der erste vorhandene. */
    private function standardLernort(): ?int
    {
        $id = DB::table('kategorien')->where('code', 'FACH')->value('kategorie_id')
            ?? DB::table('kategorien')->orderBy('sortierung')->value('kategorie_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Nur Spalten schreiben, die es in dieser Datenbank gibt – hält den Import robust gegenüber
     * Instanzen, die eine spätere Migration noch nicht haben.
     *
     * @param  array<string, mixed>  $daten
     * @return array<string, mixed>
     */
    private function nurSpalten(string $tabelle, array $daten): array
    {
        $this->spalten[$tabelle] ??= Schema::getColumnListing($tabelle);

        return array_intersect_key($daten, array_flip($this->spalten[$tabelle]));
    }
}
