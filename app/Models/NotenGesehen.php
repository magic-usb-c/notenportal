<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotenGesehen extends Model
{
    protected $table = 'noten_gesehen';
    protected $primaryKey = 'gesehen_id';
    public $timestamps = false;

    protected $fillable = ['note_id', 'viewer_benutzer_id', 'gesehen_am'];

    protected $casts = [
        'gesehen_am' => 'datetime',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id', 'note_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_benutzer_id', 'benutzer_id');
    }
}
