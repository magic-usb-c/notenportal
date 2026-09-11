<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Termin aus einem abonnierten Kalender: Lektion (Stundenplan), Termin oder Prüfung.
 * Prüfungen mit erkanntem Modul/Fach werden zusätzlich als Pruefung geführt (pruefung_id).
 */
#[Fillable(['lernender_id', 'calendar_feed_id', 'uid', 'kind', 'summary', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'course_code', 'pruefung_id'])]
#[Table(name: 'calendar_events')]
class CalendarEvent extends Model
{
    public const string LESSON = 'lesson';

    public const string APPOINTMENT = 'appointment';

    public const string EXAM = 'exam';

    public const array KINDS = [self::LESSON => 'Stundenplan', self::APPOINTMENT => 'Termine', self::EXAM => 'Prüfungen'];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(CalendarFeed::class, 'calendar_feed_id');
    }

    public function pruefung(): BelongsTo
    {
        return $this->belongsTo(Pruefung::class, 'pruefung_id', 'pruefung_id');
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'all_day' => 'boolean'];
    }
}
