<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abonnierte iCal-Adresse eines Lernenden (z. B. Schulnetz-Stundenplan oder -Prüfungen).
 * Die URL enthält meist ein persönliches Geheimnis → verschlüsselt gespeichert, nie ausgegeben.
 */
#[Fillable(['lernender_id', 'label', 'url', 'import_lessons', 'import_appointments', 'import_exams', 'last_synced_at', 'last_status', 'last_error', 'events_count', 'etag', 'last_modified'])]
#[Hidden(['url'])]
#[Table(name: 'calendar_feeds')]
class CalendarFeed extends Model
{
    public const string OK = 'ok';

    public const string ERROR = 'error';

    /** Obergrenze je Lernendem – an einer Stelle, damit Controller und Oberfläche nicht auseinanderlaufen. */
    public const int MAX_PRO_LERNENDEM = 5;

    protected $attributes = ['import_lessons' => true, 'import_appointments' => true, 'import_exams' => true, 'events_count' => 0];

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /** Anzeige ohne Geheimnis: nur Host. */
    public function host(): string
    {
        return (string) parse_url((string) $this->url, PHP_URL_HOST);
    }

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'import_lessons' => 'boolean',
            'import_appointments' => 'boolean',
            'import_exams' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
