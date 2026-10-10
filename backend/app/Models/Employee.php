<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $appends = ['id'];

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'employee_id',
        'name',
        'role',
        'department',
        'email',
        'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function getIdAttribute(): string
    {
        return $this->employee_id;
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id', 'employee_id');
    }
}
