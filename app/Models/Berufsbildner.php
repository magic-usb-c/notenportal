<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['benutzer_id'])]
#[Table(name: 'berufsbildner', key: 'berufsbildner_id')]
class Berufsbildner extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const DELETED_AT = 'geloescht_am';

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function betreuungen(): HasMany
    {
        return $this->hasMany(Betreuung::class, 'berufsbildner_id', 'berufsbildner_id');
    }
}
