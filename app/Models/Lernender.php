<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lernender extends Model
{
    use SoftDeletes;

    protected $table = 'lernende';
    protected $primaryKey = 'lernender_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';
    public const DELETED_AT = 'geloescht_am';

    protected $fillable = [
        'benutzer_id',
        'lehrberuf_id',
        'lehrbeginn',
        'lehrende',
    ];

    protected $casts = [
        'lehrbeginn' => 'date',
        'lehrende' => 'date',
        'erstellt_am' => 'datetime',
        'aktualisiert_am' => 'datetime',
        'geloescht_am' => 'datetime',
    ];

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function noten(): HasMany
    {
        return $this->hasMany(Note::class, 'lernender_id', 'lernender_id');
    }

    public function modulBelegungen(): HasMany
    {
        return $this->hasMany(ModulBelegung::class, 'lernender_id', 'lernender_id');
    }
}
