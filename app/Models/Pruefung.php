<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Geplante Prüfung eines Lernenden; wird beim Eintragen der Note zur Note. */
#[Fillable(['lernender_id', 'fach_id', 'modul_id', 'titel', 'datum', 'gewichtung_prozent'])]
#[Table(name: 'pruefungen', key: 'pruefung_id')]
class Pruefung extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id', 'fach_id');
    }

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }

    public function bezeichnung(): string
    {
        return $this->fach?->name ?? trim(($this->modul?->modul_nummer ?? '').' '.($this->modul?->titel ?? ''));
    }

    public function bezug(): string
    {
        return $this->fach_id ? 'fach:'.$this->fach_id : 'modul:'.$this->modul_id;
    }

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'gewichtung_prozent' => 'float',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
