<?php

namespace App\Domain\ReportCard\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardField extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_card_fields';

    protected $fillable = [
        'school_id',
        'section_id',
        'field_key',
        'label',
        'is_visible',
        'field_order',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'field_order' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ReportCardSection::class, 'section_id');
    }
}
