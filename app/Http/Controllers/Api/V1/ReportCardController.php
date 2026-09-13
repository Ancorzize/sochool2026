<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Academic\Models\Course;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Services\BulkReportCardService;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

use App\Domain\ReportCard\Services\ReportCardPdfRenderer;
use App\Domain\ReportCard\Services\ReportCardPdfStorageService;
use App\Domain\Document\Models\MediaFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReportCardController extends Controller
{
    public function __construct(
        protected ReportCardEngine $reportCardEngine,
        protected BulkReportCardService $bulkReportCardService,
        protected ReportCardPdfRenderer $pdfRenderer,
        protected ReportCardPdfStorageService $pdfStorageService
    ) {}

    /**
     * Generate or regenerate a report card for a single student context
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'academic_period_id' => 'required|integer',
            'course_id' => 'required|integer',
            'regeneration_reason' => 'nullable|string|max:500',
        ]);

        $schoolId = TenantContext::id();

        $course = Course::where('school_id', $schoolId)
            ->with(['grade.educationalLevel'])
            ->findOrFail($validated['course_id']);

        $reportCard = $this->reportCardEngine->generateReportCard(
            $schoolId,
            $validated['student_id'],
            $validated['academic_year_id'],
            $validated['academic_period_id'],
            $course,
            $validated['regeneration_reason'] ?? null
        );

        return response()->json([
            'message' => 'Boletín de calificaciones generado exitosamente.',
            'data' => $reportCard->load(['student', 'course', 'academicYear', 'academicPeriod', 'template']),
        ], 201);
    }

    /**
     * Dispatch a batch job for mass report card generation
     */
    public function generateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => 'required|integer',
            'academic_period_id' => 'required|integer',
            'course_id' => 'nullable|integer',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer',
            'all_courses' => 'nullable|boolean',
            'mode' => 'nullable|string|in:only_missing,regenerate',
            'regeneration_reason' => 'nullable|string|max:500',
        ]);

        $schoolId = TenantContext::id();
        $campusId = CampusContext::id();

        $result = $this->bulkReportCardService->dispatchBatch($schoolId, $validated, $campusId);

        return response()->json([
            'message' => 'Proceso masivo de generación de boletines enviado a cola exitosamente.',
            'data' => $result,
        ], 202);
    }

    /**
     * Get execution status of a report card generation batch
     */
    public function batchStatus(string $batchId): JsonResponse
    {
        $schoolId = TenantContext::id();
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote de procesamientos no encontrado.',
            ], 404);
        }

        $options = $batch->options;
        if (!isset($options['school_id']) || (int) $options['school_id'] !== (int) $schoolId) {
            return response()->json([
                'message' => 'Lote de procesamientos no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'is_completed' => $batch->finished(),
                'cancelled_at' => $batch->cancelledAt?->toDateTimeString(),
                'created_at' => $batch->createdAt?->toDateTimeString(),
                'finished_at' => $batch->finishedAt?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * List report cards for tenant with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = TenantContext::id();

        $query = ReportCard::where('school_id', $schoolId)
            ->with(['student', 'course', 'academicYear', 'academicPeriod', 'template']);

        if ($request->has('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->query('course_id'));
        }

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->has('academic_period_id')) {
            $query->where('academic_period_id', $request->query('academic_period_id'));
        }

        if ($request->has('is_latest')) {
            $query->where('is_latest', filter_var($request->query('is_latest'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $reportCards = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $reportCards,
        ]);
    }

    /**
     * Show single report card with snapshot
     */
    public function show(int $id): JsonResponse
    {
        $schoolId = TenantContext::id();

        $reportCard = ReportCard::where('school_id', $schoolId)
            ->with(['student', 'course', 'academicYear', 'academicPeriod', 'template', 'parentReportCard'])
            ->findOrFail($id);

        return response()->json([
            'data' => $reportCard,
        ]);
    }

    /**
     * List all report cards for a specific student
     */
    public function studentReportCards(int $studentId): JsonResponse
    {
        $schoolId = TenantContext::id();

        Student::where('school_id', $schoolId)->findOrFail($studentId);

        $reportCards = ReportCard::where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->with(['course', 'academicYear', 'academicPeriod', 'template'])
            ->orderBy('academic_year_id')
            ->orderBy('academic_period_id')
            ->orderBy('version', 'desc')
            ->get();

        return response()->json([
            'data' => $reportCards,
        ]);
    }

    /**
     * Stream inline PDF rendered strictly from report card data_snapshot
     */
    public function pdf(int $id): Response
    {
        $schoolId = TenantContext::id();

        $reportCard = ReportCard::where('school_id', $schoolId)->findOrFail($id);

        if (empty($reportCard->data_snapshot)) {
            abort(422, 'El data_snapshot del boletín está vacío o corrupto.');
        }

        $pdfBinary = $this->pdfRenderer->renderPdfFromSnapshot($reportCard->data_snapshot);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="report-card-%d-v%d.pdf"', $reportCard->id, $reportCard->version),
        ]);
    }

    /**
     * Render, store in private storage, and link pdf_media_file_id on ReportCard
     */
    public function generatePdf(int $id, Request $request): JsonResponse
    {
        $schoolId = TenantContext::id();

        $reportCard = ReportCard::where('school_id', $schoolId)->findOrFail($id);

        $mediaFile = $this->pdfStorageService->generateAndStorePdf($reportCard, $request->user()?->id);

        return response()->json([
            'message' => 'PDF del boletín generado y almacenado exitosamente.',
            'data' => $reportCard->fresh(['pdfMediaFile']),
        ], 200);
    }

    /**
     * Download or view stored PDF from private storage
     */
    public function downloadPdf(int $id): mixed
    {
        $schoolId = TenantContext::id();

        $reportCard = ReportCard::where('school_id', $schoolId)->findOrFail($id);

        if (!$reportCard->pdf_media_file_id) {
            return response()->json([
                'message' => 'El PDF del boletín no ha sido generado ni almacenado.',
            ], 404);
        }

        $mediaFile = MediaFile::where('school_id', $schoolId)->find($reportCard->pdf_media_file_id);

        if (!$mediaFile || !Storage::disk($mediaFile->disk)->exists($mediaFile->path)) {
            return response()->json([
                'message' => 'El archivo PDF del boletín no fue encontrado en el almacenamiento.',
            ], 404);
        }

        return response()->file(Storage::disk($mediaFile->disk)->path($mediaFile->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $mediaFile->original_name),
        ]);
    }

    /**
     * Dispatch a batch job for mass report card PDF generation
     */
    public function generatePdfBatch(Request $request, \App\Domain\ReportCard\Services\BulkReportCardPdfService $bulkPdfService): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'nullable|integer',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer',
            'all_courses' => 'nullable|boolean',
            'academic_period_id' => 'nullable|integer',
            'mode' => 'nullable|string|in:only_missing,regenerate',
        ]);

        $schoolId = TenantContext::id();
        $campusId = CampusContext::id();

        $result = $bulkPdfService->dispatchPdfBatch($schoolId, $validated, $campusId, $request->user()?->id);

        return response()->json([
            'message' => 'Proceso masivo de generación de PDFs de boletines enviado a cola exitosamente.',
            'data' => $result,
        ], 202);
    }

    /**
     * Get execution status of a report card PDF generation batch
     */
    public function pdfBatchStatus(string $batchId): JsonResponse
    {
        $schoolId = TenantContext::id();
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote de generación de PDFs no encontrado.',
            ], 404);
        }

        $options = $batch->options;
        if (!isset($options['school_id']) || (int) $options['school_id'] !== (int) $schoolId) {
            return response()->json([
                'message' => 'Lote de generación de PDFs no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'is_completed' => $batch->finished(),
                'cancelled_at' => $batch->cancelledAt?->toDateTimeString(),
                'created_at' => $batch->createdAt?->toDateTimeString(),
                'finished_at' => $batch->finishedAt?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Dispatch a batch job for mass report card PDF ZIP packaging
     */
    public function generatePdfZip(Request $request, \App\Domain\ReportCard\Services\BulkReportCardZipService $bulkZipService): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'nullable|integer',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer',
            'all_courses' => 'nullable|boolean',
            'academic_period_id' => 'nullable|integer',
            'strict' => 'nullable|boolean',
        ]);

        $schoolId = TenantContext::id();
        $campusId = CampusContext::id();

        $result = $bulkZipService->dispatchZipBatch($schoolId, $validated, $campusId, $request->user()?->id);

        return response()->json([
            'message' => 'Proceso masivo de empaquetado ZIP de boletines enviado a cola exitosamente.',
            'data' => $result,
        ], 202);
    }

    /**
     * Get execution status of a report card PDF ZIP packaging batch
     */
    public function pdfZipBatchStatus(string $batchId): JsonResponse
    {
        $schoolId = TenantContext::id();
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote de empaquetado ZIP no encontrado.',
            ], 404);
        }

        $options = $batch->options;
        if (!isset($options['school_id']) || (int) $options['school_id'] !== (int) $schoolId) {
            return response()->json([
                'message' => 'Lote de empaquetado ZIP no encontrado.',
            ], 404);
        }

        $zipMediaFileId = $options['zip_media_file_id'] ?? null;
        $downloadUrl = $zipMediaFileId && $batch->finished()
            ? sprintf('/api/v1/report-cards/pdf-zip-batches/%s/download', $batch->id)
            : null;

        return response()->json([
            'data' => [
                'batch_id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'is_completed' => $batch->finished(),
                'pdf_count' => $options['pdf_count'] ?? 0,
                'missing_count' => $options['missing_count'] ?? 0,
                'zip_media_file_id' => $zipMediaFileId,
                'download_url' => $downloadUrl,
                'created_at' => $batch->createdAt?->toDateTimeString(),
                'finished_at' => $batch->finishedAt?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Download stored ZIP file from private storage
     */
    public function downloadPdfZip(string $batchId): mixed
    {
        $schoolId = TenantContext::id();
        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return response()->json([
                'message' => 'Lote de empaquetado ZIP no encontrado.',
            ], 404);
        }

        $options = $batch->options;
        if (!isset($options['school_id']) || (int) $options['school_id'] !== (int) $schoolId) {
            return response()->json([
                'message' => 'Lote de empaquetado ZIP no encontrado.',
            ], 404);
        }

        if (!$batch->finished() || empty($options['zip_media_file_id'])) {
            return response()->json([
                'message' => 'El archivo ZIP aún no ha sido generado o el proceso falló.',
            ], 404);
        }

        $mediaFile = MediaFile::where('school_id', $schoolId)->find($options['zip_media_file_id']);

        if (!$mediaFile || !Storage::disk($mediaFile->disk)->exists($mediaFile->path)) {
            return response()->json([
                'message' => 'El archivo ZIP de boletines no fue encontrado en el almacenamiento.',
            ], 404);
        }

        $filename = sprintf('boletines-%d-%s.zip', $schoolId, substr($batchId, 0, 8));

        return response()->file(Storage::disk($mediaFile->disk)->path($mediaFile->path), [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
