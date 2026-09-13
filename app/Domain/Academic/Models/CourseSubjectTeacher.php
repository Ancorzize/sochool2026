<?php

namespace App\Domain\Academic\Models;

use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\Campus;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSubjectTeacher extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'course_subject_teachers';

    protected $fillable = [
        'school_id',
        'campus_id',
        'course_id',
        'subject_id',
        'teacher_id',
        'hours_per_week',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
