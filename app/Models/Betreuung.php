<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Betreuung extends Model
{
    use HasFactory;

    protected $table = 'betreuungen';

    protected $primaryKey = 'betreuung_id';

    public $timestamps = false;

    protected $fillable = ['berufsbildner_id', 'lernender_id', 'gueltig_von', 'gueltig_bis'];

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
