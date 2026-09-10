<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['berufsbildner_id', 'lernender_id', 'gueltig_von', 'gueltig_bis'])]
#[Table(name: 'betreuungen', key: 'betreuung_id')]
#[WithoutTimestamps]
class Betreuung extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gueltig_von' => 'date',
            'gueltig_bis' => 'date',
        ];
    }

    public function berufsbildner(): BelongsTo
    {
        return $this->belongsTo(Berufsbildner::class, 'berufsbildner_id', 'berufsbildner_id');
    }

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    /** Betreuungen, die heute gelten. */
    #[Scope]
    protected function aktiv(Builder $query): void
    {
        $heute = now()->toDateString();

        $query->where('gueltig_von', '<=', $heute)
            ->where(fn (Builder $q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $heute));
    }
}
