<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Handlungsziel eines Moduls – aus dem Katalog geerntet oder von Hand ergänzt. Ohne Zeitstempel. */
#[Table(name: 'modul_handlungsziele', key: 'handlungsziel_id')]
class ModulHandlungsziel extends Model
{
    public $timestamps = false;

    protected $fillable = ['modul_id', 'nummer', 'text', 'sortierung'];

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }
}
