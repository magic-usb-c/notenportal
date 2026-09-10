<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['note_id', 'viewer_benutzer_id', 'gesehen_am'])]
#[Table(name: 'noten_gesehen', key: 'gesehen_id')]
#[WithoutTimestamps]
class NotenGesehen extends Model
{
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id', 'note_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_benutzer_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return [
            'gesehen_am' => 'datetime',
        ];
    }
}
