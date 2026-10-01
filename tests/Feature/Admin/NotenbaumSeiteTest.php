<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Lehrberuf;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Detailseite eines Notenbaums (admin.master-data.grade-trees.show): Speichern, Fehler beim Feld, Status. */
class NotenbaumSeiteTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private int $baum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $lehrberuf = Lehrberuf::factory()->create(['aktiv' => true]);
        $this->actingAs($this->admin)->post(route('admin.master-data.grade-trees.template'), [
            'vorlage' => 'informatiker-efz-bivo2020', 'lehrberuf_id' => $lehrberuf->lehrberuf_id,
        ]);
        $this->baum = (int) DB::table('notenbaeume')->where('lehrberuf_id', $lehrberuf->lehrberuf_id)->value('baum_id');
    }

    private function seite(): string
    {
        return route('admin.master-data.grade-trees.show', $this->baum);
    }

    /** @return array<int, array<string, string>> */
    private function knoten(): array
    {
        return DB::table('notenbaum_knoten')->where('baum_id', $this->baum)->get()->mapWithKeys(fn ($k) => [$k->knoten_id => [
            'name' => $k->name, 'gewicht' => (string) (float) $k->gewicht, 'zaehlt' => '1',
        ]])->all();
    }

    #[Test]
    public function speichern_steht_in_der_symbolleiste_und_gehoert_zum_formular(): void
    {
        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee('<form id="notenbaum"', false)
            ->assertSee('form="notenbaum"', false)
            ->assertDontSee('@container', false)
            ->assertDontSee('@max-5xl', false);
    }

    #[Test]
    public function ein_fehler_im_knoten_markiert_sein_feld_und_steht_ueber_der_tabelle(): void
    {
        $knoten = $this->knoten();
        $id = (int) DB::table('notenbaum_knoten')->where('baum_id', $this->baum)->where('code', 'ipa')->value('knoten_id');
        $knoten[$id]['gewicht'] = '-1';

        $this->actingAs($this->admin)->from($this->seite())
            ->put(route('admin.master-data.grade-trees.update', $this->baum), ['name' => 'QV', 'knoten' => $knoten])
            ->assertRedirect($this->seite())
            ->assertSessionHasErrors("knoten.$id.gewicht");

        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee('aria-describedby="k'.$id.'-gewicht-fehler"', false)
            ->assertSee('<li id="k'.$id.'-gewicht-fehler">', false);
    }

    #[Test]
    public function aktiv_ohne_marke_inaktiv_mit_marke_und_deaktivieren_fragt_nach(): void
    {
        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee(__('Notenbaum «:name» deaktivieren?', ['name' => 'Informatiker/in EFZ (BiVo 2020)']))
            ->assertDontSee('<span class="np-marke text-muted">'.__('Inaktiv').'</span>', false);

        DB::table('notenbaeume')->where('baum_id', $this->baum)->update(['aktiv' => false]);

        $this->actingAs($this->admin)->get($this->seite())
            ->assertOk()
            ->assertSee('<span class="np-marke text-muted">'.__('Inaktiv').'</span>', false)
            ->assertSee(__('Aktivieren'));
    }
}
