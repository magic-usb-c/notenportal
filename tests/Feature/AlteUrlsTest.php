<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlteUrlsTest extends TestCase
{
    #[Test]
    public function alte_deutsche_pfade_leiten_dauerhaft_auf_die_englischen_um(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/stammdaten/faecher?seite=2')
            ->assertStatus(301)
            ->assertRedirect(url('/admin/master-data/subjects?seite=2'));
        $this->actingAs($admin)->get('/admin/einrichtung/personen')->assertRedirect(url('/admin/setup/people'));
        $this->actingAs($admin)->get('/berufsbildner/lernende')->assertRedirect(url('/trainer/learners'));
        $this->get('/pruefungen')->assertRedirect(url('/exams'));
    }

    #[Test]
    public function unbekannte_pfade_und_post_bleiben_404(): void
    {
        $this->get('/gibt-es-nicht')->assertNotFound();
        $this->get('/noten/unsinn/tief')->assertNotFound();
        $this->assertContains($this->post('/noten')->status(), [404, 405]);
    }
}
