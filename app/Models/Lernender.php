<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'benutzer_id',
    'lehrberuf_id',
    'lehrbeginn',
    'lehrende',
    'bemerkung',
    'klasse_schule',
    'klasse_bms',
])]
#[Hidden(['bemerkung'])]
#[Table(name: 'lernende', key: 'lernender_id')]
class Lernender extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const DELETED_AT = 'geloescht_am';

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function lehrberuf(): BelongsTo
    {
        return $this->belongsTo(Lehrberuf::class, 'lehrberuf_id', 'lehrberuf_id');
    }

    public function noten(): HasMany
    {
        return $this->hasMany(Note::class, 'lernender_id', 'lernender_id');
    }

    public function betreuungen(): HasMany
    {
        return $this->hasMany(Betreuung::class, 'lernender_id', 'lernender_id');
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(LernenderTrack::class, 'lernender_id', 'lernender_id');
    }

    public function modulBelegungen(): HasMany
    {
        return $this->hasMany(ModulBelegung::class, 'lernender_id', 'lernender_id');
    }

    public function pruefungen(): HasMany
    {
        return $this->hasMany(Pruefung::class, 'lernender_id', 'lernender_id');
    }

    public function calendarFeeds(): HasMany
    {
        return $this->hasMany(CalendarFeed::class, 'lernender_id', 'lernender_id');
    }

    public function ziele(): HasMany
    {
        return $this->hasMany(Ziel::class, 'lernender_id', 'lernender_id');
    }

    /** Laufendes Lehrjahr (1-basiert) oder null ohne Lehrbeginn / vor Lehrbeginn. */
    public function lehrjahr(): ?int
    {
        if (! $this->lehrbeginn || $this->lehrbeginn->isFuture()) {
            return null;
        }

        return intdiv((int) $this->lehrbeginn->diffInMonths(now()), 12) + 1;
    }

    /**
     * Einzige Stelle, die festlegt, welche Lernenden ein Benutzer verwalten darf.
     * Admin: alle nicht gelöschten. Berufsbildner: nur heute aktiv betreute. Sonst: keine.
     */
    #[Scope]
    protected function sichtbarFuer(Builder $query, User $user): void
    {
        $query->whereHas('benutzer');

        if ($user->hasRole('Admin')) {
            return;
        }

        $berufsbildnerId = $user->hasRole('Berufsbildner') ? $user->berufsbildner?->berufsbildner_id : null;

        if (! $berufsbildnerId) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('betreuungen', fn (Builder $b) => $b->aktiv()->where('berufsbildner_id', $berufsbildnerId));
    }

    protected function casts(): array
    {
        return [
            'lehrbeginn' => 'date',
            'lehrende' => 'date',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'geloescht_am' => 'datetime',
        ];
    }
}
