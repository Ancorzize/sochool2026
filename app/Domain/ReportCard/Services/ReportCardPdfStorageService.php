<?php

namespace App\Domain\ReportCard\Services;

use App\Domain\Document\Enums\FileCategoryEnum;
use App\Domain\Document\Models\MediaFile;
use App\Domain\Document\Services\MediaStorageService;
use App\Domain\ReportCard\Models\ReportCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportCardPdfStorageService
{
    public function __construct(
        protected ReportCardPdfRenderer $pdfRenderer,
        protected MediaStorageService $mediaStorageService
    ) {}

    /**
     * Render, store in private storage, and link pdf_media_file_id on ReportCard.
     * Idempotent: If valid PDF already exists for this exact report_card.id + version on disk, reuses it.
     * Reconstructs file from data_snapshot if physical file is missing.
     */
    public function generateAndStorePdf(ReportCard $reportCard, ?int $userId = null, bool $forceRegenerate = false): MediaFile
    {
        if (empty($reportCard->data_snapshot)) {
            throw ValidationException::withMessages([
                'data_snapshot' => ['El data_snapshot del boletín está vacío o corrupto.'],
            ]);
        }

        // 1. Check existing media file unless forceRegenerate is true
        if (!$forceRegenerate && $reportCard->pdf_media_file_id) {
            $existingMedia = MediaFile::where('school_id', $reportCard->school_id)
                ->find($reportCard->pdf_media_file_id);

            if ($existingMedia && Storage::disk($existingMedia->disk)->exists($existingMedia->path)) {
                return $existingMedia;
            }
        }

        // 2. Render binary PDF strictly from frozen snapshot
        $pdfBinary = $this->pdfRenderer->renderPdfFromSnapshot($reportCard->data_snapshot);
        $originalName = sprintf('report-card-%d-v%d.pdf', $reportCard->id, $reportCard->version);

        // 3. Store via MediaStorageService in private local storage and update ReportCard
        return DB::transaction(function () use ($reportCard, $pdfBinary, $originalName, $userId) {
            $mediaFile = $this->mediaStorageService->storeRawMedia(
                schoolId: $reportCard->school_id,
                entity: $reportCard,
                content: $pdfBinary,
                originalName: $originalName,
                mimeType: 'application/pdf',
                category: FileCategoryEnum::REPORT_CARD_PDF,
                userId: $userId,
                disk: 'local',
                isPublic: false
            );

            // Update ONLY pdf_media_file_id without touching status, version, is_latest, snapshot
            $reportCard->update([
                'pdf_media_file_id' => $mediaFile->id,
            ]);

            return $mediaFile;
        });
    }
}
