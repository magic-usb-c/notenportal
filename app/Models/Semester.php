<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $table = 'semester';
    protected $primaryKey = 'semester_id';

    public $timestamps = false;

    protected $casts = [
        'start_datum' => 'date',
        'end_datum' => 'date',
    ];
}
