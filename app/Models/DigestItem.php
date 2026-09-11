<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Eintrag für die Tageszusammenfassung; sent_at gesetzt = verschickt. */
#[Fillable(['user_id', 'type', 'title', 'body', 'url', 'sent_at'])]
#[Table(name: 'notification_digest_items')]
class DigestItem extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
