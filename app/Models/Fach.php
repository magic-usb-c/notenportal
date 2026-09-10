<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'faecher', key: 'fach_id')]
class Fach extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'kategorie_id', 'kategorie_id');
    }

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
