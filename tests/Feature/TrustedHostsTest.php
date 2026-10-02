<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\TrustedHostPatterns;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * bootstrap/app.php: vertraute Host-Header sind die Einträge aus TRUSTED_HOSTS (install.sh schreibt dort alle
 * Namen und Adressen des Zertifikats) plus der Host aus APP_URL samt Subdomains. Ist TRUSTED_HOSTS leer, gibt es
 * keine Einschränkung (fail-safe nach `git pull` ohne Installer). TrustHosts::at() wird beim Bootstrap des
 * HTTP-Kernels gesetzt (jeder Test) und nach jedem Test geleert; die Middleware überspringt in Tests das Setzen
 * der Muster (Laravel-Standard), darum setzt der Durchstich sie von Hand und räumt sie in tearDown wieder ab.
 */
class TrustedHostsTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    #[Test]
    public function middleware_ist_global_registriert(): void
    {
        $this->assertContains(TrustHosts::class, app(Kernel::class)->getGlobalMiddleware());
    }

    #[Test]
    public function vertraut_den_hosts_aus_der_konfiguration_und_app_url(): void
    {
        config(['app.url' => 'https://notenportal.lab.local', 'app.trusted_hosts' => ' 172.26.14.100, srv-lab-dva-003 ,, localhost ']);

        $muster = app(TrustHosts::class)->hosts();

        $this->assertSame(['^172\.26\.14\.100$', '^srv\-lab\-dva\-003$', '^localhost$', '^(.+\.)?notenportal\.lab\.local$'], $muster);
        $this->assertSame($muster, TrustedHostPatterns::patterns());
        $this->assertMatchesRegularExpression('{'.$muster[0].'}i', '172.26.14.100');
        $this->assertDoesNotMatchRegularExpression('{'.$muster[0].'}i', '172x26x14x100');
        $this->assertMatchesRegularExpression('{'.$muster[3].'}i', 'np.notenportal.lab.local');
    }

    #[Test]
    public function app_url_ohne_schema_und_ipv6_eintraege(): void
    {
        config(['app.url' => 'notenportal.lab.local', 'app.trusted_hosts' => '::1,[fd00::1]']);

        $muster = app(TrustHosts::class)->hosts();

        $this->assertSame(['^\[\:\:1\]$', '^\[fd00\:\:1\]$', '^(.+\.)?notenportal\.lab\.local$'], $muster);
        $this->assertMatchesRegularExpression('{'.$muster[0].'}i', '[::1]');
    }

    #[Test]
    public function ohne_trusted_hosts_keine_einschraenkung(): void
    {
        config(['app.url' => 'https://172.26.14.100', 'app.trusted_hosts' => '']);
        $this->assertSame([], app(TrustHosts::class)->hosts());

        config(['app.trusted_hosts' => ' , ']);
        $this->assertSame([], app(TrustHosts::class)->hosts());
        $this->assertSame([], TrustedHostPatterns::hosts());
    }

    #[Test]
    public function fremder_host_bekommt_400_bekannte_hosts_200(): void
    {
        config(['app.url' => 'https://notenportal.lab.local', 'app.trusted_hosts' => '172.26.14.100']);
        Request::setTrustedHosts(array_filter(app(TrustHosts::class)->hosts()));

        $this->get('http://boese.example/login')->assertStatus(400);
        $this->get('http://172.26.14.100/login')->assertOk();
        $this->get('http://np.notenportal.lab.local/login')->assertOk();
    }
}
