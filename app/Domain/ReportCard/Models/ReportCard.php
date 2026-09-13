<?php

namespace App\Domain\ReportCard\Models;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Student\Models\Student;
use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReportCard extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'report_cards';

    protected $fillable = [
        'uuid',
        'school_id',
        'student_id',
        'academic_year_id',
        'academic_period_id',
        'course_id',
        'template_id',
        'version',
        'is_latest',
        'status',
        'regeneration_reason',
        'parent_report_card_id',
        'data_snapshot',
        'pdf_media_file_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_latest' => 'boolean',
        'data_snapshot' => 'array',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }

    public function parentReportCard(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_report_card_id');
    }

    public function pdfMediaFile(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Document\Models\MediaFile::class, 'pdf_media_file_id');
    }
}
