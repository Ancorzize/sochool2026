<?php

namespace App\Domain\Tenant\Models;

use App\Domain\Tenant\Enums\SchoolStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class School extends Model
{
    use HasFactory;

    protected $table = 'schools';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'legal_name',
        'tax_identifier',
        'slug',
        'description',
        'email',
        'phone',
        'secondary_phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'language',
        'currency',
        'status',
    ];

    protected $casts = [
        'status' => SchoolStatusEnum::class,
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function branding(): HasOne
    {
        return $this->hasOne(SchoolBranding::class, 'school_id');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(SchoolSetting::class, 'school_id');
    }

    public function schoolUsers(): HasMany
    {
        return $this->hasMany(SchoolUser::class, 'school_id');
    }
}
