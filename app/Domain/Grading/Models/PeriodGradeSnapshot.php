<?php

namespace App\Domain\Grading\Models;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\Course;
use App\Domain\Student\Models\Student;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodGradeSnapshot extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'period_grade_snapshots';

    protected $fillable = [
        'school_id',
        'student_id',
        'course_id',
        'academic_period_id',
        'calculation_version',
        'snapshot_data',
        'calculated_at',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }
}
