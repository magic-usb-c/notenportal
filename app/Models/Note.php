<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Notenwert;
use App\Support\NotenSkala;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'lernender_id',
    'kategorie_id',
    'semester_id',
    'fach_id',
    'modul_belegung_id',
    'titel',
    'pruefungsdatum',
    'note_wert',
    'note_stufe',
    'gewichtung_prozent',
    'erfasst_von_benutzer_id',
    'aktualisiert_von_benutzer_id',
])]
#[Table(name: 'noten', key: 'note_id')]
class Note extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Custom Timestamp-Spalten gemäss Schema.
     */
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const DELETED_AT = 'geloescht_am';

    /**
     * Relations, die fast jede Noten-Übersicht braucht (Index, Admin-Views, Berufsbildner-Views).
     */
    public const OVERVIEW_RELATIONS = [
        'kategorie',
        'semester',
        'fach',
        'modulBelegung.modul',
    ];

    /** Gelöschte Note: die verknüpfte Prüfung der Agenda ist wieder offen. */
    protected static function booted(): void
    {
        static::deleted(fn (Note $note) => Pruefung::where('note_id', $note->note_id)->update(['note_id' => null]));
    }

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'kategorie_id', 'kategorie_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function fach(): BelongsTo
    {
        return $this->belongsTo(Fach::class, 'fach_id', 'fach_id');
    }

    public function modulBelegung(): BelongsTo
    {
        return $this->belongsTo(ModulBelegung::class, 'modul_belegung_id', 'modul_belegung_id');
    }

    public function gesehen(): HasMany
    {
        return $this->hasMany(NotenGesehen::class, 'note_id', 'note_id');
    }

    public function kommentare(): HasMany
    {
        return $this->hasMany(NotenKommentar::class, 'note_id', 'note_id')
            ->orderBy('erstellt_am', 'asc');
    }

    /**
     * Wer hat die Note erfasst (für Audit / Admin später).
     */
    public function erfasstVonBenutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erfasst_von_benutzer_id', 'benutzer_id');
    }

    /**
     * Wer hat die Note zuletzt aktualisiert (optional).
     */
    public function aktualisiertVonBenutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aktualisiert_von_benutzer_id', 'benutzer_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query-Scopes (damit Controller sauber bleiben)
    |--------------------------------------------------------------------------
    */

    /**
     * Noten nur für einen Lernenden.
     */
    #[Scope]
    protected function forLernender(Builder $query, int $lernenderId): Builder
    {
        return $query->where('lernender_id', $lernenderId);
    }

    /**
     * Optional nach Kategorie filtern.
     */
    #[Scope]
    protected function filterKategorie(Builder $query, ?int $kategorieId): Builder
    {
        if (! $kategorieId) {
            return $query;
        }

        return $query->where('kategorie_id', $kategorieId);
    }

    /**
     * Optional nach Semester filtern.
     */
    #[Scope]
    protected function filterSemester(Builder $query, ?int $semesterId): Builder
    {
        if (! $semesterId) {
            return $query;
        }

        return $query->where('semester_id', $semesterId);
    }

    /**
     * Standard-Sortierung für Noten-Listen.
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id');
    }

    /**
     * Standard-Relations für Listen laden.
     */
    #[Scope]
    protected function withOverview(Builder $query): Builder
    {
        return $query->with(self::OVERVIEW_RELATIONS);
    }

    /**
     * Typ-Casts für korrekte Datentypen in PHP.
     */
    protected function casts(): array
    {
        return [
            'pruefungsdatum' => 'date',
            'note_wert' => Notenwert::class,
            'gewichtung_prozent' => 'decimal:2',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'geloescht_am' => 'datetime',
        ];
    }

    /** Wert, wie er gelesen wird: Zahl ohne überflüssige Null oder Stufe (A/B/C, dispensiert). */
    public function anzeige(): string
    {
        return $this->note_stufe !== null
            ? NotenSkala::stufeText($this->note_stufe)
            : NotenSkala::format($this->note_wert);
    }
}
