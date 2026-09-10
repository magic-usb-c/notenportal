<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'modul_belegung_id',
    'bezeichnung',
    'ziel_gewicht_summe',
])]
#[Table(name: 'modul_note_gruppen', key: 'gruppe_id')]
class ModulNoteGruppe extends Model
{
    // Laravel auf eure Timestamp-Spalten mappen
    const CREATED_AT = 'erstellt_am';

    const UPDATED_AT = 'aktualisiert_am';

    public function modulBelegung()
    {
        return $this->belongsTo(ModulBelegung::class, 'modul_belegung_id', 'modul_belegung_id');
    }

    protected function casts(): array
    {
        return [
            'ziel_gewicht_summe' => 'decimal:2',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
