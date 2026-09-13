<?php

namespace App\Domain\User\Models;

use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SchoolUser extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'school_users';

    protected $fillable = [
        'school_id',
        'user_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'school_user_roles', 'school_user_id', 'role_id')
            ->withPivot('school_id');
    }
}
