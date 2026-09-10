<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Note;
use App\Models\Rolle;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BemerkungUndKontenTest extends TestCase
{
    use VerwaltungTestHilfen;

    private const string BEMERKUNG = 'Streng vertraulich XYZ';

    #[Test]
    public function bemerkung_erscheint_nie_in_lernenden_ansichten(): void
    {
        $user = User::factory()->lernender(['bemerkung' => self::BEMERKUNG])->create();
        $note = Note::factory()->create(['lernender_id' => $user->lernender->lernender_id]);

        foreach ([
            route('lernender.dashboard'),
            route('lernender.noten.index', ['semester_id' => $note->semester_id]),
            route('lernender.noten.drucken'),
            route('profile.edit'),
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertDontSee(self::BEMERKUNG);
        }

        $this->assertStringNotContainsString(self::BEMERKUNG, $user->lernender->toJson());
    }

    #[Test]
    public function berufsbildner_und_admin_sehen_die_bemerkung(): void
    {
        $lernender = $this->neuerLernender([], ['bemerkung' => self::BEMERKUNG]);

        foreach (['admin', 'berufsbildner'] as $rolle) {
            $this->actingAs($this->verwalter($rolle, $lernender))
                ->get(route("{$rolle}.lernende.show", $lernender->lernender_id))
                ->assertSee(self::BEMERKUNG);
        }
    }

    #[Test]
    public function benutzerverwaltung_legt_keine_lernenden_an_und_verlangt_starke_passwoerter(): void
    {
        $admin = User::factory()->admin()->create();
        $basis = [
            'vorname' => 'Bea',
            'nachname' => 'Bildnerin',
            'email' => 'bea@example.test',
            'benutzername' => 'bea',
        ];

        $this->actingAs($admin)->post(route('admin.benutzer.store'), $basis + [
            'passwort' => 'Kurz12', 'passwort_confirmation' => 'Kurz12',
            'rolle_id' => Rolle::where('name', 'Berufsbildner')->value('rolle_id'),
        ])->assertSessionHasErrors('passwort');

        $this->actingAs($admin)->post(route('admin.benutzer.store'), $basis + [
            'passwort' => 'Sicher12345', 'passwort_confirmation' => 'Sicher12345',
            'rolle_id' => Rolle::where('name', 'Lernender')->value('rolle_id'),
        ])->assertSessionHasErrors('rolle_id');

        $this->actingAs($admin)->post(route('admin.benutzer.store'), $basis + [
            'passwort' => 'Sicher12345', 'passwort_confirmation' => 'Sicher12345',
            'rolle_id' => Rolle::where('name', 'Berufsbildner')->value('rolle_id'),
        ])->assertSessionHasNoErrors();

        $bb = User::where('benutzername', 'bea')->sole();
        $this->assertTrue($bb->passwort_wechsel_noetig);
        $this->assertNotNull($bb->berufsbildner);
    }

    #[Test]
    public function lernenden_konten_laufen_ueber_die_verwaltung(): void
    {
        $admin = User::factory()->admin()->create();
        $lernender = $this->neuerLernender();

        $this->actingAs($admin)->get(route('admin.benutzer.edit', $lernender->benutzer_id))
            ->assertRedirect(route('admin.lernende.show', $lernender->lernender_id));
        $this->actingAs($admin)->post(route('admin.benutzer.toggle-aktiv', $lernender->benutzer_id))->assertNotFound();
        $this->assertTrue($lernender->benutzer->fresh()->aktiv);
    }
}
