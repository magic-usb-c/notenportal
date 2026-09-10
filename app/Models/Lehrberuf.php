<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kuerzel', 'name', 'aktiv'])]
#[Table(name: 'lehrberufe', key: 'lehrberuf_id')]
class Lehrberuf extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    protected function casts(): array
    {
        return ['aktiv' => 'boolean'];
    }

    public function lernende(): HasMany
    {
        return $this->hasMany(Lernender::class, 'lehrberuf_id', 'lehrberuf_id');
    }
}
