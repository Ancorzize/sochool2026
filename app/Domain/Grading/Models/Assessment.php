<?php

namespace App\Domain\Grading\Models;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Subject;
use App\Domain\Teacher\Models\Teacher;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'assessments';

    protected $fillable = [
        'school_id',
        'course_id',
        'subject_id',
        'academic_period_id',
        'assessment_category_id',
        'teacher_id',
        'title',
        'description',
        'weight_percentage',
        'due_date',
        'max_score',
    ];

    protected $casts = [
        'weight_percentage' => 'float',
        'max_score' => 'float',
        'due_date' => 'date',
    ];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssessmentCategory::class, 'assessment_category_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function studentGrades(): HasMany
    {
        return $this->hasMany(StudentGrade::class, 'assessment_id');
    }
}
