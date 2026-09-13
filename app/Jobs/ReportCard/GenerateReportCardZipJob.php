<?php

namespace App\Jobs\ReportCard;

use App\Domain\Document\Enums\FileCategoryEnum;
use App\Domain\Document\Services\MediaStorageService;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\Contracts\TenantAwareJob;
use Illuminate\Bus\Batchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReportCardZipJob extends TenantAwareJob
{
    use Batchable, InteractsWithQueue, SerializesModels;

    public array $reportCardIds;
    public string $selectionMode;
    public bool $strict;
    public ?int $userId;

    public function __construct(
        int $schoolId,
        array $reportCardIds,
        string $selectionMode = 'single_course',
        bool $strict = false,
        ?int $userId = null
    ) {
        parent::__construct($schoolId);

        $this->reportCardIds = $reportCardIds;
        $this->selectionMode = $selectionMode;
        $this->strict = $strict;
        $this->userId = $userId;
    }

    /**
     * Package existing report card PDFs into a single private ZIP archive
     */
    protected function executeJob(): void
    {
        // Cancel execution if job batch has been cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $tempZipPath = null;

        try {
            $reportCards = ReportCard::query()
                ->where('school_id', $this->schoolId)
                ->whereIn('id', $this->reportCardIds)
                ->where('is_latest', true)
                ->with([
                    'pdfMediaFile',
                    'student',
                    'course.grade.educationalLevel',
                ])
                ->get();

            $tempDir = storage_path('app/private/temp');
            if (!file_exists($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }

            $uuid = (string) Str::uuid();
            $tempZipPath = "{$tempDir}/zip_{$uuid}.zip";

            $zip = new \ZipArchive();
            if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("No se pudo crear el archivo ZIP temporal en {$tempZipPath}.");
            }

            $pdfCount = 0;
            $missingCount = 0;
            $missingDetails = [];

            foreach ($reportCards as $reportCard) {
                $mediaFile = $reportCard->pdfMediaFile;
                $hasValidFile = $mediaFile
                    && $mediaFile->school_id === $this->schoolId
                    && Storage::disk($mediaFile->disk)->exists($mediaFile->path);

                if (!$hasValidFile) {
                    $missingCount++;
                    $studentName = $reportCard->student
                        ? trim("{$reportCard->student->last_name} {$reportCard->student->first_name}")
                        : 'Estudiante Desconocido';
                    $courseName = $reportCard->course?->name ?? 'Sin Curso';
                    $missingDetails[] = "Estudiante: {$studentName} | Curso: {$courseName} | ID Boletín: {$reportCard->id} - Razón: PDF no generado o archivo físico faltante.";
                    continue;
                }

                $pdfCount++;
                $courseName = Str::slug($reportCard->course?->name ?? 'curso', '-');
                $studentLastName = Str::slug($reportCard->student?->last_name ?? 'estudiante', '-');
                $studentFirstName = Str::slug($reportCard->student?->first_name ?? '', '-');
                $studentFullName = trim("{$studentLastName}-{$studentFirstName}", '-');

                if ($this->selectionMode === 'single_course') {
                    $localZipPath = sprintf('%s/boletin-%s-%d-v%d.pdf', $courseName, $studentFullName, $reportCard->id, $reportCard->version);
                } else {
                    $levelName = Str::slug($reportCard->course?->grade?->educationalLevel?->name ?? 'nivel', '-');
                    $gradeName = Str::slug($reportCard->course?->grade?->name ?? 'grado', '-');
                    $localZipPath = sprintf('%s/%s/%s/boletin-%s-%d-v%d.pdf', $levelName, $gradeName, $courseName, $studentFullName, $reportCard->id, $reportCard->version);
                }

                $physicalPath = Storage::disk($mediaFile->disk)->path($mediaFile->path);
                $zip->addFile($physicalPath, $localZipPath);
            }

            if ($missingCount > 0 && !$this->strict) {
                $manifestContent = "MANIFIESTO DE BOLETINES FALTANTES\n";
                $manifestContent .= "=================================\n";
                $manifestContent .= "Total de boletines seleccionados: " . count($reportCards) . "\n";
                $manifestContent .= "Boletines incluidos en ZIP: {$pdfCount}\n";
                $manifestContent .= "Boletines faltantes: {$missingCount}\n\n";
                $manifestContent .= implode("\n", $missingDetails) . "\n";
                $zip->addFromString('boletines_faltantes.txt', $manifestContent);
            }

            $zip->close();

            $school = School::find($this->schoolId);
            $mediaStorageService = app(MediaStorageService::class);
            $zipContent = file_get_contents($tempZipPath);
            $originalName = sprintf('boletines-escuela-%d-%s.zip', $this->schoolId, date('Ymd-His'));

            $zipMediaFile = $mediaStorageService->storeRawMedia(
                schoolId: $this->schoolId,
                entity: $school,
                content: $zipContent,
                originalName: $originalName,
                mimeType: 'application/zip',
                category: FileCategoryEnum::REPORT_CARD_ZIP,
                userId: $this->userId,
                disk: 'local',
                isPublic: false
            );

            if ($this->batch()) {
                $options = $this->batch()->options;
                $options['zip_media_file_id'] = $zipMediaFile->id;
                $options['pdf_count'] = $pdfCount;
                $options['missing_count'] = $missingCount;

                DB::table('job_batches')
                    ->where('id', $this->batch()->id)
                    ->update(['options' => serialize($options)]);
            }
        } catch (\Throwable $e) {
            Log::error("Error generating ZIP for school {$this->schoolId}: " . $e->getMessage(), [
                'school_id' => $this->schoolId,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            if ($tempZipPath && file_exists($tempZipPath)) {
                @unlink($tempZipPath);
            }
        }
    }
}
