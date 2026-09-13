<?php

namespace App\Domain\Student\Models;

use App\Domain\Academic\Models\StudentEnrollment;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Student extends Model
{
    use HasFactory, SoftDeletes, BelongsToSchool;

    protected $table = 'students';

    protected $fillable = [
        'uuid',
        'school_id',
        'user_id',
        'student_code',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'nationality',
        'address',
        'city',
        'phone',
        'email',
        'medical_info',
        'emergency_contact',
        'enrollment_date',
        'withdrawal_date',
        'academic_status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'enrollment_date' => 'date',
        'withdrawal_date' => 'date',
        'medical_info' => 'array',
        'emergency_contact' => 'array',
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

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->withPivot(['relationship', 'is_primary', 'is_authorized_pickup', 'receives_communications', 'receives_report_cards']);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
