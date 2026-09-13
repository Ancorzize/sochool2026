<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Academic\Models\Course;
use App\Jobs\ReportCard\GenerateCourseReportCardPdfsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class BulkReportCardPdfService
{
    /**
     * Dispatch a batch of jobs for course report card PDF generation
     *
     * @param int $schoolId
     * @param array $data
     * @param int|null $campusId
     * @param int|null $userId
     * @return array
     * @throws ValidationException
     */
    public function dispatchPdfBatch(int $schoolId, array $data, ?int $campusId = null, ?int $userId = null): array
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

        // 2. Resolve and validate eligible courses within tenant
        $coursesQuery = Course::where('school_id', $schoolId);

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

        $academicPeriodId = !empty($data['academic_period_id']) ? (int) $data['academic_period_id'] : null;

        // 3. Create TenantAwareJob instances for each course
        $jobs = [];
        foreach ($courses as $course) {
            $jobs[] = new GenerateCourseReportCardPdfsJob(
                $schoolId,
                $course->id,
                $academicPeriodId,
                $mode,
                $userId
            );
        }

        // 4. Dispatch batch via Laravel Bus
        $batch = Bus::batch($jobs)
            ->name("Generación Masiva de PDFs de Boletines - Escuela {$schoolId}")
            ->withOption('school_id', $schoolId)
            ->withOption('mode', $mode)
            ->dispatch();

        return [
            'batch_id' => $batch->id,
            'status' => 'pending',
            'total_courses' => count($jobs),
            'mode' => $mode,
        ];
    }
}
