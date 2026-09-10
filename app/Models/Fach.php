<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'faecher', key: 'fach_id')]
class Fach extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
