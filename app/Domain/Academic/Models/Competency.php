<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'competencies';

    protected $fillable = [
        'school_id',
        'subject_id',
        'educational_level_id',
        'code',
        'name',
        'description',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function educationalLevel(): BelongsTo
    {
        return $this->belongsTo(EducationalLevel::class, 'educational_level_id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(AchievementIndicator::class, 'competency_id');
    }
}
