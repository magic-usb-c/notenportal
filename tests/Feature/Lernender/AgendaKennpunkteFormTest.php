<?php

declare(strict_types=1);

namespace Tests\Feature\Lernender;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\Kategorie;
use App\Models\Modul;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Kennpunkte der Eintragsart tragen die Art nicht nur über die Farbe: Im Mini-Monat der Listenansicht
 * gelten dieselben Punkte wie in Legende und Monatsraster (gefüllt gegen Ring), die Prüfung hat eine eigene Form.
 */
class AgendaKennpunkteFormTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->lernender()->create();
        $modul = Modul::factory()->create();
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $this->user->lernender->lehrberuf_id,
            'modul_id' => $modul->modul_id,
            'kategorie_id' => Kategorie::where('code', 'UEK')->value('kategorie_id') ?? Kategorie::value('kategorie_id'),
        ]);
        $this->actingAs($this->user)->post(route('learner.exams.store'), [
            'bezug' => 'modul:'.$modul->modul_id,
            'datum' => now()->startOfMonth()->addDays(9)->toDateString(),
            'gewichtung_prozent' => 40,
            'titel' => 'LB2',
        ])->assertSessionHasNoErrors();
        $feed = CalendarFeed::create(['lernender_id' => $this->user->lernender->lernender_id, 'url' => 'https://schulnetz.example/geheim']);
        CalendarEvent::create([
            'lernender_id' => $this->user->lernender->lernender_id, 'calendar_feed_id' => $feed->id, 'kind' => CalendarEvent::APPOINTMENT,
            'uid' => 'termin-1', 'summary' => 'Elterngespräch', 'starts_at' => now()->startOfMonth()->addDays(11)->setTime(10, 0), 'all_day' => false,
        ]);
    }

    private function miniMonat(): string
    {
        $html = $this->actingAs($this->user)->get(route('learner.exams.index'))->assertOk()->getContent();
        $start = strpos($html, 'aria-label="'.__('Monatsübersicht').'"');
        $ende = strpos($html, '</aside>', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($ende);

        return substr($html, $start, $ende - $start);
    }

    #[Test]
    public function mini_monat_nutzt_die_kennpunkte_der_legende_statt_eigener_farbpunkte(): void
    {
        $mini = $this->miniMonat();

        $this->assertStringNotContainsString('size-1 rounded-full', $mini);
        $this->assertStringContainsString('size-2 shrink-0 rounded-xs bg-accent', $mini, 'Prüfung: eigene Form (Quadrat mit Radius), gefüllt');
        $this->assertStringContainsString('size-2 shrink-0 rounded-full bg-muted', $mini, 'Schulnetz-Termin: gefüllter Kreis');
    }

    #[Test]
    public function pruefung_und_termin_unterscheiden_sich_in_der_form_nicht_nur_in_der_farbe(): void
    {
        $form = fn (string $art) => $this->renderPunkt($art);

        $this->assertNotSame(
            preg_replace('/bg-\S+|border-\S+/', '', $form('pruefung')),
            preg_replace('/bg-\S+|border-\S+/', '', $form('termin')),
        );
        $this->assertNotSame(
            preg_replace('/bg-\S+|border-\S+/', '', $form('erkannt')),
            preg_replace('/bg-\S+|border-\S+/', '', $form('pruefung')),
            'Prüfung gegen erkannte Prüfung: Form',
        );
    }

    private function renderPunkt(string $art): string
    {
        return view('lernender.agenda._punkt', ['art' => $art])->render();
    }
}
