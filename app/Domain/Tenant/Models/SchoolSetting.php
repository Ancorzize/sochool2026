<?php

namespace App\Domain\Tenant\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'school_settings';

    protected $fillable = [
        'school_id',
        'date_format',
        'time_format',
        'number_format',
        'locale',
        'encrypted_mail_config',
        'academic_config',
        'attendance_config',
        'notification_config',
    ];

    protected $casts = [
        'academic_config' => 'array',
        'attendance_config' => 'array',
        'notification_config' => 'array',
    ];
}
