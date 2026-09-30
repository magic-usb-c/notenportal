<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BasisSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeitentitelTest extends TestCase
{
    #[Test]
    public function sonderzeichen_im_titel_werden_genau_einmal_maskiert(): void
    {
        // Prüferbefund: der Browsertab zeigte «Lehrberufe &amp;amp; Fächer» (Titel über zwei Slots gereicht)
        $this->seed(BasisSeeder::class);
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('admin.setup', 'professions'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>[^<]*Lehrberufe &amp; Fächer – /u', (string) $html);
        $this->assertStringNotContainsString('&amp;amp;', (string) $html);
    }
}
