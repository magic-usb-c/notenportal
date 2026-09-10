<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
#[Table(name: 'rollen', key: 'rolle_id')]
#[WithoutTimestamps]
class Rolle extends Model
{
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
