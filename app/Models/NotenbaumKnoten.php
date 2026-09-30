<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['baum_id', 'eltern_id', 'code', 'name', 'typ', 'gewicht', 'rundung', 'fallnote', 'max_ungenuegend',
    'max_minuspunkte', 'zaehlt', 'kategorie_id', 'elementtyp', 'sortierung'])]
#[Table(name: 'notenbaum_knoten', key: 'knoten_id')]
class NotenbaumKnoten extends Model
{
    public $timestamps = false;

    public function baum(): BelongsTo
    {
        return $this->belongsTo(Notenbaum::class, 'baum_id', 'baum_id');
    }

    public function eltern(): BelongsTo
    {
        return $this->belongsTo(self::class, 'eltern_id', 'knoten_id');
    }

    public function kinder(): HasMany
    {
        return $this->hasMany(self::class, 'eltern_id', 'knoten_id')->orderBy('sortierung')->orderBy('knoten_id');
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'kategorie_id', 'kategorie_id');
    }

    public function faecher(): BelongsToMany
    {
        return $this->belongsToMany(Fach::class, 'notenbaum_knoten_faecher', 'knoten_id', 'fach_id');
    }

    protected function casts(): array
    {
        return [
            'gewicht' => 'float', 'rundung' => 'float', 'fallnote' => 'float', 'max_ungenuegend' => 'integer',
            'max_minuspunkte' => 'float', 'zaehlt' => 'boolean', 'sortierung' => 'integer',
        ];
    }
}
