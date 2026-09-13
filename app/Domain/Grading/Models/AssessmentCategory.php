<?php

namespace App\Domain\Grading\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentCategory extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'assessment_categories';

    protected $fillable = [
        'school_id',
        'name',
        'default_weight_percentage',
    ];

    protected $casts = [
        'default_weight_percentage' => 'float',
    ];
}
