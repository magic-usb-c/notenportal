<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modul extends Model
{
    protected $table = 'module';
    protected $primaryKey = 'modul_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';
}
