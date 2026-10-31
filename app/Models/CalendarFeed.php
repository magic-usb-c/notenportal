<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
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

    /**
     * Gespeicherte Adresse; null, wenn sie sich nicht entschlüsseln lässt (Datenbank aus einer Sicherung
     * unter einem anderen APP_KEY). Jeder lesende Zugriff auf `url` läuft hierüber, nie über das Attribut.
     */
    public function adresse(): ?string
    {
        try {
            return $this->url === null ? null : (string) $this->url;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Setzt eine neue Adresse. Ist die gespeicherte nicht entschlüsselbar, darf Laravel den Altwert beim
     * Dirty-Vergleich (isDirty, save) nicht mehr entschlüsseln wollen – sonst liesse sich der Kalender nie reparieren.
     */
    public function adresseSetzen(string $url): void
    {
        if ($this->exists && $this->adresse() === null) {
            unset($this->original['url']);
        }
        $this->url = $url;
    }

    /** Anzeige ohne Geheimnis: nur Host; leer, wenn die Adresse nicht lesbar ist. */
    public function host(): string
    {
        return (string) parse_url((string) $this->adresse(), PHP_URL_HOST);
    }

    /** Host oder, wenn nicht ermittelbar, ein Platzhalter – für Listen und Meldungen. */
    public function anzeigehost(): string
    {
        return $this->host() ?: __('Adresse nicht lesbar');
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
