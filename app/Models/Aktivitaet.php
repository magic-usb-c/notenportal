<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aktivitätsprotokoll (Audit-Log): ein Eintrag je sicherheitsrelevanter Aktion.
 * Geschrieben ausschliesslich über App\Support\Protokoll::schreiben(), nie direkt.
 */
#[Fillable(['benutzer_id', 'aktion', 'ziel_typ', 'ziel_id', 'ziel_bezeichnung', 'details', 'ip'])]
#[Table(name: 'aktivitaeten')]
class Aktivitaet extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = null;

    /** Handelnde Person – null, wenn das Konto seither gelöscht wurde oder niemand angemeldet war (z. B. fehlgeschlagene Anmeldung). */
    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'erstellt_am' => 'datetime',
        ];
    }
}
