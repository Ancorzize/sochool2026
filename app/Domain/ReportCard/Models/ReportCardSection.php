<?php

namespace App\Domain\ReportCard\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCardSection extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_sections';

    protected $fillable = [
        'school_id',
        'template_id',
        'section_type',
        'title',
        'section_order',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'section_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ReportCardField::class, 'section_id')->orderBy('field_order');
    }
}
