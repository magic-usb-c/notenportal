<?php

namespace App\Models;

use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
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
])]
#[Hidden([
    'passwort_hash',
])]
#[Table(name: 'benutzer', key: 'benutzer_id')]
class User extends Authenticatable
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

        Notifier::dispatch($this, NotificationCatalog::PASSWORD_RESET, new MailContent(
            subject: 'Passwort zurücksetzen',
            title: 'Passwort zurücksetzen',
            lines: ['Du hast angefordert, dein Passwort zurückzusetzen.'],
            facts: ['Gültig für' => $minuten.' Minuten'],
            actionLabel: 'Neues Passwort festlegen',
            actionUrl: route('password.reset', ['token' => $token, 'email' => $this->email]),
            outro: ['Nicht angefordert? Dann ignorieren – dein Passwort bleibt.'],
        ));
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
