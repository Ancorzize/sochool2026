<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationalLevel extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'educational_levels';

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'level_order',
    ];

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'educational_level_id')->orderBy('grade_order');
    }
}
