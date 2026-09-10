<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lehrberuf extends Model
{
    use HasFactory;

    protected $table = 'lehrberufe';

    protected $primaryKey = 'lehrberuf_id';

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    protected $fillable = ['kuerzel', 'name', 'aktiv'];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean'];
    }

    public function lernende(): HasMany
    {
        return $this->hasMany(Lernender::class, 'lehrberuf_id', 'lehrberuf_id');
    }
}
