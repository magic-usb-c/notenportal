<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Support\JsTexte;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Statische Prüfung der Übersetzungsschlüssel (docs/i18n-plan.md): Jeder literale Schlüssel
 * ausserhalb der offenen Bereiche (offen.php) hat eine EN-Übersetzung, geschlossene
 * Übersetzungsdateien enthalten keine verwaisten oder widersprüchlichen Einträge.
 */
class SchluesselTest extends TestCase
{
    #[Test]
    public function jeder_verwendete_schluessel_hat_eine_englische_uebersetzung(): void
    {
        $vorhanden = array_merge(...array_values(array_map('array_keys', Schluessel::dateien())));
        $fehlend = [];

        foreach (Schluessel::verwendet() as $schluessel => $dateien) {
            $geschlossen = array_filter($dateien, fn (string $datei) => ! Schluessel::istOffen($datei));
            if ($geschlossen !== [] && ! in_array($schluessel, $vorhanden, true)) {
                $fehlend[] = "«{$schluessel}» (".implode(', ', $geschlossen).')';
            }
        }

        $this->assertSame([], $fehlend, "Ohne EN-Übersetzung:\n".implode("\n", $fehlend));
    }

    #[Test]
    public function geschlossene_uebersetzungsdateien_haben_keine_verwaisten_schluessel(): void
    {
        $verwendet = [...array_keys(Schluessel::verwendet()), ...Schluessel::dynamisch()];
        $verwaist = [];

        foreach (Schluessel::geschlossen() as $datei => $uebersetzungen) {
            foreach (array_keys($uebersetzungen) as $schluessel) {
                if (! in_array($schluessel, $verwendet, true)) {
                    $verwaist[] = "{$datei}: «{$schluessel}»";
                }
            }
        }

        $this->assertSame([], $verwaist, "Nicht mehr verwendet:\n".implode("\n", $verwaist));
    }

    #[Test]
    public function doppelte_schluessel_haben_dieselbe_uebersetzung(): void
    {
        $gesehen = [];
        $abweichend = [];

        foreach (Schluessel::geschlossen() as $datei => $uebersetzungen) {
            foreach ($uebersetzungen as $schluessel => $text) {
                if (isset($gesehen[$schluessel]) && $gesehen[$schluessel][1] !== $text) {
                    $abweichend[] = "«{$schluessel}»: {$gesehen[$schluessel][0]} «{$gesehen[$schluessel][1]}» ≠ {$datei} «{$text}»";
                }
                $gesehen[$schluessel] ??= [$datei, $text];
            }
        }

        $this->assertSame([], $abweichend, implode("\n", $abweichend));
    }

    #[Test]
    public function platzhalter_bleiben_in_der_uebersetzung_erhalten(): void
    {
        $falsch = [];

        foreach (Schluessel::dateien() as $datei => $uebersetzungen) {
            foreach ($uebersetzungen as $schluessel => $text) {
                preg_match_all('/:([a-z_]+)/i', $schluessel, $soll);
                preg_match_all('/:([a-z_]+)/i', (string) $text, $ist);
                if (array_diff($soll[1], $ist[1]) !== [] || array_diff($ist[1], $soll[1]) !== []) {
                    $falsch[] = "{$datei}: «{$schluessel}» → «{$text}»";
                }
                if (trim((string) $text) === '') {
                    $falsch[] = "{$datei}: «{$schluessel}» ist leer";
                }
            }
        }

        $this->assertSame([], $falsch, implode("\n", $falsch));
    }

    #[Test]
    public function js_texte_decken_genau_die_np_t_schluessel_ab(): void
    {
        $imJs = array_keys(Schluessel::javascript());
        sort($imJs);
        $geliefert = JsTexte::SCHLUESSEL;
        sort($geliefert);

        $this->assertSame($imJs, $geliefert, 'JsTexte::SCHLUESSEL muss den t()-Aufrufen in resources/js entsprechen');
    }
}
