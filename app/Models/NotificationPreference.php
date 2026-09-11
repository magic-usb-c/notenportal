<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Persönliche Wahl eines Benutzers je Anlass (immediate/daily/never). */
#[Fillable(['user_id', 'type', 'frequency'])]
#[Table(name: 'notification_preferences')]
class NotificationPreference extends Model {}
