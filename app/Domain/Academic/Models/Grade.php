<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'grades';

    protected $fillable = [
        'school_id',
        'educational_level_id',
        'name',
        'code',
        'grade_order',
    ];

    public function educationalLevel(): BelongsTo
    {
        return $this->belongsTo(EducationalLevel::class, 'educational_level_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'grade_id');
    }
}
