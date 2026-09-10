<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    protected $table = 'benutzer';
    protected $primaryKey = 'benutzer_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';
    public const DELETED_AT = 'geloescht_am';

    protected $fillable = [
        'benutzername',
        'email',
        'vorname',
        'nachname',
        'passwort_hash',
        'aktiv',
    ];

    protected $hidden = [
        'passwort_hash',
    ];

    protected $casts = [
        'aktiv' => 'boolean',
        'erstellt_am' => 'datetime',
        'aktualisiert_am' => 'datetime',
        'geloescht_am' => 'datetime',
        'passwort_hash' => 'hashed',
    ];

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
}
