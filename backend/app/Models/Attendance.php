<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'date',
        'time',
        'status',
        'latitude',
        'longitude',
        'face_distance',
        'match_score',
        'photo_path',
        'liveness_verified',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'latitude' => 'float',
            'longitude' => 'float',
            'face_distance' => 'float',
            'match_score' => 'float',
            'liveness_verified' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
