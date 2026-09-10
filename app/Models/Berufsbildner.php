<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Berufsbildner extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $table = 'berufsbildner';
    protected $primaryKey = 'berufsbildner_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';
    public const DELETED_AT = 'geloescht_am';

    protected $fillable = ['benutzer_id'];

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function betreuungen(): HasMany
    {
        return $this->hasMany(Betreuung::class, 'berufsbildner_id', 'berufsbildner_id');
    }
}
