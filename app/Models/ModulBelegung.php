<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulBelegung extends Model
{
    use HasFactory;

    protected $table = 'modul_belegungen';
    protected $primaryKey = 'modul_belegung_id';

    public $timestamps = false; // weil keine created_at/updated_at vorhanden

    protected $fillable = [
        'lernender_id',
        'modul_id',
        'start_datum',
        'end_datum',
    ];

    protected $casts = [
        'start_datum' => 'date',
        'end_datum' => 'date',
    ];

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

    public function gruppen()
    {
        return $this->hasMany(ModulNoteGruppe::class, 'modul_belegung_id', 'modul_belegung_id');
    }
}
