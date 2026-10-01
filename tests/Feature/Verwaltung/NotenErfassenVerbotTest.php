<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Lernender;
use App\Models\Note;
use App\Models\Semester;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Berufsbildner legen keine Noten an: die 403-Seite nennt den Grund statt des Standardsatzes
 * (App\Policies\LernenderPolicy::noteAnlegen). Admin darf nachtragen, Lernende erfassen selbst.
 */
class NotenErfassenVerbotTest extends TestCase
{
    use VerwaltungTestHilfen;

    private const string GRUND = 'Noten erfassen die Lernenden selbst.';

    private Lernender $lernender;

    private User $bb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lernender = $this->neuerLernender();
        $this->bb = $this->verwalter('trainer', $this->lernender);
    }

    #[Test]
    public function berufsbildner_sieht_beim_erfassen_den_grund(): void
    {
        $this->actingAs($this->bb)
            ->get(route('trainer.learners.grades.create', $this->lernender->lernender_id))
            ->assertForbidden()
            ->assertSee(self::GRUND)
            ->assertDontSee('Du hast keine Berechtigung, diese Seite zu sehen.')
            ->assertDontSee('This action is unauthorized.');
    }

    #[Test]
    public function berufsbildner_kann_auch_nicht_absenden_oder_importieren(): void
    {
        $id = $this->lernender->lernender_id;

        $this->actingAs($this->bb)->post(route('trainer.learners.grades.store', $id), [])->assertForbidden();
        $this->actingAs($this->bb)->get(route('trainer.learners.grades.import.index', $id))
            ->assertForbidden()
            ->assertSee(self::GRUND);
        $this->assertSame(0, Note::count());
    }

    #[Test]
    public function berufsbildner_sieht_die_schaltflaeche_nicht(): void
    {
        $this->actingAs($this->bb)
            ->get(route('trainer.learners.grades.index', $this->lernender->lernender_id))
            ->assertOk()
            ->assertDontSee(route('trainer.learners.grades.create', $this->lernender->lernender_id));
    }

    #[Test]
    public function admin_darf_erfassen(): void
    {
        $this->bmsTrack($this->lernender, Semester::factory()->create()->semester_id);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.learners.grades.create', $this->lernender->lernender_id))
            ->assertOk();
    }

    #[Test]
    public function lernende_erreichen_die_verwaltungsseite_weiterhin_nicht(): void
    {
        $this->actingAs($this->lernender->benutzer)
            ->get(route('trainer.learners.grades.create', $this->lernender->lernender_id))
            ->assertForbidden()
            ->assertDontSee(self::GRUND);
    }

    #[Test]
    public function fremde_lernende_bleiben_fuer_berufsbildner_404(): void
    {
        $fremder = $this->neuerLernender();

        $this->actingAs($this->bb)
            ->get(route('trainer.learners.grades.create', $fremder->lernender_id))
            ->assertNotFound();
    }
}
