<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Academic\Models\Course;
use App\Domain\ReportCard\Models\ReportCard;
use App\Jobs\ReportCard\GenerateReportCardZipJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BulkReportCardZipService
{
    /**
     * Dispatch an asynchronous batch for packaging report card PDFs into a ZIP archive
     *
     * @param int $schoolId
     * @param array $data
     * @param int|null $campusId
     * @param int|null $userId
     * @return array
     * @throws ValidationException
     */
    public function dispatchZipBatch(int $schoolId, array $data, ?int $campusId = null, ?int $userId = null): array
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

        // 2. Resolve eligible courses within tenant & optional campus
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

        // 3. Resolve eligible ReportCard records (is_latest = true)
        $cardsQuery = ReportCard::query()
            ->where('school_id', $schoolId)
            ->whereIn('course_id', $courses->pluck('id'))
            ->where('is_latest', true);

        if (!empty($data['academic_period_id'])) {
            $cardsQuery->where('academic_period_id', $data['academic_period_id']);
        }

        $reportCards = $cardsQuery->with('pdfMediaFile')->get();

        if ($reportCards->isEmpty()) {
            throw ValidationException::withMessages([
                'report_cards' => ['No se encontraron boletines vigentes para la selección especificada.'],
            ]);
        }

        // 4. Evaluate strict mode and count missing PDFs
        $strict = filter_var($data['strict'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $missingCount = 0;

        foreach ($reportCards as $card) {
            $media = $card->pdfMediaFile;
            $isValid = $media
                && $media->school_id === $schoolId
                && Storage::disk($media->disk)->exists($media->path);

            if (!$isValid) {
                $missingCount++;
            }
        }

        if ($strict && $missingCount > 0) {
            throw ValidationException::withMessages([
                'strict' => ["No se puede generar el archivo ZIP debido a que existen {$missingCount} boletines sin PDF generado. Por favor ejecute primero la generación masiva de PDFs."],
            ]);
        }

        $selectionMode = $hasCourseId ? 'single_course' : 'multi_course';

        // 5. Dispatch batch via Laravel Bus containing 1 GenerateReportCardZipJob
        $job = new GenerateReportCardZipJob(
            $schoolId,
            $reportCards->pluck('id')->all(),
            $selectionMode,
            $strict,
            $userId
        );

        $batch = Bus::batch([$job])
            ->name("Empaquetado ZIP de Boletines - Escuela {$schoolId}")
            ->withOption('school_id', $schoolId)
            ->withOption('strict', $strict)
            ->withOption('total_report_cards', $reportCards->count())
            ->withOption('missing_count', $missingCount)
            ->dispatch();

        return [
            'batch_id' => $batch->id,
            'status' => 'pending',
            'total_report_cards' => $reportCards->count(),
            'missing_count' => $missingCount,
            'strict' => $strict,
        ];
    }
}
