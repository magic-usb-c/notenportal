<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fach extends Model
{
    use HasFactory;

    protected $table = 'faecher';
    protected $primaryKey = 'fach_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';

    protected $casts = [
        'erstellt_am' => 'datetime',
        'aktualisiert_am' => 'datetime',
    ];
}
