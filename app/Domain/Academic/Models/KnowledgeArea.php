<?php

namespace App\Domain\Academic\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeArea extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'knowledge_areas';

    protected $fillable = [
        'school_id',
        'name',
        'code',
        'description',
        'area_order',
    ];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'knowledge_area_id');
    }
}
