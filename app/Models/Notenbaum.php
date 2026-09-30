<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gewichteter Notenbaum eines Lehrberufs (QV) oder Bildungsgangs (BM). Rechnung: App\Services\Auswertung\Notenbaum. */
#[Fillable(['name', 'bezug', 'lehrberuf_id', 'track_typ', 'vorlage', 'beschreibung', 'aktiv'])]
#[Table(name: 'notenbaeume', key: 'baum_id')]
class Notenbaum extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public function lehrberuf(): BelongsTo
    {
        return $this->belongsTo(Lehrberuf::class, 'lehrberuf_id', 'lehrberuf_id');
    }

    public function knoten(): HasMany
    {
        return $this->hasMany(NotenbaumKnoten::class, 'baum_id', 'baum_id')->orderBy('sortierung')->orderBy('knoten_id');
    }

    public function wurzel(): ?NotenbaumKnoten
    {
        return $this->knoten()->whereNull('eltern_id')->first();
    }

    protected function casts(): array
    {
        return ['aktiv' => 'boolean', 'erstellt_am' => 'datetime', 'aktualisiert_am' => 'datetime'];
    }
}
