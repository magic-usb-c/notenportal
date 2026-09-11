<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Merker «schon benachrichtigt» je Benutzer, Anlass und Gegenstand (z. B. pruefung:12) gegen Doppelversand. */
#[Fillable(['user_id', 'type', 'subject_key'])]
#[Table(name: 'notification_marks')]
class NotificationMark extends Model
{
    public const UPDATED_AT = null;
}
