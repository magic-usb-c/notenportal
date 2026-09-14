<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unterlage zu einem Modul (Modulbeschreibung als PDF, Aufgabenblatt …). Sie hängt am Modul,
 * nicht an einer Person: wer sie hochlädt, stellt sie allen zur Verfügung. Die Datei liegt privat
 * unter storage/app/private/module/{modul_id}/ und wird nur über den Controller ausgeliefert.
 */
#[Table(name: 'modul_dokumente', key: 'modul_dokument_id')]
class ModulDokument extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    /** Typen, die der Browser gefahrlos selbst anzeigen darf; alles andere wird heruntergeladen. */
    public const array INLINE = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'modul_id', 'titel', 'originalname', 'pfad', 'mime',
        'groesse', 'sha256', 'hochgeladen_von_benutzer_id',
    ];

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }

    public function hochgeladenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hochgeladen_von_benutzer_id', 'benutzer_id');
    }

    public function endung(): string
    {
        return strtolower(pathinfo($this->pfad, PATHINFO_EXTENSION)) ?: 'bin';
    }
}
