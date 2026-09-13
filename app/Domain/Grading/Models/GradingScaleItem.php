<?php

namespace App\Domain\Grading\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleItem extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'grading_scale_items';

    protected $fillable = [
        'school_id',
        'grading_scale_id',
        'name',
        'code',
        'min_value',
        'max_value',
        'equivalent_numeric_value',
        'label',
        'description',
        'color',
        'item_order',
    ];

    protected $casts = [
        'min_value' => 'float',
        'max_value' => 'float',
        'equivalent_numeric_value' => 'float',
        'item_order' => 'integer',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }
}
