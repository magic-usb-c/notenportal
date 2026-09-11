<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Persönliche Startseite nach dem Login (Profil «Darstellung», Feld «startseite»), nur ohne
 * intended-URL angewendet. Siehe App\Support\Darstellung::STARTSEITEN/startseiteRoute und
 * AuthenticatedSessionController::store(). Der Standardfall («dashboard» bzw. keine Präferenz)
 * bleibt die generische Weiterleitung «/dashboard» – siehe auch LoginTest.
 */
class StartseiteTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function nichtStandardZiele(): array
    {
        return [
            'Lernende: Noten' => ['lernender', 'noten', 'learner.grades.index'],
            'Lernende: Agenda' => ['lernender', 'agenda', 'learner.exams.index'],
            'Berufsbildner: Lernende' => ['berufsbildner', 'lernende', 'trainer.learners.index'],
            'Berufsbildner: Prüfungstermine' => ['berufsbildner', 'pruefungstermine', 'trainer.exams.index'],
            'Admin: Lernende' => ['admin', 'lernende', 'admin.learners.index'],
            'Admin: Prüfungstermine' => ['admin', 'pruefungstermine', 'admin.exams.index'],
        ];
    }

    #[Test]
    #[DataProvider('nichtStandardZiele')]
    public function login_ohne_intended_url_fuehrt_zur_gewaehlten_startseite(string $rolle, string $wert, string $routenname): void
    {
        $user = User::factory()->{$rolle}()->create(['praeferenzen' => ['startseite' => $wert]]);

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect(route($routenname));
    }

    #[Test]
    public function ohne_praeferenz_bleibt_die_generische_weiterleitung(): void
    {
        $user = User::factory()->lernender()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');
    }

    #[Test]
    public function ungueltiger_gespeicherter_wert_faellt_still_auf_dashboard_zurueck(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['startseite' => 'nicht-vorhanden']]);

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');
    }

    #[Test]
    public function startseite_einer_anderen_rolle_ist_fuer_die_eigene_rolle_ungueltig(): void
    {
        // «lernende» ist nur für Berufsbildner/Admin ein gültiges Ziel, nicht für Lernende selbst.
        $user = User::factory()->lernender()->create(['praeferenzen' => ['startseite' => 'lernende']]);

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect('/dashboard');
    }

    #[Test]
    public function intended_url_hat_vorrang_vor_der_persoenlichen_startseite(): void
    {
        $user = User::factory()->lernender()->create(['praeferenzen' => ['startseite' => 'noten']]);

        // Zugriff auf eine geschützte Seite ohne Sitzung merkt die Ziel-URL vor (intended).
        $this->get(route('learner.exams.index'))->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORT])
            ->assertRedirect(route('learner.exams.index'));
    }
}
