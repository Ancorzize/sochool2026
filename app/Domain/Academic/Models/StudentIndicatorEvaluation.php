<?php

namespace App\Domain\Academic\Models;

use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Student\Models\Student;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentIndicatorEvaluation extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'student_indicator_evaluations';

    protected $fillable = [
        'school_id',
        'student_id',
        'achievement_indicator_id',
        'academic_period_id',
        'grading_scale_item_id',
        'qualitative_evaluation',
        'recorded_by_user_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AchievementIndicator::class, 'achievement_indicator_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function scaleItem(): BelongsTo
    {
        return $this->belongsTo(GradingScaleItem::class, 'grading_scale_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
