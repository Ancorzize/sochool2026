<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPlanSubject extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'academic_plan_subjects';

    protected $fillable = [
        'school_id',
        'academic_plan_id',
        'knowledge_area_id',
        'subject_name',
        'subject_code',
        'hours_per_week',
        'weight_in_area',
    ];

    public function academicPlan(): BelongsTo
    {
        return $this->belongsTo(AcademicPlan::class, 'academic_plan_id');
    }

    public function knowledgeArea(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArea::class, 'knowledge_area_id');
    }
}
