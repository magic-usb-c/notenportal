<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Services\Notifications\MailContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «vorlage»: Knopfhöhe und Schriftgrösse der Mailvorlage auf dem Telefon. */
class MailVorlageTelefonMasseTest extends TestCase
{
    private function html(MailContent $c): string
    {
        return view('mail.portal', [
            'c' => $c, 'vorname' => 'Nina', 'anlass' => 'Kommentar', 'abmeldenUrl' => 'https://x.test/u',
            'einstellungenUrl' => 'https://x.test/s', 'betrieb' => 'Beispiel', 'logoUrl' => null, 'portalUrl' => 'https://x.test/',
        ])->render();
    }

    #[Test]
    public function knopf_hat_feste_zeilenhoehe_und_ist_mindestens_44_pixel_hoch(): void
    {
        $html = $this->html(new MailContent(subject: 'x', actionLabel: 'Öffnen', actionUrl: 'https://x.test/a'));

        $this->assertSame(1, preg_match('/<a\b[^>]*class="np-btn"[^>]*style="([^"]*)"/', $html, $m));
        $this->assertMatchesRegularExpression('/font-size:15px;\s*line-height:(\d+)px;\s*mso-line-height-rule:exactly/', $m[1]);

        preg_match('/line-height:(\d+)px/', $m[1], $zeile);
        preg_match('/padding:(\d+)px\s+\d+px/', $m[1], $pad);
        $this->assertGreaterThanOrEqual(44, (int) $zeile[1] + 2 * (int) $pad[1], 'Knopf unter 44 px hoch');
    }

    #[Test]
    public function betriebszeile_und_tabellenkopf_werden_auf_dem_telefon_auf_14_pixel_angehoben(): void
    {
        $html = $this->html(new MailContent(
            subject: 'x',
            table: ['head' => ['Bereich', 'Vorher', 'Neu'], 'rows' => [['Mathe', '4.0', '4.5']]],
        ));

        $this->assertMatchesRegularExpression('/\.np-klein\s*\{\s*font-size:\s*14px\s*!important/', $html);
        $this->assertMatchesRegularExpression('/<td class="np-pad np-klein"[^>]*font-size:13px[^>]*>\s*(?:<img|<span)/', $html);
        preg_match_all('/<th\b[^>]*class="([^"]*)"/', $html, $ths);
        $this->assertCount(3, $ths[1]);
        foreach ($ths[1] as $klassen) {
            $this->assertStringContainsString('np-klein', $klassen);
        }
    }
}
