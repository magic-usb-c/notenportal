<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Von Hand erfasster Wert eines Blatts «manuell» (IPA, Schlussarbeit, Abschlussprüfung) für einen Lernenden. */
#[Fillable(['lernender_id', 'knoten_id', 'note_wert', 'datum', 'erfasst_von_benutzer_id', 'aktualisiert_von_benutzer_id'])]
#[Table(name: 'notenbaum_positionen', key: 'position_id')]
class NotenbaumPosition extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public function knoten(): BelongsTo
    {
        return $this->belongsTo(NotenbaumKnoten::class, 'knoten_id', 'knoten_id');
    }

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    protected function casts(): array
    {
        return ['note_wert' => 'float', 'datum' => 'date'];
    }
}
