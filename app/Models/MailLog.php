<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versandprotokoll: jede Mail mit Status queued → sent | failed, skipped (nicht zustellbar); retried = erneut gesendet (neue Zeile). */
#[Fillable(['user_id', 'type', 'recipient', 'redirected_to', 'subject', 'status', 'attempts', 'error', 'payload', 'sent_at', 'failed_at'])]
#[Table(name: 'mail_log')]
class MailLog extends Model
{
    public const UPDATED_AT = null;

    public const string QUEUED = 'queued';

    public const string SENT = 'sent';

    public const string FAILED = 'failed';

    public const string SKIPPED = 'skipped';

    public const string RETRIED = 'retried';

    public const array STATUS = [
        self::QUEUED => 'In Warteschlange',
        self::SENT => 'Verschickt',
        self::FAILED => 'Fehlgeschlagen',
        self::SKIPPED => 'Nicht zustellbar',
        self::RETRIED => 'Erneut gesendet',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'benutzer_id');
    }

    /** Verständlicher deutscher Text zur rohen Transportmeldung (die bleibt unverändert in `error` und im Versandprotokoll). */
    public static function fehlertext(?string $roh): string
    {
        // Adressen und Kontonamen zählen nicht: ein Host wie certificate.example.ch oder ein Empfänger
        // authentication@firma.ch ist weder Zertifikat- noch Anmeldefehler
        $roh = (string) preg_replace(['/[^\s<>"\'()]+@[^\s<>"\'()]+/', '/\b(?:host|username) "[^"]*"/i', '/\b[a-z][a-z0-9+.-]*:\/\/\S+/i', '/\b(?:[a-z0-9-]+\.)+[a-z]{2,}\b/i'], ' ', (string) $roh);

        if (preg_match('/authenticat|\b535\b|username and password|credentials/i', $roh) === 1) {
            return __('Anmeldung am Mailserver abgelehnt – Benutzername und Passwort prüfen.');
        }
        // TLS nur in Grossschrift: ein kleines «tls» ist das Schema der Adresse, kein Verschlüsselungsfehler
        if (preg_match('/certificate|SSL (operation|routines|error|handshake)|enable_crypto|(?-i:\bTLS\b|STARTTLS)/i', $roh) === 1) {
            return __('Die verschlüsselte Verbindung zum Mailserver ist fehlgeschlagen – Zertifikat und Verschlüsselung prüfen.');
        }
        // «Expected response code» ist die Antwort eines erreichten Servers (z. B. «refused by policy»), kein Verbindungsfehler
        if (preg_match('/Expected response code/i', $roh) !== 1
            && preg_match('/could not be established|refused|timed out|timeout|getaddrinfo|getaddresses|stream_socket_client|unable to connect|no route to host|network is unreachable/i', $roh) === 1) {
            return __('Der Mailserver ist nicht erreichbar – Host, Port und Verschlüsselung prüfen.');
        }

        return __('Der Versand ist fehlgeschlagen – Details im Versandprotokoll.');
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime', 'created_at' => 'datetime', 'payload' => 'array'];
    }
}
