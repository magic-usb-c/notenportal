<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Stimme «Betrifft mich auch» zu einer Feedback-Hauptmeldung. Kein Fillable: Einträge entstehen
 * nur über insertOrIgnore im Controller (idempotent gegen Doppelstimmen), nie über Mass Assignment.
 * Eine Stimme wird nie geändert, darum kein aktualisiert_am.
 */
#[Table(name: 'feedback_stimmen', key: 'feedback_stimme_id')]
class FeedbackStimme extends Model
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

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    private static ?bool $tabelleVorhanden = null;

    /** Cachiert statisch, ob die Migration 2026_09_12_000011 schon gelaufen ist (Tabelle feedback_stimmen). */
    public static function tabelleVorhanden(): bool
    {
        if (self::$tabelleVorhanden === null) {
            try {
                self::$tabelleVorhanden = Schema::hasTable('feedback_stimmen');
            } catch (Throwable) {
                return false;
            }
        }

        return self::$tabelleVorhanden;
    }
}
