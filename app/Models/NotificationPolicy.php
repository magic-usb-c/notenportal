<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Admin-Regel je Anlass; fehlt die Zeile, gilt der Standard aus NotificationCatalog. */
#[Fillable(['type', 'enabled', 'mandatory', 'frequency', 'params'])]
#[Table(name: 'notification_policies', key: 'type', keyType: 'string', incrementing: false)]
class NotificationPolicy extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'mandatory' => 'boolean', 'params' => 'array'];
    }
}
