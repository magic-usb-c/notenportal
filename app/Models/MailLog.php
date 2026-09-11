<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versandprotokoll: jede Mail mit Status queued → sent | failed, skipped (nicht zustellbar). */
#[Fillable(['user_id', 'type', 'recipient', 'redirected_to', 'subject', 'status', 'attempts', 'error', 'payload', 'sent_at', 'failed_at'])]
#[Table(name: 'mail_log')]
class MailLog extends Model
{
    public const UPDATED_AT = null;

    public const string QUEUED = 'queued';

    public const string SENT = 'sent';

    public const string FAILED = 'failed';

    public const string SKIPPED = 'skipped';

    public const array STATUS = [
        self::QUEUED => 'In Warteschlange',
        self::SENT => 'Verschickt',
        self::FAILED => 'Fehlgeschlagen',
        self::SKIPPED => 'Nicht zustellbar',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'benutzer_id');
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime', 'created_at' => 'datetime', 'payload' => 'array'];
    }
}
