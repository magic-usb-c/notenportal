<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModulNoteGruppe extends Model
{
    protected $table = 'modul_note_gruppen';
    protected $primaryKey = 'gruppe_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';

    public function belegung(): BelongsTo
    {
        return $this->belongsTo(ModulBelegung::class, 'modul_belegung_id', 'modul_belegung_id');
    }
}
