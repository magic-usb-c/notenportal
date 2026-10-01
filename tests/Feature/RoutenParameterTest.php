<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** IDs in Routen sind Zahlen (Route::patterns in routes/web.php): Unsinn in der Adresse ist ein 404, kein 500. */
class RoutenParameterTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function jeder_id_parameter_nimmt_nur_ziffern(): void
    {
        $offen = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->parameterNames() as $name) {
                if ((str_ends_with($name, '_id') || in_array($name, ['id', 'user'], true)) && ($route->wheres[$name] ?? null) !== '[0-9]+') {
                    $offen[] = "{$route->uri()} → {$name}";
                }
            }
        }

        $this->assertSame([], $offen, "Ohne Zahlenmuster:\n".implode("\n", $offen));
    }

    #[Test]
    public function eine_id_aus_buchstaben_endet_mit_404(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/master-data/professions/abc')
            ->assertNotFound();
    }
}
