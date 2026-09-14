<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Modul ist gemeinsame Stammdatei: ein Datensatz, den alle sehen und den jede angemeldete
 * Person ergänzen darf. Nichts davon wird je Lernendem kopiert – was eine Person einträgt,
 * steht sofort allen zur Verfügung.
 */
#[Table(name: 'module', key: 'modul_id')]
class Modul extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    protected $fillable = [
        'modul_nummer', 'titel', 'beschreibung', 'link', 'erstellt_von_benutzer_id',
        'version', 'kompetenzfeld', 'kompetenz', 'objekt', 'publiziert_am', 'auslaufend',
        'quelle', 'quelle_stand', 'ziel_gewicht_summe_default', 'aktiv',
    ];

    /** Module aus dem Modulbaukasten gehören dem Katalog: sie zeigen keine eigene Herkunft. */
    public function ausKatalog(): bool
    {
        return $this->quelle === 'modulbaukasten';
    }

    public function handlungsziele(): HasMany
    {
        return $this->hasMany(ModulHandlungsziel::class, 'modul_id')->orderBy('sortierung');
    }

    public function lbvElemente(): HasMany
    {
        return $this->hasMany(ModulLbvElement::class, 'modul_id')->orderBy('sortierung');
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(ModulDokument::class, 'modul_id')->orderByDesc('erstellt_am');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von_benutzer_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return [
            'aktiv' => 'boolean',
            'auslaufend' => 'boolean',
            'publiziert_am' => 'date',
        ];
    }
}
