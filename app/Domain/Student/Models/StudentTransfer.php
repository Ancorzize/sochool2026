<?php

namespace App\Domain\Student\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentTransfer extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'student_transfers';

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'student_id',
        'academic_year_id',
        'transfer_type',
        'from_campus_id',
        'to_campus_id',
        'from_course_id',
        'to_course_id',
        'effective_date',
        'reason',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];
}
