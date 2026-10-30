<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\DezimalKomma;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DezimalKommaTest extends TestCase
{
    private function durch(array $eingabe, bool $json = false): Request
    {
        $request = $json
            ? Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($eingabe))
            : Request::create('/x', 'POST', $eingabe);
        $ergebnis = null;
        (new DezimalKomma)->handle($request, function (Request $r) use (&$ergebnis) {
            $ergebnis = $r;

            return response('');
        });

        return $ergebnis;
    }

    #[Test]
    public function dezimalkomma_in_zahlenfeldern_wird_zum_punkt(): void
    {
        $r = $this->durch([
            'note_wert' => '4,5',
            'gewichtung_prozent' => ' 33,33 ',
            'kategorien' => [3 => ['gewicht_gesamt' => '1,25', 'promotion_min_schnitt' => '4,0']],
            'note_gut' => '5,25',
            'knoten' => [7 => ['gewicht' => '33,5', 'fallnote' => '4,0', 'max_minuspunkte' => '1,5']],
            'gewichtung_prozent_kurz' => ',5',
            'zielwert' => ',5',
            'ziel' => '99,5',
        ]);

        $this->assertSame('4.5', $r->input('note_wert'));
        $this->assertSame('33.33', $r->input('gewichtung_prozent'));
        $this->assertSame('1.25', $r->input('kategorien.3.gewicht_gesamt'));
        $this->assertSame('4.0', $r->input('kategorien.3.promotion_min_schnitt'));
        $this->assertSame('5.25', $r->input('note_gut'));
        $this->assertSame(['gewicht' => '33.5', 'fallnote' => '4.0', 'max_minuspunkte' => '1.5'], $r->input('knoten.7'));
        $this->assertSame('.5', $r->input('zielwert'));
        $this->assertSame('99.5', $r->input('ziel'));
        $this->assertSame(',5', $r->input('gewichtung_prozent_kurz'), 'nur die genannten Felder');
        $this->assertSame('4.5', $this->durch(['zielwert' => '4,5'], json: true)->input('zielwert'));
    }

    #[Test]
    public function texte_und_andere_felder_bleiben_unveraendert(): void
    {
        $r = $this->durch([
            'titel' => '4,5',
            'bemerkung' => 'Note 4,5 erreicht',
            'note_wert' => '4,5,6',
            'gewichtung_prozent' => '4.5',
            'zielwert' => 'abc,5',
            'ziel' => 'fach:3,kategorie:1',
        ]);

        $this->assertSame('4,5', $r->input('titel'));
        $this->assertSame('Note 4,5 erreicht', $r->input('bemerkung'));
        $this->assertSame('4,5,6', $r->input('note_wert'));
        $this->assertSame('4.5', $r->input('gewichtung_prozent'));
        $this->assertSame('abc,5', $r->input('zielwert'));
        $this->assertSame('fach:3,kategorie:1', $r->input('ziel'));
    }
}
