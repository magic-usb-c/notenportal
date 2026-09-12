<?php

namespace App\Support;

/**
 * Prüft und normalisiert eine Ernte aus `tools/modulkatalog-ernte.mjs`, bevor sie in die Datenbank
 * geht. Reine Funktionen, keine Datenbank – damit der Import vorher zeigen kann, was passieren wird.
 *
 * Erwartete Struktur: {format, quelle, geerntet_am, abschluesse:[{name, kennung, module:[…]}],
 * module:[{nummer, version, titel, kompetenzfeld, kompetenz, objekt, publiziert_am,
 * handlungsziele:[{nummer,text}], lbv:{elemente:[{bezeichnung,gewichtung_prozent,…}]}]}
 */
final class Modulkatalog
{
    public const FORMAT = 1;

    private const GRADE = ['pfl', 'wpfl', 'wm'];

    /**
     * @return array{module: list<array<string,mixed>>, abschluesse: list<array<string,mixed>>, fehler: list<string>, stand: ?string}
     */
    public static function ausJson(string $inhalt): array
    {
        $leer = ['module' => [], 'abschluesse' => [], 'fehler' => [], 'stand' => null];

        try {
            $daten = json_decode($inhalt, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [...$leer, 'fehler' => [__('Die Datei ist kein gültiges JSON.')]];
        }
        if (! is_array($daten)) {
            return [...$leer, 'fehler' => [__('Die Datei enthält kein Objekt.')]];
        }
        if ((int) ($daten['format'] ?? 0) !== self::FORMAT) {
            return [...$leer, 'fehler' => [__('Unbekanntes Format der Ernte (erwartet :n).', ['n' => self::FORMAT])]];
        }

        $fehler = [];
        $module = [];
        foreach ((array) ($daten['module'] ?? []) as $i => $roh) {
            $modul = self::modul(is_array($roh) ? $roh : [], $fehler, (int) $i);
            if ($modul !== null) {
                $module[$modul['nummer'].'/'.($modul['version'] ?? '')] = $modul;
            }
        }

        $abschluesse = [];
        foreach ((array) ($daten['abschluesse'] ?? []) as $roh) {
            $name = trim((string) ($roh['name'] ?? ''));
            if ($name === '') {
                $fehler[] = __('Ein Abschluss ohne Namen wurde übersprungen.');

                continue;
            }
            $zuordnungen = [];
            foreach ((array) ($roh['module'] ?? []) as $m) {
                $nummer = Modulbaukasten::nummerNormalisieren((string) ($m['nummer'] ?? ''));
                if ($nummer === null) {
                    continue;
                }
                $grad = in_array($m['pflichtgrad'] ?? null, self::GRADE, true) ? $m['pflichtgrad'] : null;
                $lehrjahr = (int) ($m['lehrjahr'] ?? 0);
                $zuordnungen[] = [
                    'nummer' => $nummer,
                    'version' => self::version($m['version'] ?? null),
                    'titel' => self::text($m['titel'] ?? '', 255),
                    'pflichtgrad' => $grad,
                    // Pflicht ist der ausgewertete Schalter: Wahlpflicht- und Wahlmodule sind frei.
                    'pflicht' => $grad === 'pfl' ? 1 : 0,
                    'lehrjahr' => $lehrjahr >= 1 && $lehrjahr <= 4 ? $lehrjahr : null,
                    'kompetenzfeld' => self::text($m['kompetenzfeld'] ?? null, 120),
                    'auslaufend' => (bool) ($m['auslaufend'] ?? false),
                ];
            }
            if ($zuordnungen === []) {
                $fehler[] = __('Abschluss «:name» enthält keine verwertbaren Module.', ['name' => $name]);

                continue;
            }
            $abschluesse[] = [
                'name' => self::text($name, 200),
                'kennung' => self::text($roh['kennung'] ?? null, 60),
                'bivo_jahr' => self::jahr($name),
                'module' => $zuordnungen,
            ];
        }

        // Module, die nur in einer Abschlussliste stehen (Ernte ohne --details), ergänzen.
        foreach ($abschluesse as $a) {
            foreach ($a['module'] as $m) {
                $schluessel = $m['nummer'].'/'.($m['version'] ?? '');
                if (! isset($module[$schluessel]) && $m['titel'] !== null) {
                    $module[$schluessel] = [
                        'nummer' => $m['nummer'], 'version' => $m['version'], 'titel' => $m['titel'],
                        'kompetenzfeld' => $m['kompetenzfeld'], 'kompetenz' => null, 'objekt' => null,
                        'publiziert_am' => null, 'auslaufend' => $m['auslaufend'],
                        'handlungsziele' => [], 'lbv_elemente' => [],
                    ];
                }
            }
        }

        if ($module === [] && $abschluesse === []) {
            $fehler[] = __('Die Ernte enthält keine Module.');
        }

        return [
            'module' => array_values($module),
            'abschluesse' => $abschluesse,
            'fehler' => $fehler,
            'stand' => self::zeitpunkt($daten['geerntet_am'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $fehler
     * @return array<string,mixed>|null
     */
    private static function modul(array $roh, array &$fehler, int $i): ?array
    {
        $nummer = Modulbaukasten::nummerNormalisieren((string) ($roh['nummer'] ?? ''));
        if ($nummer === null) {
            $fehler[] = __('Modul :i hat keine verwertbare Nummer.', ['i' => $i + 1]);

            return null;
        }
        $titel = self::text($roh['titel'] ?? null, 255);
        if ($titel === null) {
            $fehler[] = __('Modul :nummer hat keinen Titel.', ['nummer' => $nummer]);

            return null;
        }

        $ziele = [];
        foreach ((array) ($roh['handlungsziele'] ?? []) as $z) {
            $nr = trim((string) ($z['nummer'] ?? ''));
            $text = self::text($z['text'] ?? null, 2000);
            if ($nr !== '' && $text !== null && preg_match('/^\d{1,2}$/', $nr) === 1) {
                $ziele[$nr] = ['nummer' => $nr, 'text' => $text];
            }
        }

        $elemente = [];
        foreach ((array) (($roh['lbv']['elemente'] ?? [])) as $e) {
            $bez = self::text($e['bezeichnung'] ?? null, 150);
            if ($bez === null) {
                continue;
            }
            $elemente[] = [
                'bezeichnung' => $bez,
                'gewichtung_prozent' => self::zahl($e['gewichtung_prozent'] ?? null, 0, 100),
                'richtzeit' => self::zahl($e['richtzeit'] ?? null, 0, 999),
                'pruefungsform' => self::text($e['pruefungsform'] ?? null, 80),
                'sozialform' => self::text($e['sozialform'] ?? null, 80),
                'beschreibung' => self::text($e['beschreibung'] ?? null, 4000),
            ];
        }

        return [
            'nummer' => $nummer,
            'version' => self::version($roh['version'] ?? null),
            'titel' => $titel,
            'kompetenzfeld' => self::text($roh['kompetenzfeld'] ?? null, 120),
            'kompetenz' => self::text($roh['kompetenz'] ?? null, 4000),
            'objekt' => self::text($roh['objekt'] ?? null, 4000),
            'publiziert_am' => self::datum($roh['publiziert_am'] ?? null),
            'auslaufend' => (bool) ($roh['auslaufend'] ?? false),
            'handlungsziele' => array_values($ziele),
            'lbv_elemente' => $elemente,
        ];
    }

    private static function text(mixed $wert, int $max): ?string
    {
        $wert = trim(preg_replace('/\s+/u', ' ', (string) $wert) ?? '');

        return $wert === '' ? null : mb_substr($wert, 0, $max);
    }

    private static function version(mixed $wert): ?string
    {
        $wert = trim((string) $wert);

        return preg_match('/^\d{1,2}$/', $wert) === 1 ? $wert : null;
    }

    private static function zahl(mixed $wert, float $min, float $max): ?float
    {
        if (! is_numeric($wert)) {
            return null;
        }
        $zahl = (float) $wert;

        return $zahl >= $min && $zahl <= $max ? round($zahl, 2) : null;
    }

    private static function datum(mixed $wert): ?string
    {
        $wert = trim((string) $wert);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1 ? $wert : null;
    }

    private static function zeitpunkt(mixed $wert): ?string
    {
        $wert = trim((string) $wert);
        if ($wert === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($wert))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** «Informatiker/in EFZ Applikationsentwicklung (2021)» → 2021 */
    private static function jahr(string $name): ?int
    {
        if (preg_match('/\((\d{4})/', $name, $m) === 1) {
            $jahr = (int) $m[1];

            return $jahr >= 1990 && $jahr <= 2100 ? $jahr : null;
        }

        return null;
    }
}
