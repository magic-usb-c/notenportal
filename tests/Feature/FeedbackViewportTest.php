<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackViewportTest extends TestCase
{
    #[Test]
    public function viewport_akzeptiert_nur_breite_mal_hoehe(): void
    {
        $this->actingAs(User::factory()->lernender()->create());
        $meldung = ['kategorie' => 'bug', 'text' => 'Knopf reagiert nicht'];

        $this->postJson(route('feedback.store'), $meldung + ['viewport' => '=1+1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('viewport');

        $this->postJson(route('feedback.store'), $meldung + ['viewport' => '1280x900'])
            ->assertCreated();
    }
}
