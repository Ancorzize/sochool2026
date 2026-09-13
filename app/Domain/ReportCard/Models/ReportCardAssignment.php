<?php

namespace App\Domain\ReportCard\Models;

use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\Subject;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardAssignment extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_assignments';

    protected $fillable = [
        'school_id',
        'template_id',
        'educational_level_id',
        'grade_id',
        'course_id',
        'subject_id',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }

    public function educationalLevel(): BelongsTo
    {
        return $this->belongsTo(EducationalLevel::class, 'educational_level_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
