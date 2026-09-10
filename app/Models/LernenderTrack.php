<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LernenderTrack extends Model
{
    protected $table = 'lernender_tracks';

    protected $primaryKey = 'lernender_track_id';

    public $timestamps = false;

    protected $fillable = ['lernender_id', 'track_typ', 'start_datum', 'end_datum', 'start_semester_id', 'end_semester_id'];

    protected function casts(): array
    {
        return [
            'start_datum' => 'date',
            'end_datum' => 'date',
        ];
    }

    public function lernender(): BelongsTo
    {
        return $this->belongsTo(Lernender::class, 'lernender_id', 'lernender_id');
    }

    public function startSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'start_semester_id', 'semester_id');
    }

    public function endSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'end_semester_id', 'semester_id');
    }
}
