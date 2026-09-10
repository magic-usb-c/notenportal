<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'dokumente', key: 'dokument_id')]
#[Fillable([
    'lernender_id',
    'semester_id',
    'art',
    'titel',
    'originalname',
    'pfad',
    'mime',
    'groesse',
    'sha256',
    'hochgeladen_von_benutzer_id',
])]
class Dokument extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const array ARTEN = ['zeugnis' => 'Zeugnis', 'notenliste' => 'Notenliste', 'sonstiges' => 'Sonstiges'];

    /** Im Browser anzeigbar (mit Sandbox-CSP ausgeliefert). */
    public const array INLINE = ['application/pdf', 'image/png', 'image/jpeg'];

    protected function casts(): array
    {
        return ['groesse' => 'integer', 'erstellt_am' => 'datetime'];
    }

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function hochgeladenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hochgeladen_von_benutzer_id', 'benutzer_id');
    }

    public function endung(): string
    {
        return strtolower(pathinfo($this->pfad, PATHINFO_EXTENSION));
    }

    public function istPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }
}
