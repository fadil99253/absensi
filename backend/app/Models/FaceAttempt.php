<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'employee_id',
        'passed',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return ['passed' => 'boolean'];
    }
}
