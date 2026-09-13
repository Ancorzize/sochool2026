<?php

namespace App\Domain\Academic\Models;

use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\Campus;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'courses';

    protected $fillable = [
        'school_id',
        'campus_id',
        'academic_year_id',
        'grade_id',
        'director_teacher_id',
        'name',
        'shift',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function directorTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'director_teacher_id');
    }

    public function courseEnrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class, 'course_id');
    }

    public function subjectTeachers(): HasMany
    {
        return $this->hasMany(CourseSubjectTeacher::class, 'course_id');
    }
}
