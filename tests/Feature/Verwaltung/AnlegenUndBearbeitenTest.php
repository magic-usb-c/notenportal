<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnlegenUndBearbeitenTest extends TestCase
{
    use VerwaltungTestHilfen;

    #[Test]
    public function berufsbildner_legt_lernenden_an_und_betreut_ihn(): void
    {
        $bb = User::factory()->berufsbildner()->create();
        $andererBb = User::factory()->berufsbildner()->create();

        $antwort = $this->actingAs($bb)->post(route('trainer.learners.store'), $this->formular([
            'berufsbildner_id' => $andererBb->berufsbildner->berufsbildner_id,
        ]));

        $lernender = $this->angelegterLernender();
        $antwort->assertSessionHasNoErrors()
            ->assertRedirect(route('trainer.learners.show', $lernender->lernender_id))
            ->assertSessionHas('startpasswort');

        $this->assertStartkonto($lernender);
        $this->assertSame([(int) $bb->berufsbildner->berufsbildner_id], $lernender->betreuungen()->aktiv()->pluck('berufsbildner_id')->all());
        $this->actingAs($bb)->get(route('trainer.learners.show', $lernender->lernender_id))->assertOk();
    }

    #[Test]
    public function admin_legt_lernenden_mit_gewaehltem_berufsbildner_an(): void
    {
        $admin = User::factory()->admin()->create();
        $bb = User::factory()->berufsbildner()->create();

        $this->actingAs($admin)
            ->post(route('admin.learners.store'), $this->formular(['berufsbildner_id' => $bb->berufsbildner->berufsbildner_id]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('startpasswort');

        $lernender = $this->angelegterLernender();
        $this->assertStartkonto($lernender);
        $this->assertSame([(int) $bb->berufsbildner->berufsbildner_id], $lernender->betreuungen()->aktiv()->pluck('berufsbildner_id')->all());
        $this->actingAs($bb)->get(route('trainer.learners.show', $lernender->lernender_id))->assertOk();
    }

    #[Test]
    #[DataProvider('verwalterRollen')]
    public function bearbeiten_speichert_stammdaten_und_bemerkung(string $rolle): void
    {
        $lernender = $this->neuerLernender();
        $verwalter = $this->verwalter($rolle, $lernender);
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);

        $this->actingAs($verwalter)
            ->put(route("{$rolle}.learners.update", $lernender->lernender_id), [
                'vorname' => 'Mia',
                'nachname' => 'Muster',
                'email' => 'mia.muster@example.test',
                'benutzername' => 'miamuster',
                'lehrberuf_id' => $lehrberuf->lehrberuf_id,
                'lehrbeginn' => '2025-08-01',
                'lehrende' => '2029-07-31',
                'bemerkung' => 'Braucht Unterstützung in Mathe',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route("{$rolle}.learners.show", $lernender->lernender_id));

        $lernender->refresh();
        $this->assertSame('Mia', $lernender->benutzer->vorname);
        $this->assertSame('miamuster', $lernender->benutzer->benutzername);
        $this->assertSame($lehrberuf->lehrberuf_id, $lernender->lehrberuf_id);
        $this->assertSame('Braucht Unterstützung in Mathe', $lernender->bemerkung);
    }

    #[Test]
    public function ungueltiges_bearbeiten_speichert_nichts(): void
    {
        $lernender = $this->neuerLernender(['vorname' => 'Alt']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.learners.update', $lernender->lernender_id), [
                'vorname' => 'Neu',
                'nachname' => 'Muster',
                'email' => 'kein-mail',
                'benutzername' => 'neu',
                'lehrberuf_id' => $lernender->lehrberuf_id,
                'lehrbeginn' => '2025-08-01',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('Alt', $lernender->benutzer->fresh()->vorname);
    }

    private function formular(array $extra = []): array
    {
        return [
            'vorname' => 'Nina',
            'nachname' => 'Neuling',
            'email' => 'nina.neuling@example.test',
            'benutzername' => 'ninaneuling',
            'lehrberuf_id' => Lehrberuf::factory()->create(['aktiv' => true])->lehrberuf_id,
            'lehrbeginn' => '2025-08-01',
            'lehrende' => '2029-07-31',
            'bemerkung' => 'Intern notiert',
            ...$extra,
        ];
    }

    private function angelegterLernender(): Lernender
    {
        return User::where('benutzername', 'ninaneuling')->sole()->lernender;
    }

    private function assertStartkonto(Lernender $lernender): void
    {
        $benutzer = $lernender->benutzer;

        $this->assertTrue($benutzer->hasRole('Lernender'));
        $this->assertTrue($benutzer->passwort_wechsel_noetig);
        $this->assertTrue($benutzer->aktiv);
        $this->assertSame('Intern notiert', $lernender->bemerkung);
        $this->assertTrue(Validator::make(['p' => session('startpasswort')], ['p' => Password::defaults()])->passes());
    }
}
