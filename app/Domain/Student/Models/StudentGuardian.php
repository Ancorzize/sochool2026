<?php

namespace App\Domain\Student\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'student_guardians';

    protected $fillable = [
        'school_id',
        'student_id',
        'guardian_id',
        'relationship',
        'is_primary',
        'is_authorized_pickup',
        'receives_communications',
        'receives_report_cards',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_authorized_pickup' => 'boolean',
        'receives_communications' => 'boolean',
        'receives_report_cards' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }
}
