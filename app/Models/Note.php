<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use SoftDeletes;

    protected $table = 'noten';
    protected $primaryKey = 'note_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';
    public const DELETED_AT = 'geloescht_am';

    protected $fillable = [
        'lernender_id',
        'kategorie_id',
        'semester_id',
        'fach_id',
        'modul_belegung_id',
        'gruppe_id',
        'titel',
        'pruefungsdatum',
        'note_wert',
        'gewichtung_prozent',
        'erfasst_von_benutzer_id',
        'aktualisiert_von_benutzer_id',
    ];

    protected $casts = [
        'pruefungsdatum' => 'date',
        'note_wert' => 'decimal:1',
        'gewichtung_prozent' => 'decimal:2',
        'erstellt_am' => 'datetime',
        'aktualisiert_am' => 'datetime',
        'geloescht_am' => 'datetime',
    ];

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'kategorie_id', 'kategorie_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id', 'fach_id');
    }

    public function modulBelegung(): BelongsTo
    {
        return $this->belongsTo(ModulBelegung::class, 'modul_belegung_id', 'modul_belegung_id');
    }

    public function gruppe(): BelongsTo
    {
        return $this->belongsTo(ModulNoteGruppe::class, 'gruppe_id', 'gruppe_id');
    }
}
