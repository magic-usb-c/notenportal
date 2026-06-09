<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotenKommentar extends Model
{
    protected $table = 'noten_kommentare';
    protected $primaryKey = 'kommentar_id';

    // Nur erstellt_am, kein updated_at (Kommentare sind unveränderlich)
    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = null;

    protected $fillable = ['note_id', 'autor_benutzer_id', 'kommentar_text'];

    protected $casts = [
        'erstellt_am' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id', 'note_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_benutzer_id', 'benutzer_id');
    }
}
