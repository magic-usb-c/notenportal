<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;

/** Betriebsspezifische Werte als Schlüssel/Wert. Zugriff über App\Support\Einstellungen. */
#[Fillable(['schluessel', 'wert'])]
#[Table(name: 'einstellungen', key: 'schluessel', keyType: 'string')]
#[WithoutIncrementing]
class Einstellung extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = 'aktualisiert_am';
}
