<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'module', key: 'modul_id')]
class Modul extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';
}
