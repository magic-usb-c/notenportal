<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Ratsche für die Sprachumschaltung: zählt pro View-Ordner die Textknoten und Textattribute
 * (placeholder, title, aria-label, alt), die nicht in __() stehen. Heuristik: Blade-Kommentare,
 * {{ }}/{!! !!}, @php, <script>, <style>, <svg> und Blade-Direktiven werden entfernt, Zahlen
 * und reine Satzzeichen zählen nicht.
 */
class I18nScan extends Command
{
    protected $signature = 'notenportal:i18n-scan
        {--tief : pro Unterordner statt pro oberstem View-Ordner}
        {--details : Fundstellen je Datei ausgeben}';

    protected $description = 'Deutsche Texte ausserhalb von __() pro View-Ordner zählen';

    private const string ATTRIBUTE = 'placeholder|title|aria-label|alt|label';

    /** Marken und Tastennamen, die in beiden Sprachen gleich bleiben. */
    private const array NICHT_UEBERSETZEN = ['Notenportal', 'CSV', 'PDF', 'ICS', 'Ctrl', 'Cmd', 'Esc', 'K'];

    public function handle(): int
    {
        $basis = resource_path('views');
        $zeilen = [];

        foreach ((new Finder)->files()->in($basis)->name('*.blade.php')->sortByName() as $datei) {
            $relativ = str_replace('\\', '/', $datei->getRelativePathname());
            $ordner = $this->ordner($relativ);
            [$knoten, $attribute] = self::funde($datei->getContents());

            $zeilen[$ordner]['knoten'] = ($zeilen[$ordner]['knoten'] ?? 0) + count($knoten);
            $zeilen[$ordner]['attribute'] = ($zeilen[$ordner]['attribute'] ?? 0) + count($attribute);

            if ($this->option('details') && ($knoten || $attribute)) {
                $this->line("<comment>{$relativ}</comment>");
                foreach ([...$knoten, ...$attribute] as $text) {
                    $this->line('  '.mb_strimwidth($text, 0, 110, '…'));
                }
            }
        }

        ksort($zeilen);
        $tabelle = collect($zeilen)->map(fn ($z, $o) => [$o, $z['knoten'], $z['attribute']])->values()->all();
        $tabelle[] = ['Total', array_sum(array_column($zeilen, 'knoten')), array_sum(array_column($zeilen, 'attribute'))];
        $this->table(['Ordner', 'Textknoten', 'Attribute'], $tabelle);

        return self::SUCCESS;
    }

    private function ordner(string $relativ): string
    {
        $teile = explode('/', $relativ);
        array_pop($teile);
        if ($teile === []) {
            return '.';
        }

        return $this->option('tief') ? implode('/', $teile) : $teile[0];
    }

    /**
     * @return array{0: list<string>, 1: list<string>} Textknoten und Attributwerte ausserhalb von __()
     */
    public static function funde(string $blade): array
    {
        $s = preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
        $s = preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $s);
        $s = preg_replace('#<(script|style|svg)\b.*?</\1>#si', '', $s);
        $s = preg_replace('/\{!!.*?!!\}|\{\{.*?\}\}/s', '', $s);
        $s = self::ohneDirektiven($s);

        $attribute = [];
        preg_match_all('/\s(?:'.self::ATTRIBUTE.')\s*=\s*"([^"]*)"/i', $s, $treffer);
        foreach ($treffer[1] as $wert) {
            if (self::istText($wert)) {
                $attribute[] = '['.trim($wert).']';
            }
        }

        // Tags entfernen (Anführungszeichen in Attributen respektieren), Rest sind Textknoten
        $text = preg_replace('/<\s*[a-zA-Z\/!][^>"\']*(?:(?:"[^"]*"|\'[^\']*\')[^>"\']*)*>/', "\n", $s);
        $knoten = [];
        foreach (preg_split('/\n+/', (string) $text) as $stueck) {
            $stueck = trim(html_entity_decode($stueck, ENT_QUOTES | ENT_HTML5));
            if (self::istText($stueck)) {
                $knoten[] = $stueck;
            }
        }

        return [$knoten, $attribute];
    }

    private static function istText(string $wert): bool
    {
        $wert = preg_replace('/\b(?:'.implode('|', self::NICHT_UEBERSETZEN).')\b/u', '', $wert);

        return (bool) preg_match('/\p{L}{2,}/u', $wert) && ! preg_match('/^[\s\p{P}\p{S}\d]*$/u', $wert);
    }

    /** Entfernt @direktive(...) mit ausgeglichenen Klammern und @direktive ohne Argumente. */
    private static function ohneDirektiven(string $s): string
    {
        $ergebnis = '';
        $laenge = strlen($s);
        for ($i = 0; $i < $laenge; $i++) {
            if ($s[$i] === '@' && ($i === 0 || ! ctype_alnum($s[$i - 1])) && preg_match('/\G@\w+/', $s, $m, 0, $i)) {
                $i += strlen($m[0]);
                $j = $i;
                while ($j < $laenge && ($s[$j] === ' ' || $s[$j] === "\t")) {
                    $j++;
                }
                if ($j < $laenge && $s[$j] === '(') {
                    $tiefe = 0;
                    for (; $j < $laenge; $j++) {
                        $tiefe += $s[$j] === '(' ? 1 : ($s[$j] === ')' ? -1 : 0);
                        if ($tiefe === 0) {
                            break;
                        }
                    }
                    $i = $j;
                } else {
                    $i--;
                }

                continue;
            }
            $ergebnis .= $s[$i];
        }

        return $ergebnis;
    }
}
