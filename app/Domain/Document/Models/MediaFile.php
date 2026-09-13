<?php

namespace App\Domain\Document\Models;

use App\Domain\Document\Enums\FileCategoryEnum;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class MediaFile extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'media_files';

    protected $fillable = [
        'uuid',
        'school_id',
        'entity_type',
        'entity_id',
        'file_category',
        'file_name',
        'original_name',
        'mime_type',
        'size_bytes',
        'disk',
        'path',
        'url',
        'is_public',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'file_category' => FileCategoryEnum::class,
        'size_bytes' => 'integer',
        'is_public' => 'boolean',
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

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
