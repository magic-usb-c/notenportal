<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'lernender_id',
    'modul_id',
    'start_datum',
    'end_datum',
])]
#[Table(name: 'modul_belegungen', key: 'modul_belegung_id')]
#[WithoutTimestamps]
class ModulBelegung extends Model
{
    use HasFactory;

    public function lernender()
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function modul()
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }

    public function noten()
    {
        return $this->hasMany(Note::class, 'modul_belegung_id', 'modul_belegung_id');
    }

    protected function casts(): array
    {
        return [
            'start_datum' => 'date',
            'end_datum' => 'date',
        ];
    }
}
