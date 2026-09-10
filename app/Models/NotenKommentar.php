<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['note_id', 'autor_benutzer_id', 'kommentar_text'])]
#[Table(name: 'noten_kommentare', key: 'kommentar_id')]
class NotenKommentar extends Model
{
    // Nur erstellt_am, kein updated_at (Kommentare sind unveränderlich)
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = null;

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id', 'note_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_benutzer_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
        ];
    }
}
