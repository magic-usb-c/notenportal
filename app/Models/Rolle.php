<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rolle extends Model
{
    protected $table = 'rollen';
    protected $primaryKey = 'rolle_id';
    public $timestamps = false;

    protected $fillable = ['name'];

    public function benutzer(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'benutzer_rollen',
            'rolle_id',
            'benutzer_id',
            'rolle_id',
            'benutzer_id'
        );
    }
}
