<?php

namespace Tests\Unit\Mail;

use App\Models\MailLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailLogFehlertextTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function faelle(): array
    {
        return [
            'nicht erreichbar (Symfony)' => ['Connection could not be established with host "127.0.0.1:2525": stream_socket_client(): Unable to connect to 127.0.0.1:2525 (Connection refused)', 'nicht erreichbar'],
            'Verbindung verweigert' => ['Connection refused', 'nicht erreichbar'],
            'Zeitüberschreitung' => ['Connection to smtp.example.com timed out', 'nicht erreichbar'],
            'Namensauflösung' => ['php_network_getaddresses: getaddrinfo for smtp.example.com failed', 'nicht erreichbar'],
            'Anmeldung 535' => ['Failed to authenticate on SMTP server with username "x" using the following authenticators: "LOGIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535 5.7.8 Username and Password not accepted"".', 'Anmeldung'],
            'Authentication' => ['Authentication failed', 'Anmeldung'],
            'Zertifikat' => ['Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed, certificate verify failed', 'Verschlüsselung'],
            'TLS' => ['TLS handshake failed', 'Verschlüsselung'],
            'tls im Hostnamen' => ['Connection could not be established with host "tls://tls.example.ch:587": stream_socket_client(): Unable to connect', 'nicht erreichbar'],
            'Zertifikat bei ssl://' => ['Connection could not be established with host "ssl://smtp.example.ch:465": stream_socket_client(): SSL operation failed with code 1. OpenSSL Error messages: error:0A000086:SSL routines::certificate verify failed', 'Verschlüsselung'],
            'Zertifikat im Hostnamen' => ['Connection could not be established with host "ssl://certificate.example.ch:465": stream_socket_client(): Unable to connect to ssl://certificate.example.ch:465 (Connection refused)', 'nicht erreichbar'],
            'credentials im Hostnamen' => ['php_network_getaddresses: getaddrinfo for credentials.example.ch failed: Name or service not known', 'nicht erreichbar'],
            'certificate im Benutzernamen' => ['Failed to authenticate on SMTP server with username "certificate-admin" using the following authenticators: "LOGIN".', 'Anmeldung'],
            'certificate im Empfänger' => ['Expected response code "250/251/252" but got code "550", with message "550 5.1.1 <certificate@example.ch>: Recipient address rejected: User unknown"', 'fehlgeschlagen'],
            'authentication in der Absenderadresse' => ['Email "authentication@firma.example" does not comply with addr-spec of RFC 2822.', 'fehlgeschlagen'],
            'allgemein' => ['Expected response code "250" but got code "550", with message "550 Mailbox unavailable"', 'fehlgeschlagen'],
            'leer' => ['', 'fehlgeschlagen'],
        ];
    }

    #[Test]
    #[DataProvider('faelle')]
    public function fehlertext_bildet_rohmeldung_auf_deutschen_text_ab(string $roh, string $erwartet): void
    {
        $text = MailLog::fehlertext($roh);

        $this->assertStringContainsString($erwartet, $text);
        $this->assertStringNotContainsString('stream_socket', $text);
        $this->assertStringNotContainsString('Connection', $text);
        $this->assertStringNotContainsString('127.0.0.1', $text);
    }

    #[Test]
    public function fehlertext_ohne_meldung_ist_allgemein(): void
    {
        $this->assertStringContainsString('fehlgeschlagen', MailLog::fehlertext(null));
    }
}
