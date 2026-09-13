<?php

namespace App\Domain\Grading\Models;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Subject;
use App\Domain\Student\Models\Student;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodFinalGrade extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'period_final_grades';

    protected $fillable = [
        'school_id',
        'course_id',
        'subject_id',
        'student_id',
        'academic_period_id',
        'final_entered_value',
        'numeric_score',
        'grading_scale_item_id',
        'is_recovery',
        'recovery_score',
        'status',
    ];

    protected $casts = [
        'numeric_score' => 'float',
        'recovery_score' => 'float',
        'is_recovery' => 'boolean',
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

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function scaleItem(): BelongsTo
    {
        return $this->belongsTo(GradingScaleItem::class, 'grading_scale_item_id');
    }
}
