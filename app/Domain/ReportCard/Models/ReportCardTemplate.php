<?php

namespace App\Domain\ReportCard\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCardTemplate extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_templates';

    protected $fillable = [
        'school_id',
        'name',
        'description',
        'header_html',
        'footer_html',
        'layout_config',
        'is_active',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(ReportCardSection::class, 'template_id')->orderBy('section_order');
    }
}
