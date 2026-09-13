<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchievementIndicator extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'achievement_indicators';

    protected $fillable = [
        'school_id',
        'competency_id',
        'academic_period_id',
        'code',
        'description',
    ];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class, 'competency_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }
}
