<?php

namespace App\Infrastructure\Tenant\Traits;

use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\TenantContext;
use App\Infrastructure\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->school_id) && TenantContext::hasTenant()) {
                $model->school_id = TenantContext::id();
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
