<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Models\User;
use App\Notifications\PortalMail;
use App\Services\Notifications\MailContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «mailvorlagen». */
class MailVorlagenFunktionspruefungTest extends TestCase
{
    private function html(MailContent $c): string
    {
        return view('mail.portal', [
            'c' => $c, 'vorname' => 'Nina', 'anlass' => 'Kommentar', 'abmeldenUrl' => 'https://x.test/u',
            'einstellungenUrl' => 'https://x.test/s', 'betrieb' => 'Beispiel', 'logoUrl' => null, 'portalUrl' => 'https://x.test/',
        ])->render();
    }

    #[Test]
    public function textversion_gibt_sonderzeichen_unveraendert_aus_html_bleibt_escaped(): void
    {
        $user = User::factory()->lernender()->create();
        $c = new MailContent(
            subject: 'Betreff',
            lines: ['Zeile 2 & mehr', "Müller & Söhne's «Fach» \"x\" <b>"],
            facts: ['A&B' => "O'Neil"],
            sections: [['title' => 'Kommentar', 'text' => "Tom & Jerry\n- Punkt & Strich"]],
            actionLabel: 'Öffnen & los',
            actionUrl: 'https://x.test/a?x=1&y=2',
            outro: ['Gruss & Dank'],
            table: ['head' => ['A&B', 'C'], 'rows' => [['x & y', "it's"]]],
            items: [['group' => 'G&H', 'title' => 'T&T', 'text' => "Text's", 'url' => 'https://x.test/a?x=1&y=2']],
        );
        config(['mail.default' => 'array']);
        $user->notifyNow(new PortalMail('grade_added', $c));
        $msg = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $text = $msg->getTextBody();

        foreach (['Zeile 2 & mehr', "Müller & Söhne's «Fach» \"x\" <b>", "A&B: O'Neil", 'Tom & Jerry', 'Punkt & Strich',
            'Öffnen & los: https://x.test/a?x=1&y=2', 'Gruss & Dank', 'A&B | C', "x & y | it's", 'T&T', "Text's"] as $erwartet) {
            $this->assertStringContainsString($erwartet, $text);
        }
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringNotContainsString('&#039;', $text);
        $this->assertStringNotContainsString('&quot;', $text);
        $this->assertStringNotContainsString('&lt;', $text);

        // Gegenprobe: die HTML-Fassung escaped weiterhin und lässt kein rohes Tag durch.
        $html = $this->html($c);
        $this->assertStringContainsString('Zeile 2 &amp; mehr', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    #[Test]
    public function lange_woerter_ohne_leerzeichen_werden_in_jedem_textblock_umbrochen(): void
    {
        $lang = 'https://example.com/'.str_repeat('abcdef', 12).'?x=1';
        $c = new MailContent(
            subject: 'Neuer Kommentar '.$lang,
            lines: ['Zeile '.$lang],
            facts: ['Fach' => $lang],
            sections: [['title' => 'Kommentar', 'text' => "Siehe {$lang}\n- Punkt {$lang}"]],
            outro: ['Ende '.$lang],
            table: ['head' => ['A', 'B'], 'rows' => [[$lang, 'x']]],
            items: [['title' => 'Titel '.$lang, 'text' => 'Text '.$lang, 'url' => null]],
        );
        $html = $this->html($c);

        preg_match_all('/<(h1|p|li|td|div|span)\b([^>]*)>([^<]*'.preg_quote($lang, '/').'[^<]*)</', $html, $treffer, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(9, count($treffer), 'erwartet: Titel, Zeile, Fakt, Absatz, Listenpunkt, Outro, Tabellenzelle, Eintrag, Eintragstext');
        foreach ($treffer as $t) {
            $this->assertMatchesRegularExpression('/overflow-wrap:anywhere/', $t[2], "<{$t[1]}> ohne overflow-wrap: ".$t[0]);
            $this->assertMatchesRegularExpression('/word-break:break-word/', $t[2], "<{$t[1]}> ohne word-break: ".$t[0]);
        }
    }

    #[Test]
    public function alle_links_haben_im_dunkelmodus_eine_lesbare_ueberschreibung(): void
    {
        $c = new MailContent(
            subject: 'X', lines: ['a'], actionLabel: 'Ansehen', actionUrl: 'https://x.test/k',
            items: [['group' => 'G', 'title' => 'Titel', 'url' => 'https://x.test/a']],
        );
        $html = $this->html($c);
        preg_match('/@media \(prefers-color-scheme: dark\) \{(.*?)\n        \}/s', $html, $m);
        $dark = $m[1] ?? '';
        $this->assertMatchesRegularExpression('/\.np-link\s*\{[^}]*color:\s*#93c5fd\s*!important/', $dark);
        $this->assertMatchesRegularExpression('/\.np-link-muted\s*\{[^}]*color:\s*#94a3b8\s*!important/', $dark);

        preg_match_all('/<a\s[^>]*>/', $html, $as);
        $this->assertGreaterThanOrEqual(5, count($as[0]));
        foreach ($as[0] as $a) {
            // Der Knopf hat eigene Farben (weiss auf Akzent); alle übrigen Links brauchen eine Klasse mit Dark-Override.
            if (str_contains($a, 'np-btn')) {
                continue;
            }
            $this->assertMatchesRegularExpression('/class="[^"]*\bnp-link(-muted)?\b/', $a, 'Link ohne Dark-Klasse: '.$a);
        }
    }

    #[Test]
    public function dreispaltige_tabelle_laeuft_nicht_ueber_die_mailbreite(): void
    {
        $c = new MailContent(
            subject: 'Semesterabschluss',
            table: ['head' => ['Lernender', 'Semesterschnitt', 'Ungenügende Elemente'], 'rows' => [['Nina Beispiel', '4.8', '1']]],
        );
        $html = $this->html($c);
        preg_match_all('/<th\b[^>]*style="([^"]*)"/', $html, $ths);
        $this->assertCount(3, $ths[1]);
        foreach ($ths[1] as $stil) {
            // Grossbuchstaben mit breitem Zeichenabstand machten die Kopfzellen unteilbar breit.
            $this->assertStringNotContainsString('letter-spacing:.06em', $stil);
            $this->assertStringContainsString('font-size:13px', $stil);
            $this->assertStringContainsString('padding:8px 4px', $stil);
        }
    }
}
