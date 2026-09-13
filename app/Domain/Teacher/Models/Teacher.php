<?php

namespace App\Domain\Teacher\Models;

use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Teacher extends Model
{
    use HasFactory, SoftDeletes, BelongsToSchool;

    protected $table = 'teachers';

    protected $fillable = [
        'uuid',
        'school_id',
        'user_id',
        'teacher_code',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'hire_date',
        'specialty',
        'professional_profile',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
