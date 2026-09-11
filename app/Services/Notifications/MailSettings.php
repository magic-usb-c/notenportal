<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Support\Einstellungen;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Mailversand: Standard aus .env, Admin kann in «Betrieb» überschreiben (DB, Passwort verschlüsselt mit APP_KEY).
 * apply() setzt die Laufzeit-Config; wird beim Boot und vor jedem Versand aufgerufen.
 */
final class MailSettings
{
    public const string HOST = 'mail_host';

    public const string PORT = 'mail_port';

    public const string ENCRYPTION = 'mail_encryption';

    public const string USERNAME = 'mail_username';

    public const string PASSWORD = 'mail_password';

    public const string FROM_ADDRESS = 'mail_from_address';

    public const string FROM_NAME = 'mail_from_name';

    public const string REDIRECT_TO = 'mail_redirect_to';

    /** ssl = implizites TLS (465), tls = STARTTLS (587), none = unverschlüsselt */
    public const array ENCRYPTIONS = ['ssl' => 'SSL/TLS (Port 465)', 'tls' => 'STARTTLS (Port 587)', 'none' => 'Keine'];

    private static bool $envCaptured = false;

    /** @var array<string, mixed> */
    private static array $env = [];

    public static function apply(): void
    {
        self::captureEnv();
        $db = Einstellungen::alle();

        if (filled($db[self::HOST] ?? null)) {
            $encryption = $db[self::ENCRYPTION] ?? 'ssl';
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $db[self::HOST],
                'mail.mailers.smtp.port' => (int) ($db[self::PORT] ?? 465),
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.require_tls' => $encryption === 'tls',
                'mail.mailers.smtp.username' => $db[self::USERNAME] ?? null,
                'mail.mailers.smtp.password' => self::password() ?? self::$env['password'],
            ]);
        } else {
            config([
                'mail.default' => self::$env['default'],
                'mail.mailers.smtp.host' => self::$env['host'],
                'mail.mailers.smtp.port' => self::$env['port'],
                'mail.mailers.smtp.scheme' => self::$env['scheme'],
                'mail.mailers.smtp.username' => self::$env['username'],
                'mail.mailers.smtp.password' => self::$env['password'],
            ]);
        }

        config([
            'mail.from.address' => filled($db[self::FROM_ADDRESS] ?? null) ? $db[self::FROM_ADDRESS] : self::$env['from_address'],
            'mail.from.name' => self::fromName(),
        ]);
        Mail::purge('smtp');
    }

    /** Anzeigename: eigener Wert, sonst Betriebsname, sonst App-Name. */
    public static function fromName(): string
    {
        $db = Einstellungen::alle();

        return (string) (filled($db[self::FROM_NAME] ?? null) ? $db[self::FROM_NAME]
            : (filled($db[Einstellungen::BETRIEB_NAME] ?? null) ? 'Notenportal '.$db[Einstellungen::BETRIEB_NAME] : config('app.name')));
    }

    public static function redirectTo(): ?string
    {
        $wert = trim((string) Einstellungen::get(self::REDIRECT_TO, ''));

        return $wert !== '' ? $wert : null;
    }

    /** Werte fürs Formular – Passwort nie im Klartext. */
    public static function values(): array
    {
        self::captureEnv();
        $db = Einstellungen::alle();

        return [
            'source' => filled($db[self::HOST] ?? null) ? 'db' : (self::$env['default'] === 'smtp' ? 'env' : 'none'),
            self::HOST => $db[self::HOST] ?? '',
            self::PORT => $db[self::PORT] ?? '465',
            self::ENCRYPTION => $db[self::ENCRYPTION] ?? 'ssl',
            self::USERNAME => $db[self::USERNAME] ?? '',
            'has_password' => filled($db[self::PASSWORD] ?? null),
            self::FROM_ADDRESS => $db[self::FROM_ADDRESS] ?? '',
            self::FROM_NAME => $db[self::FROM_NAME] ?? '',
            self::REDIRECT_TO => $db[self::REDIRECT_TO] ?? '',
            'env_host' => self::$env['default'] === 'smtp' ? self::$env['host'] : null,
            'env_from' => self::$env['from_address'],
            'effective_from_name' => self::fromName(),
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            self::HOST => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9.-]+$/'],
            self::PORT => ['nullable', 'integer', 'between:1,65535'],
            self::ENCRYPTION => ['required', 'in:'.implode(',', array_keys(self::ENCRYPTIONS))],
            self::USERNAME => ['nullable', 'string', 'max:190'],
            self::PASSWORD => ['nullable', 'string', 'max:190'],
            'mail_password_clear' => ['nullable', 'boolean'],
            self::FROM_ADDRESS => ['nullable', 'email', 'max:190'],
            self::FROM_NAME => ['nullable', 'string', 'max:120'],
            self::REDIRECT_TO => ['nullable', 'email', 'max:190'],
        ];
    }

    /** Leeres Passwortfeld = unverändert; «Passwort entfernen» löscht es. */
    public static function save(array $validated): void
    {
        foreach ([self::HOST, self::PORT, self::ENCRYPTION, self::USERNAME, self::FROM_ADDRESS, self::FROM_NAME, self::REDIRECT_TO] as $key) {
            $wert = trim((string) ($validated[$key] ?? ''));
            Einstellungen::set($key, $wert !== '' ? $wert : null);
        }
        if (! empty($validated['mail_password_clear'])) {
            Einstellungen::set(self::PASSWORD, null);
        } elseif (filled($validated[self::PASSWORD] ?? null)) {
            Einstellungen::set(self::PASSWORD, Crypt::encryptString((string) $validated[self::PASSWORD]));
        }
        self::apply();
    }

    private static function password(): ?string
    {
        $verschluesselt = Einstellungen::get(self::PASSWORD);
        if (blank($verschluesselt)) {
            return null;
        }
        try {
            return Crypt::decryptString($verschluesselt);
        } catch (DecryptException) {
            Log::warning('Mail-Passwort in den Einstellungen lässt sich nicht entschlüsseln (APP_KEY geändert?).');

            return null;
        }
    }

    /** .env-Werte einmal pro Prozess festhalten, damit apply() nach einer DB-Änderung zurückfallen kann. */
    private static function captureEnv(): void
    {
        if (self::$envCaptured) {
            return;
        }
        self::$env = [
            'default' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'from_address' => config('mail.from.address'),
        ];
        self::$envCaptured = true;
    }
}
