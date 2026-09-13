<?php

namespace App\Domain\Tenant\Models;

use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campus extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'campuses';

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'status',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
