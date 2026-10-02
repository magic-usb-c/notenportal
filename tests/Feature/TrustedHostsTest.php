<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustHosts;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * bootstrap/app.php: vertraute Host-Header sind der Host aus APP_URL (samt Subdomains) und die Einträge
 * aus TRUSTED_HOSTS (install.sh schreibt dort alle Namen und Adressen des Zertifikats). Die Middleware
 * selbst ist in Tests abgeschaltet (Laravel-Standard), darum wird hier die Musterliste geprüft.
 */
class TrustedHostsTest extends TestCase
{
    #[Test]
    public function vertraut_app_url_und_den_hosts_aus_der_konfiguration(): void
    {
        config(['app.url' => 'https://notenportal.lab.local', 'app.trusted_hosts' => ' 172.26.14.100, srv-lab-dva-003 ,, localhost ']);

        $muster = app(TrustHosts::class)->hosts();

        $this->assertSame(['^172\.26\.14\.100$', '^srv\-lab\-dva\-003$', '^localhost$', '^(.+\.)?notenportal\.lab\.local$'], $muster);
        $this->assertMatchesRegularExpression('{'.$muster[0].'}i', '172.26.14.100');
        $this->assertDoesNotMatchRegularExpression('{'.$muster[0].'}i', '172x26x14x100');
        $this->assertMatchesRegularExpression('{'.$muster[3].'}i', 'np.notenportal.lab.local');
    }

    #[Test]
    public function ohne_trusted_hosts_bleibt_nur_app_url(): void
    {
        config(['app.url' => 'https://172.26.14.100', 'app.trusted_hosts' => '']);

        $this->assertSame(['^(.+\.)?172\.26\.14\.100$'], app(TrustHosts::class)->hosts());
    }
}
