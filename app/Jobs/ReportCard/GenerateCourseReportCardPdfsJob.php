<?php

namespace App\Jobs\ReportCard;

use App\Domain\Document\Models\MediaFile;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Services\ReportCardPdfStorageService;
use App\Infrastructure\Tenant\Contracts\TenantAwareJob;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateCourseReportCardPdfsJob extends TenantAwareJob
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public int $courseId;
    public ?int $academicPeriodId;
    public string $mode;
    public ?int $userId;

    public function __construct(
        int $schoolId,
        int $courseId,
        ?int $academicPeriodId = null,
        string $mode = 'only_missing',
        ?int $userId = null
    ) {
        parent::__construct($schoolId);

        $this->courseId = $courseId;
        $this->academicPeriodId = $academicPeriodId;
        $this->mode = $mode;
        $this->userId = $userId;
    }

    /**
     * Process PDF generation/storage for all latest report cards in the course
     */
    protected function executeJob(): void
    {
        // Cancel execution if job batch has been cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $query = ReportCard::query()
            ->where('school_id', $this->schoolId)
            ->where('course_id', $this->courseId)
            ->where('is_latest', true);

        if ($this->academicPeriodId) {
            $query->where('academic_period_id', $this->academicPeriodId);
        }

        $reportCards = $query->get();

        $pdfStorageService = app(ReportCardPdfStorageService::class);

        foreach ($reportCards as $reportCard) {
            // Re-check cancellation inside loop for fine-grained batch stop
            if ($this->batch() && $this->batch()->cancelled()) {
                return;
            }

            // Mode check: only_missing skips existing valid PDFs
            if ($this->mode === 'only_missing' && $reportCard->pdf_media_file_id) {
                $existingMedia = MediaFile::where('school_id', $this->schoolId)
                    ->find($reportCard->pdf_media_file_id);

                if ($existingMedia && Storage::disk($existingMedia->disk)->exists($existingMedia->path)) {
                    continue;
                }
            }

            try {
                $forceRegenerate = ($this->mode === 'regenerate');
                $pdfStorageService->generateAndStorePdf($reportCard, $this->userId, $forceRegenerate);
            } catch (\Throwable $e) {
                Log::error("Error generating PDF for ReportCard #{$reportCard->id} in course {$this->courseId}: " . $e->getMessage(), [
                    'school_id' => $this->schoolId,
                    'report_card_id' => $reportCard->id,
                    'course_id' => $this->courseId,
                    'exception' => $e,
                ]);
            }
        }
    }
}
