<?php

namespace App\Domain\Tenant\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classroom extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'classrooms';

    protected $fillable = [
        'school_id',
        'campus_id',
        'code',
        'name',
        'type',
        'capacity',
        'status',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }
}
