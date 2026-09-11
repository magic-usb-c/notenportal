<?php

namespace App\Models;

use App\Http\Middleware\SetLocale;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'benutzername',
    'email',
    'vorname',
    'nachname',
    'passwort_hash',
    'aktiv',
    'passwort_wechsel_noetig',
    'darstellung',
    'kontrast',
    'locale',
])]
#[Hidden([
    'passwort_hash',
])]
#[Table(name: 'benutzer', key: 'benutzer_id')]
class User extends Authenticatable implements HasLocalePreference
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const DELETED_AT = 'geloescht_am';

    public function getAuthPassword(): string
    {
        return (string) $this->passwort_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'passwort_hash';
    }

    /** «Passwort vergessen»-Link, Broker «users» (config/auth.php, Standarddauer). Inaktive Konten bekommen keinen Link. */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        if (! $this->aktiv) {
            return;
        }

        $minuten = (int) config('auth.passwords.users.expire');

        Notifier::dispatch($this, NotificationCatalog::PASSWORD_RESET, fn () => new MailContent(
            subject: __('Passwort zurücksetzen'),
            title: __('Passwort zurücksetzen'),
            lines: [__('Du hast angefordert, dein Passwort zurückzusetzen.')],
            facts: [__('Gültig für') => __(':anzahl Minuten', ['anzahl' => $minuten])],
            actionLabel: __('Neues Passwort festlegen'),
            actionUrl: route('password.reset', ['token' => $token, 'email' => $this->email]),
            outro: [__('Nicht angefordert? Dann ignorieren – dein Passwort bleibt.')],
        ));
    }

    /** Sprache für Mails (Laravel-Notifications und Notifier): eigene Wahl, sonst Standard des Betriebs. */
    public function preferredLocale(): string
    {
        return SetLocale::wahlAktiv()
            ? (SetLocale::gueltig($this->getAttribute('locale')) ?? SetLocale::standard())
            : SetLocale::standard();
    }

    public function rollen(): BelongsToMany
    {
        return $this->belongsToMany(
            Rolle::class,
            'benutzer_rollen',
            'benutzer_id',
            'rolle_id',
            'benutzer_id',
            'rolle_id'
        );
    }

    public function hasRole(string $roleName): bool
    {
        return $this->rollen()
            ->whereRaw('LOWER(name) = LOWER(?)', [$roleName])
            ->exists();
    }

    public function lernender(): HasOne
    {
        return $this->hasOne(Lernender::class, 'benutzer_id', 'benutzer_id');
    }

    public function berufsbildner(): HasOne
    {
        return $this->hasOne(Berufsbildner::class, 'benutzer_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
            'passwort_wechsel_noetig' => 'boolean',
            'kontrast' => 'boolean',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'geloescht_am' => 'datetime',
            'passwort_hash' => 'hashed',
        ];
    }
}
