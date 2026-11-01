<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Export eines Notenbaums, den es nicht (mehr) gibt: 404 wie bei den übrigen Baum-Routen. */
class NotenbaumExportNichtVorhandenTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function export_einer_nicht_vorhandenen_baum_id_gibt_404(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.master-data.grade-trees.export', 99999999))
            ->assertNotFound();
    }
}
