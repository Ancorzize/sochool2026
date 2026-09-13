<?php

namespace App\Domain\Attendance\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceStatus extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'attendance_statuses';

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'is_absence',
        'is_tardiness',
        'is_justified',
        'requires_justification',
        'color',
        'status_order',
        'is_active',
    ];

    protected $casts = [
        'is_absence' => 'boolean',
        'is_tardiness' => 'boolean',
        'is_justified' => 'boolean',
        'requires_justification' => 'boolean',
        'is_active' => 'boolean',
        'status_order' => 'integer',
    ];
}
