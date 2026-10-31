<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackListeAktualisiertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function senden(User $user, string $routeName)
    {
        return $this->actingAs($user)->postJson(route('feedback.store'), [
            'kategorie' => 'idee',
            'text' => 'Die Liste sollte die neue Meldung sofort zeigen.',
            'route_name' => $routeName,
            'url' => '/feedback',
        ]);
    }

    #[Test]
    public function meldung_von_der_meldungsliste_verlangt_neuladen_und_merkt_den_dank_vor(): void
    {
        $user = User::factory()->lernender()->create();

        $this->senden($user, 'feedback.index')
            ->assertCreated()
            ->assertJson(['ok' => true, 'seite_neu' => true]);

        $this->assertSame(__('Danke, deine Meldung ist eingegangen.'), session('success'));

        // Nach dem Neuladen steht die neue Zeile in der Liste, nicht mehr «Noch keine Meldungen».
        $this->actingAs($user)->get(route('feedback.index'))
            ->assertOk()
            ->assertSee('Die Liste sollte die neue Meldung sofort zeigen.')
            ->assertDontSee(__('Noch keine Meldungen'));
    }

    #[Test]
    public function meldung_von_anderen_seiten_laedt_nicht_neu_und_merkt_keinen_dank_vor(): void
    {
        $user = User::factory()->lernender()->create();

        $this->senden($user, 'dashboard')
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $this->assertNull(session('success'));
        $this->assertSame(1, Feedback::where('benutzer_id', $user->benutzer_id)->count());
    }
}
