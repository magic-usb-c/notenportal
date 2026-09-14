<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Element der Leistungsbeurteilung eines Moduls (Gewichtung, Richtzeit, Prüfungsform). Ohne Zeitstempel. */
#[Table(name: 'modul_lbv_elemente', key: 'lbv_element_id')]
class ModulLbvElement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'modul_id', 'bezeichnung', 'gewichtung_prozent', 'richtzeit',
        'pruefungsform', 'sozialform', 'beschreibung', 'sortierung',
    ];

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }
}
