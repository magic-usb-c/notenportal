<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'semester', key: 'semester_id')]
#[WithoutTimestamps]
class Semester extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_datum' => 'date',
            'end_datum' => 'date',
        ];
    }
}
