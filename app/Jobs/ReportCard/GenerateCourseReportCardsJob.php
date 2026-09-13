<?php

namespace App\Jobs\ReportCard;

use App\Domain\Academic\Models\Course;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\Student\Models\Student;
use App\Infrastructure\Tenant\Contracts\TenantAwareJob;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GenerateCourseReportCardsJob extends TenantAwareJob
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public int $courseId;
    public int $academicYearId;
    public int $academicPeriodId;
    public string $mode;
    public ?string $regenerationReason;

    public function __construct(
        int $schoolId,
        int $courseId,
        int $academicYearId,
        int $academicPeriodId,
        string $mode = 'only_missing',
        ?string $regenerationReason = null
    ) {
        parent::__construct($schoolId);

        $this->courseId = $courseId;
        $this->academicYearId = $academicYearId;
        $this->academicPeriodId = $academicPeriodId;
        $this->mode = $mode;
        $this->regenerationReason = $regenerationReason;
    }

    /**
     * Process report card generation for all enrolled students in the course
     */
    protected function executeJob(): void
    {
        // Cancel execution if job batch has been cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $course = Course::query()
            ->where('school_id', $this->schoolId)
            ->with(['grade.educationalLevel'])
            ->find($this->courseId);

        if (!$course) {
            return;
        }

        // Fetch enrolled active students for this course
        $students = Student::query()
            ->where('school_id', $this->schoolId)
            ->whereHas('enrollments', function ($query) use ($course) {
                $query->where('school_id', $this->schoolId)
                    ->whereIn('status', ['ACTIVE', 'ENROLLED'])
                    ->whereHas('courseEnrollments', function ($cq) use ($course) {
                        $cq->where('school_id', $this->schoolId)
                            ->where('course_id', $course->id)
                            ->where('status', 'ACTIVE');
                    });
            })
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->get();

        $engine = app(ReportCardEngine::class);

        foreach ($students as $student) {
            // Check for existing latest version
            $existingLatest = ReportCard::query()
                ->where('school_id', $this->schoolId)
                ->where('student_id', $student->id)
                ->where('academic_year_id', $this->academicYearId)
                ->where('academic_period_id', $this->academicPeriodId)
                ->where('is_latest', true)
                ->first();

            // 1. Protection Check: PUBLISHED and LOCKED report cards are protected
            if ($existingLatest && in_array($existingLatest->status, ['LOCKED', 'PUBLISHED'], true)) {
                continue;
            }

            // 2. Mode Check: only_missing skips existing non-protected report cards
            if ($this->mode === 'only_missing' && $existingLatest) {
                continue;
            }

            // 3. Generate report card via engine
            try {
                $engine->generateReportCard(
                    $this->schoolId,
                    $student->id,
                    $this->academicYearId,
                    $this->academicPeriodId,
                    $course,
                    $this->regenerationReason
                );
            } catch (ValidationException $e) {
                // Ignore validation exception (e.g. protected status caught by engine)
                continue;
            } catch (\Throwable $e) {
                Log::error("Error generating report card for student {$student->id} in course {$course->id}: " . $e->getMessage(), [
                    'school_id' => $this->schoolId,
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'exception' => $e,
                ]);
            }
        }
    }
}
