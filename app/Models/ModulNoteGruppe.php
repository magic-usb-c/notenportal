<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModulNoteGruppe extends Model
{
    protected $table = 'modul_note_gruppen';
    protected $primaryKey = 'gruppe_id';

    // Laravel auf eure Timestamp-Spalten mappen
    const CREATED_AT = 'erstellt_am';
    const UPDATED_AT = 'aktualisiert_am';

    protected $fillable = [
        'modul_belegung_id',
        'bezeichnung',
        'ziel_gewicht_summe',
    ];

    protected $casts = [
        'ziel_gewicht_summe' => 'decimal:2',
        'erstellt_am' => 'datetime',
        'aktualisiert_am' => 'datetime',
    ];

    public function modulBelegung()
    {
        return $this->belongsTo(ModulBelegung::class, 'modul_belegung_id', 'modul_belegung_id');
    }
}
