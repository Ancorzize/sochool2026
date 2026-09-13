<?php

namespace App\Domain\ReportCard\Models;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Subject;
use App\Domain\Student\Models\Student;
use App\Domain\Teacher\Models\Teacher;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPeriodObservation extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'teacher_period_observations';

    protected $fillable = [
        'school_id',
        'student_id',
        'course_id',
        'subject_id',
        'academic_period_id',
        'teacher_id',
        'observation',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
