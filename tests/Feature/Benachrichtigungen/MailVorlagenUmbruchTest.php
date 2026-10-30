<?php

declare(strict_types=1);

namespace Tests\Feature\Benachrichtigungen;

use App\Services\Notifications\MailContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Regressionstests der Funktionsprüfung, Gruppe «mailvorlage»: Umbrüche in der HTML- und Textfassung. */
class MailVorlagenUmbruchTest extends TestCase
{
    private function daten(MailContent $c): array
    {
        return [
            'c' => $c, 'vorname' => 'Nina', 'anlass' => 'Kommentar', 'abmeldenUrl' => 'https://x.test/u',
            'einstellungenUrl' => 'https://x.test/s', 'betrieb' => 'Beispiel', 'logoUrl' => null, 'portalUrl' => 'https://x.test/',
        ];
    }

    private function html(MailContent $c): string
    {
        return view('mail.portal', $this->daten($c))->render();
    }

    private function text(MailContent $c): string
    {
        return view('mail.portal-text', $this->daten($c))->render();
    }

    #[Test]
    public function zahlenzellen_der_mailtabelle_brechen_nicht_mitten_in_der_zahl_um(): void
    {
        $c = new MailContent(
            subject: 'Unter dem Grenzwert',
            table: [
                'head' => ['Fach', 'Alt', 'Neu'],
                'rows' => [['Modul 123 Serverdienste in Betrieb nehmen und Datensicherheit gewährleisten', '4.5', '3.75 / 5']],
            ],
        );
        $html = $this->html($c);

        preg_match_all('/<th\b[^>]*style="([^"]*)"/', $html, $ths);
        $this->assertCount(3, $ths[1]);
        foreach ($ths[1] as $stil) {
            // Köpfe brechen nur an Leerzeichen (kein anywhere); mehrwortige Köpfe sollen weiter umbrechen dürfen.
            $this->assertStringNotContainsString('overflow-wrap:anywhere', $stil);
        }

        preg_match_all('/<td\b[^>]*style="([^"]*)">([^<]*)<\/td>/', $html, $tds, PREG_SET_ORDER);
        $zellen = array_values(array_filter($tds, fn ($t) => str_contains($t[1], 'padding:9px 4px')));
        $this->assertCount(3, $zellen);
        $this->assertStringNotContainsString('white-space:nowrap', $zellen[0][1], 'Namensspalte darf umbrechen');
        $this->assertStringNotContainsString('overflow-wrap:anywhere', $zellen[0][1], 'Namen brechen an Wortgrenzen');
        $this->assertStringContainsString('overflow-wrap:break-word', $zellen[0][1]);
        foreach ([1, 2] as $i) {
            $this->assertStringContainsString('white-space:nowrap', $zellen[$i][1], 'Zahlenzelle «'.$zellen[$i][2].'» ohne nowrap');
            $this->assertStringNotContainsString('overflow-wrap:anywhere', $zellen[$i][1]);
        }
    }

    #[Test]
    public function lange_werte_in_den_zahlenspalten_bleiben_umbruchfaehig(): void
    {
        $lang = 'https://example.com/'.str_repeat('abcdef', 12);
        $html = $this->html(new MailContent(subject: 'X', table: ['head' => ['A', 'B'], 'rows' => [['x', $lang]]]));

        preg_match('/<td\b[^>]*style="([^"]*)">'.preg_quote($lang, '/').'</', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('white-space:nowrap', $m[1]);
        $this->assertStringContainsString('overflow-wrap:anywhere', $m[1]);
    }

    #[Test]
    public function faktenlabel_behalten_normale_woerter_ganz(): void
    {
        $html = $this->html(new MailContent(
            subject: 'Semesterabschluss',
            facts: ['Semesterschnitt' => '4.8', 'Ungenügende Elemente' => '1'],
        ));

        preg_match_all('/<td\b[^>]*style="([^"]*)">(Semesterschnitt|Ungenügende Elemente)</', $html, $labels, PREG_SET_ORDER);
        $this->assertCount(2, $labels);
        foreach ($labels as $l) {
            $this->assertStringNotContainsString('overflow-wrap:anywhere', $l[1], "Label «{$l[2]}» bricht sonst mitten im Wort");
            $this->assertStringNotContainsString('word-break:break-word', $l[1], "Label «{$l[2]}» bricht sonst mitten im Wort");
            $this->assertStringContainsString('overflow-wrap:break-word', $l[1]);
        }
    }

    #[Test]
    public function textfassung_stellt_die_url_eines_eintrags_eingerueckt_unter_den_titel(): void
    {
        $c = new MailContent(
            subject: 'Tagesübersicht',
            lines: ['Das ist passiert:', 'Zweite Zeile'],
            facts: ['Fach' => 'Mathematik'],
            sections: [['title' => 'Kommentar', 'text' => "Eins  \n\n\n\nZwei"]],
            actionLabel: 'Öffnen', actionUrl: 'https://x.test/a',
            outro: ['Gruss'],
            table: ['head' => ['A', 'B'], 'rows' => [['x', '1']]],
            items: [
                ['group' => 'Neue Note', 'title' => 'Titel A', 'text' => 'Kurzer Text', 'url' => 'https://x.test/1'],
                ['group' => 'Neue Note', 'title' => 'Titel B', 'url' => 'https://x.test/2'],
                ['group' => 'Kommentare', 'title' => 'Titel C', 'text' => 'Nur Text'],
                ['title' => 'Ohne Gruppe und Link'],
            ],
        );
        $text = $this->text($c);

        $this->assertStringContainsString("- Titel A – Kurzer Text\n  https://x.test/1\n", $text);
        $this->assertStringContainsString("- Titel B\n  https://x.test/2\n", $text);
        $this->assertStringContainsString("- Titel C – Nur Text\n", $text);
        $this->assertStringContainsString("- Ohne Gruppe und Link\n", $text);
        $this->assertDoesNotMatchRegularExpression('/[ \t]$/m', $text, 'Zeilen enden auf Leerzeichen');
        $this->assertStringNotContainsString("\n\n\n", $text, 'mehr als eine Leerzeile hintereinander');
        // Blöcke sind weiterhin durch genau eine Leerzeile getrennt.
        $this->assertStringContainsString("Tagesübersicht\n\nHallo Nina\n\nDas ist passiert:\n\nZweite Zeile\n\nFach: Mathematik\n\nKOMMENTAR\nEins\n\nZwei\n\nA | B\nx | 1\n\nNEUE NOTE\n- Titel A", $text);
        $this->assertStringContainsString("\n\nKOMMENTARE\n- Titel C", $text);
        $this->assertStringContainsString("\n\nÖffnen: https://x.test/a\n\nGruss\n\n--\n", $text);
    }
}
