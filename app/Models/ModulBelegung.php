<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModulBelegung extends Model
{
    protected $table = 'modul_belegungen';
    protected $primaryKey = 'modul_belegung_id';

    public $timestamps = false;

    protected $casts = [
        'start_datum' => 'date',
        'end_datum' => 'date',
    ];

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }

    public function gruppen(): HasMany
    {
        return $this->hasMany(ModulNoteGruppe::class, 'modul_belegung_id', 'modul_belegung_id');
    }
}
