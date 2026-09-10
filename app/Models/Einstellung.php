<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Betriebsspezifische Werte als Schlüssel/Wert. Zugriff über App\Support\Einstellungen. */
class Einstellung extends Model
{
    protected $table = 'einstellungen';

    protected $primaryKey = 'schluessel';

    protected $keyType = 'string';

    public $incrementing = false;

    public const CREATED_AT = null;

    public const UPDATED_AT = 'aktualisiert_am';

    protected $fillable = ['schluessel', 'wert'];
}
