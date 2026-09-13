<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Jobs\ReportCard\GenerateCourseReportCardsJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class BulkReportCardService
{
    /**
     * Dispatch a batch of jobs for course report card generation
     *
     * @param int $schoolId
     * @param array $data
     * @param int|null $campusId
     * @return array
     * @throws ValidationException
     */
    public function dispatchBatch(int $schoolId, array $data, ?int $campusId = null): array
    {
        // 1. Validate exactly ONE course selection mode is specified
        $hasCourseId = !empty($data['course_id']);
        $hasCourseIds = !empty($data['course_ids']) && is_array($data['course_ids']);
        $hasAllCourses = isset($data['all_courses']) && filter_var($data['all_courses'], FILTER_VALIDATE_BOOLEAN);

        $selectionCount = ($hasCourseId ? 1 : 0) + ($hasCourseIds ? 1 : 0) + ($hasAllCourses ? 1 : 0);

        if ($selectionCount !== 1) {
            throw ValidationException::withMessages([
                'selection' => ['Debe especificar exactamente una modalidad de selección: course_id, course_ids o all_courses=true.'],
            ]);
        }

        // 2. Validate Academic Year and Period ownership within tenant
        $academicYear = AcademicYear::where('school_id', $schoolId)->findOrFail($data['academic_year_id']);
        $academicPeriod = AcademicPeriod::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYear->id)
            ->findOrFail($data['academic_period_id']);

        // 3. Resolve and validate eligible courses
        $coursesQuery = Course::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYear->id);

        if ($campusId) {
            $coursesQuery->where('campus_id', $campusId);
        }

        if ($hasCourseId) {
            $coursesQuery->where('id', $data['course_id']);
        } elseif ($hasCourseIds) {
            $normalizedCourseIds = array_values(array_unique(array_filter($data['course_ids'])));
            $coursesQuery->whereIn('id', $normalizedCourseIds);
        }

        $courses = $coursesQuery->get();

        if ($courses->isEmpty()) {
            throw ValidationException::withMessages([
                'courses' => ['No se encontraron cursos válidos pertenecientes al colegio/sede especificada.'],
            ]);
        }

        $mode = $data['mode'] ?? 'only_missing';
        if (!in_array($mode, ['only_missing', 'regenerate'], true)) {
            throw ValidationException::withMessages([
                'mode' => ['El modo especificado es inválido. Valores permitidos: only_missing, regenerate.'],
            ]);
        }

        $regenerationReason = $data['regeneration_reason'] ?? null;

        // 4. Create TenantAwareJob instances for each course
        $jobs = [];
        foreach ($courses as $course) {
            $jobs[] = new GenerateCourseReportCardsJob(
                $schoolId,
                $course->id,
                $academicYear->id,
                $academicPeriod->id,
                $mode,
                $regenerationReason
            );
        }

        // 5. Dispatch batch via Laravel Bus
        $batch = Bus::batch($jobs)
            ->name("Generación Masiva de Boletines - {$academicYear->name} {$academicPeriod->name}")
            ->withOption('school_id', $schoolId)
            ->withOption('academic_year_id', $academicYear->id)
            ->withOption('academic_period_id', $academicPeriod->id)
            ->withOption('mode', $mode)
            ->dispatch();

        return [
            'batch_id' => $batch->id,
            'status' => 'pending',
            'total_courses' => count($jobs),
            'mode' => $mode,
            'academic_year_id' => $academicYear->id,
            'academic_period_id' => $academicPeriod->id,
        ];
    }
}
