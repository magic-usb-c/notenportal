<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Prüfung in der Agenda eines Lernenden (geplant oder aus dem Schulnetz-Kalender).
 * Sobald die Note eingetragen ist, hängt sie über note_id an der Prüfung; «offen» = ohne Note, nicht abgesagt.
 * `art` unterscheidet Prüfung von Abgabetermin/Meilenstein (Rückmeldung #14, Modulstatus): bewusst keine
 * eigene Tabelle, da Datum/Titel/Gewichtung/Fach-Modul-Bezug hier schon vorhanden sind (docs/audit-backlog.md).
 */
#[Fillable(['lernender_id', 'fach_id', 'modul_id', 'titel', 'art', 'datum', 'uhrzeit', 'dauer_minuten', 'pruefungsart', 'hilfsmittel', 'stoff',
    'notizen', 'raum', 'lehrperson', 'gewichtung_prozent', 'note_id', 'quelle', 'extern_uid', 'abgesagt_am'])]
#[Table(name: 'pruefungen', key: 'pruefung_id')]
class Pruefung extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const string MANUELL = 'manuell';

    public const string ICAL = 'ical';

    public const string ART_PRUEFUNG = 'pruefung';

    public const string ART_ABGABE = 'abgabe';

    private static ?bool $hatArtSpalte = null;

    /** Cachiert statisch, ob die Migration 2026_09_12_000013 (Spalte art) schon gelaufen ist. */
    public static function hatArtSpalte(): bool
    {
        if (self::$hatArtSpalte === null) {
            try {
                self::$hatArtSpalte = Schema::hasColumn('pruefungen', 'art');
            } catch (Throwable) {
                return false;
            }
        }

        return self::$hatArtSpalte;
    }

    /** «pruefung», solange die Spalte fehlt oder kein Wert gesetzt ist – Bestandsdaten sind alle Prüfungen. */
    public function art(): string
    {
        return $this->getAttribute('art') ?? self::ART_PRUEFUNG;
    }

    public function istAbgabe(): bool
    {
        return $this->art() === self::ART_ABGABE;
    }

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id', 'fach_id');
    }

    public function modul(): BelongsTo
    {
        return $this->belongsTo(Modul::class, 'modul_id', 'modul_id');
    }

    /** Verknüpfte Note (gelöschte Noten zählen nicht). */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id', 'note_id')->whereNull('geloescht_am');
    }

    public function dokumente(): HasMany
    {
        return $this->hasMany(Dokument::class, 'pruefung_id', 'pruefung_id');
    }

    /** Ohne Note und nicht abgesagt – zählt als geplante Leistung. */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('note_id'))->whereNull($query->qualifyColumn('abgesagt_am'));
    }

    public function istOffen(): bool
    {
        return $this->note_id === null && $this->abgesagt_am === null;
    }

    public function bezeichnung(): string
    {
        return $this->fach?->name ?? trim(($this->modul?->modul_nummer ?? '').' '.($this->modul?->titel ?? ''));
    }

    public function bezug(): string
    {
        return $this->fach_id ? 'fach:'.$this->fach_id : 'modul:'.$this->modul_id;
    }

    /** Beginn mit Uhrzeit (falls bekannt) in der App-Zeitzone. */
    public function beginn(): CarbonImmutable
    {
        $datum = CarbonImmutable::parse($this->datum->format('Y-m-d'), config('app.timezone'));

        return $this->uhrzeit ? $datum->setTimeFromTimeString((string) $this->uhrzeit) : $datum;
    }

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'gewichtung_prozent' => 'float',
            'dauer_minuten' => 'integer',
            'abgesagt_am' => 'datetime',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
