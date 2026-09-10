<?php

namespace App\Models;

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
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'geloescht_am' => 'datetime',
            'passwort_hash' => 'hashed',
        ];
    }
}
