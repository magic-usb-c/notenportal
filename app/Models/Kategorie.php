<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'kategorien', key: 'kategorie_id')]
#[WithoutTimestamps]
class Kategorie extends Model
{
    use HasFactory;
}
