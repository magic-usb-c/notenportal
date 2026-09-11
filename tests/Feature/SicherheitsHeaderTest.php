<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SicherheitsHeader;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SicherheitsHeaderTest extends TestCase
{
    #[Test]
    public function login_seite_sendet_sicherheits_header(): void
    {
        $antwort = $this->get(route('login'))->assertOk();

        foreach (SicherheitsHeader::HEADER as $name => $wert) {
            $antwort->assertHeader($name, $wert);
        }
    }

    #[Test]
    public function angemeldete_seite_sendet_sicherheits_header(): void
    {
        $this->actingAs(User::factory()->lernender()->create())
            ->get(route('dashboard'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function vorhandene_header_der_antwort_werden_nicht_ueberschrieben(): void
    {
        $antwort = (new SicherheitsHeader)->handle(
            request(),
            fn () => response('x')->header('X-Frame-Options', 'DENY'),
        );

        $this->assertSame('DENY', $antwort->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $antwort->headers->get('X-Content-Type-Options'));
    }
}
