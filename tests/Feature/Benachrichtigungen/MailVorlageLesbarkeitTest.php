<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Services\Notifications\MailContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «mail-layout»: Lesbarkeit auf dem Telefon (Schriftgrösse, Ersatzlink). */
class MailVorlageLesbarkeitTest extends TestCase
{
    private const URL = 'https://portal.beispiel.test/lernende/noten/12345?signature=abcdef0123456789abcdef0123456789';

    private function html(MailContent $c): string
    {
        return view('mail.portal', [
            'c' => $c, 'vorname' => 'Nina', 'anlass' => 'Kommentar', 'abmeldenUrl' => 'https://x.test/u',
            'einstellungenUrl' => 'https://x.test/s', 'betrieb' => 'Beispiel', 'logoUrl' => null, 'portalUrl' => 'https://x.test/',
        ])->render();
    }

    /** Mail mit allen Bausteinen: Fakten, Abschnitt, Tabelle, Liste, Knopf mit Ersatzlink, Fuss. */
    private function vollstaendig(): MailContent
    {
        return new MailContent(
            subject: 'Semesterabschluss',
            lines: ['Zusammenfassung.'],
            facts: ['Datum' => 'Mo 14.09.2026', 'Fach' => 'Mathe'],
            sections: [['title' => 'Hinweise', 'text' => "- Eins\nZwei"]],
            actionLabel: 'Öffnen',
            actionUrl: self::URL,
            outro: ['Schluss.'],
            table: ['head' => ['Lernender', 'Semesterschnitt', 'Ungenügende Elemente'], 'rows' => [['Nina Beispiel', '4.8', '1']]],
            items: [['group' => 'Heute', 'title' => 'Neue Note', 'text' => 'Mathe 5.5', 'url' => self::URL]],
        );
    }

    #[Test]
    public function kein_text_der_mail_ist_kleiner_als_13_pixel_ausser_dem_preheader(): void
    {
        $html = $this->html($this->vollstaendig());
        $ohneVorschau = preg_replace('/<div style="display:none.*?<\/div>/s', '', $html);

        preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $ohneVorschau, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $px) {
            $this->assertGreaterThanOrEqual(13, (float) $px, "font-size:{$px}px ist auf dem Telefon zu klein");
        }
    }

    #[Test]
    public function tabellenkopf_ist_lesbar_gross_und_nicht_in_grossbuchstaben_gesperrt(): void
    {
        preg_match_all('/<th\b[^>]*style="([^"]*)"/', $this->html($this->vollstaendig()), $ths);
        $this->assertCount(3, $ths[1]);
        foreach ($ths[1] as $stil) {
            $this->assertStringContainsString('font-size:13px', $stil);
            $this->assertStringNotContainsString('text-transform:uppercase', $stil);
            $this->assertStringNotContainsString('letter-spacing', $stil);
        }
    }

    #[Test]
    public function ersatzlink_steht_in_eigener_zeile_und_bricht_nicht_zeichenweise(): void
    {
        $html = $this->html($this->vollstaendig());

        $this->assertMatchesRegularExpression('/<p class="np-muted[^"]*"[^>]*>[^<]*Knopf funktioniert nicht\?\s*<br>\s*'.preg_quote(htmlspecialchars(self::URL, ENT_QUOTES), '/').'<\/p>/', $html);
        preg_match('/<p class="np-muted[^"]*" style="([^"]*)">[^<]*Knopf funktioniert nicht/', $html, $m);
        $this->assertNotEmpty($m, 'Ersatzlink-Absatz nicht gefunden');
        $this->assertStringNotContainsString('break-all', $m[1]);
        $this->assertStringContainsString('overflow-wrap:anywhere', $m[1]);
    }

    #[Test]
    public function fuss_und_ersatzlink_werden_auf_dem_telefon_auf_14_pixel_angehoben(): void
    {
        $html = $this->html($this->vollstaendig());

        $this->assertMatchesRegularExpression('/@media \(max-width: 620px\) \{[^\n]*\.np-klein \{ font-size: 14px !important; \}/', $html);
        $this->assertMatchesRegularExpression('/<p class="np-muted np-klein"[^>]*>[^<]*Knopf funktioniert nicht/', $html);
        $this->assertMatchesRegularExpression('/<td class="np-pad np-muted np-klein"[^>]*>\s*Du erhältst diese Mail/', $html);
    }
}
