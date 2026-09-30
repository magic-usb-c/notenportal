<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Rückfragen vor folgenreichen Aktionen laufen über den eigenen Dialog (<x-bestaetigung>), nie über window.confirm. */
class BestaetigungTest extends TestCase
{
    #[Test]
    public function keine_view_fragt_mit_dem_browserdialog(): void
    {
        $treffer = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($datei) => preg_match('/\bconfirm\(/', $datei->getContents()))
            ->map(fn ($datei) => $datei->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $treffer);
    }

    #[Test]
    public function das_layout_enthaelt_den_dialog_genau_einmal(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="np-bestaetigung"'));
    }
}
