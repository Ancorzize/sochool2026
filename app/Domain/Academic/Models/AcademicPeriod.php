<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicPeriod extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'academic_periods';

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'name',
        'period_order',
        'weight_percentage',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weight_percentage' => 'float',
        'period_order' => 'integer',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function isLockedOrClosed(): bool
    {
        return in_array($this->status, ['CLOSED', 'LOCKED'], true);
    }
}
