<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EinrichtungKategorienTauschTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BasisSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
    }

    /** @return array<int, array<string, mixed>> */
    private function formular(): array
    {
        return DB::table('kategorien')->orderBy('kategorie_id')->get()->mapWithKeys(fn ($k) => [$k->kategorie_id => [
            'name' => $k->name, 'aktiv' => 1, 'rundung_element' => '0.5', 'rundung_schnitt' => '0.1', 'gewicht_gesamt' => '1',
        ]])->all();
    }

    #[Test]
    public function zwei_kategorien_koennen_ihre_namen_tauschen(): void
    {
        $f = $this->formular();
        [$a, $b] = array_slice(array_keys($f), 0, 2);
        $nameA = $f[$a]['name'];
        $nameB = $f[$b]['name'];
        [$f[$a]['name'], $f[$b]['name']] = [$nameB, $nameA];

        $this->post(route('admin.setup.categories'), ['kategorien' => $f])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($nameB, DB::table('kategorien')->where('kategorie_id', $a)->value('name'));
        $this->assertSame($nameA, DB::table('kategorien')->where('kategorie_id', $b)->value('name'));
    }

    #[Test]
    public function name_einer_nicht_uebermittelten_kategorie_ergibt_feldfehler_statt_500(): void
    {
        $f = $this->formular();
        [$a, $b] = array_slice(array_keys($f), 0, 2);
        $nameA = DB::table('kategorien')->where('kategorie_id', $a)->value('name');
        $f[$a]['name'] = $f[$b]['name'];
        unset($f[$b]);

        $this->post(route('admin.setup.categories'), ['kategorien' => $f])
            ->assertSessionHasErrors("kategorien.$a.name");

        $this->assertSame($nameA, DB::table('kategorien')->where('kategorie_id', $a)->value('name'));
    }

    #[Test]
    public function grossschreibung_einer_nicht_uebermittelten_kategorie_gilt_als_doppelt(): void
    {
        $f = $this->formular();
        [$a, $b] = array_slice(array_keys($f), 0, 2);
        $f[$a]['name'] = mb_strtoupper((string) $f[$b]['name']);
        unset($f[$b]);

        $this->post(route('admin.setup.categories'), ['kategorien' => $f])
            ->assertSessionHasErrors("kategorien.$a.name");
    }
}
