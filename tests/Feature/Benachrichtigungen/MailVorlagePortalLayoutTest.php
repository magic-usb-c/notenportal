<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Services\Notifications\MailContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «mail-layout»: Vorschautext, Logo im Dunkelmodus, Outlook-Absicherung. */
class MailVorlagePortalLayoutTest extends TestCase
{
    private function html(MailContent $c, ?string $logoUrl = null): string
    {
        return view('mail.portal', [
            'c' => $c, 'vorname' => 'Nina', 'anlass' => 'Kommentar', 'abmeldenUrl' => 'https://x.test/u',
            'einstellungenUrl' => 'https://x.test/s', 'betrieb' => 'Beispiel', 'logoUrl' => $logoUrl, 'portalUrl' => 'https://x.test/',
        ])->render();
    }

    /** Text des versteckten Vorschau-Divs oder null. */
    private function preheader(string $html): ?string
    {
        if (! preg_match('/<div style="display:none[^"]*">(.*?)<\/div>/s', $html, $m)) {
            return null;
        }

        return trim(html_entity_decode(preg_replace('/&#(8199|65279|847);/', '', $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    #[Test]
    public function ohne_gesetzten_preheader_zeigt_die_vorschau_die_erste_inhaltszeile(): void
    {
        $html = $this->html(new MailContent(subject: 'Neue Note in Mathe', lines: ['Du hast eine 5.5 erhalten.', 'Zweite Zeile.']));

        $this->assertSame('Du hast eine 5.5 erhalten.', $this->preheader($html));
    }

    #[Test]
    public function ohne_zeilen_dient_der_erste_fakt_als_vorschau(): void
    {
        $html = $this->html(new MailContent(subject: 'Erinnerung', facts: ['Datum' => 'Mo 14.09.2026, 08:00', 'Fach' => 'Mathe']));

        $this->assertSame('Datum: Mo 14.09.2026, 08:00', $this->preheader($html));
    }

    #[Test]
    public function gesetzter_preheader_hat_vorrang_und_lange_rueckfaelle_werden_gekuerzt(): void
    {
        $eigen = $this->html(new MailContent(subject: 'x', lines: ['Zeile'], preheader: 'Eigene Vorschau'));
        $this->assertSame('Eigene Vorschau', $this->preheader($eigen));

        $lang = $this->preheader($this->html(new MailContent(subject: 'x', lines: [str_repeat('wort ', 80)])));
        $this->assertLessThanOrEqual(140, mb_strlen($lang));
    }

    #[Test]
    public function preheader_ist_auch_fuer_outlook_versteckt(): void
    {
        $html = $this->html(new MailContent(subject: 'x', lines: ['Zeile']));

        $this->assertMatchesRegularExpression('/<div style="display:none[^"]*mso-hide:all[^"]*">/', $html);
    }

    #[Test]
    public function dunkles_logo_steht_im_dunkelmodus_auf_einem_hellen_chip(): void
    {
        $html = $this->html(new MailContent(subject: 'x'), 'https://x.test/logo.png');

        $this->assertMatchesRegularExpression('/<img\b[^>]*class="np-logo"/', $html);
        $this->assertMatchesRegularExpression('/\.np-logo\s*\{[^}]*background:\s*#ffffff\s*!important/', $html);
    }

    #[Test]
    public function karte_liegt_fuer_outlook_in_einer_festen_geistertabelle(): void
    {
        $html = $this->html(new MailContent(subject: 'x', lines: ['Zeile']));

        $this->assertMatchesRegularExpression('/<!--\[if mso\]>\s*<table[^>]*width="600"[^>]*>/', $html);
        $this->assertStringContainsString('<!--[if mso]></td></tr></table><![endif]-->', $html);
    }

    #[Test]
    public function knopf_traegt_sein_padding_auch_auf_der_zelle(): void
    {
        $html = $this->html(new MailContent(subject: 'x', actionLabel: 'Öffnen', actionUrl: 'https://x.test/a'));

        $this->assertMatchesRegularExpression('/<td\b[^>]*class="np-btn-zelle"[^>]*bgcolor="#2563eb"[^>]*mso-padding-alt:\s*13px 24px/', $html);
        $this->assertStringContainsString('href="https://x.test/a"', $html);
    }
}
