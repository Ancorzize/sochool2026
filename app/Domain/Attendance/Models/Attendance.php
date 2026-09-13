<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Subject;
use App\Domain\Student\Models\Student;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'attendances';

    protected $fillable = [
        'school_id',
        'course_id',
        'subject_id',
        'student_id',
        'attendance_status_id',
        'date',
        'is_justified',
        'remarks',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'is_justified' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class, 'attendance_status_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
