<?php

namespace App\Domain\Grading\Models;

use App\Domain\Student\Models\Student;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'student_grades';

    protected $fillable = [
        'school_id',
        'assessment_id',
        'student_id',
        'entered_value',
        'normalized_value',
        'equivalent_numeric_value',
        'grading_scale_item_id',
        'display_value',
        'is_exempt',
        'comments',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'equivalent_numeric_value' => 'float',
        'is_exempt' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function scaleItem(): BelongsTo
    {
        return $this->belongsTo(GradingScaleItem::class, 'grading_scale_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
