<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Enums\ScaleTypeEnum;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'grading_scales';

    protected $fillable = [
        'school_id',
        'name',
        'scale_type',
        'min_score',
        'max_score',
        'passing_score',
        'decimal_places',
        'rounding_rule',
        'is_default',
    ];

    protected $casts = [
        'scale_type' => ScaleTypeEnum::class,
        'min_score' => 'float',
        'max_score' => 'float',
        'passing_score' => 'float',
        'decimal_places' => 'integer',
        'is_default' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GradingScaleItem::class, 'grading_scale_id')->orderBy('item_order');
    }
}
