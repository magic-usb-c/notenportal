<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eigener Anhang zu einer Feedback-Meldung (Block G): bis zu drei Dateien, geprüft und – bei
 * Bildern – über GD neu kodiert (App\Services\Feedback\Anhang), abgelegt auf der privaten Disk.
 * Existiert erst ab Migration 2026_09_12_000012 (siehe Feedback::hatAnhaengeTabelle()).
 */
#[Fillable(['feedback_id', 'dateiname', 'pfad', 'mime', 'groesse'])]
#[Table(name: 'feedback_anhaenge', key: 'feedback_anhang_id')]
class FeedbackAnhang extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
        ];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class, 'feedback_id', 'feedback_id');
    }
}
