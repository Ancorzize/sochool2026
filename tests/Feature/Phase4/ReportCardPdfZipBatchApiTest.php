<?php

namespace Tests\Feature\Phase4;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\Subject;
use App\Domain\Document\Models\MediaFile;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\ReportCard\Services\ReportCardPdfStorageService;
use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\Campus;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use App\Jobs\ReportCard\GenerateReportCardZipJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardPdfZipBatchApiTest extends TestCase
{
    protected School $schoolA;
    protected School $schoolB;
    protected User $userAdminA;
    protected User $userViewOnlyA;
    protected User $userNoPermA;
    protected User $userAdminB;
    protected Campus $campusA;
    protected Campus $campusB;
    protected AcademicYear $yearA;
    protected AcademicPeriod $periodA;
    protected EducationalLevel $levelA;
    protected Grade $grade11A;
    protected Course $course11A;
    protected Course $course11B;
    protected Subject $subjectPhysics;
    protected Student $studentA1;
    protected Student $studentA2;
    protected GradingScale $scaleA;
    protected GradingScaleItem $scaleItem;
    protected ReportCardTemplate $templateA;
    protected ReportCardEngine $engine;
    protected ReportCardPdfStorageService $pdfStorageService;
    protected ReportCard $reportCardA1;
    protected ReportCard $reportCardA2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        $this->engine = app(ReportCardEngine::class);
        $this->pdfStorageService = app(ReportCardPdfStorageService::class);

        // 1. Create School A & School B
        $this->schoolA = School::factory()->create(['name' => 'Colegio La Matia']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio San Juan']);

        // 2. Setup Permissions & Roles
        $permView = Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $permGen = Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // Admin User School A (view + generate)
        RlsManager::setTenantContext($this->schoolA->id);
        $roleAdminA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'ADMIN_A', 'guard_name' => 'web']);
        $roleAdminA->permissions()->sync([$permView->id, $permGen->id]);

        $this->userAdminA = User::factory()->create();
        $linkAdminA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userAdminA->id, 'status' => 'ACTIVE']);
        $linkAdminA->roles()->sync([$roleAdminA->id => ['school_id' => $this->schoolA->id]]);

        // View-only User School A
        $roleViewA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'VIEW_ONLY_A', 'guard_name' => 'web']);
        $roleViewA->permissions()->sync([$permView->id]);

        $this->userViewOnlyA = User::factory()->create();
        $linkViewA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userViewOnlyA->id, 'status' => 'ACTIVE']);
        $linkViewA->roles()->sync([$roleViewA->id => ['school_id' => $this->schoolA->id]]);

        // User without permissions School A
        $roleNoPermA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'NO_PERM_A', 'guard_name' => 'web']);
        $this->userNoPermA = User::factory()->create();
        $linkNoPermA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userNoPermA->id, 'status' => 'ACTIVE']);
        $linkNoPermA->roles()->sync([$roleNoPermA->id => ['school_id' => $this->schoolA->id]]);

        // Admin User School B
        RlsManager::setTenantContext($this->schoolB->id);
        $roleAdminB = Role::create(['school_id' => $this->schoolB->id, 'name' => 'ADMIN_B', 'guard_name' => 'web']);
        $roleAdminB->permissions()->sync([$permView->id, $permGen->id]);

        $this->userAdminB = User::factory()->create();
        $linkAdminB = SchoolUser::create(['school_id' => $this->schoolB->id, 'user_id' => $this->userAdminB->id, 'status' => 'ACTIVE']);
        $linkAdminB->roles()->sync([$roleAdminB->id => ['school_id' => $this->schoolB->id]]);

        // 3. Setup Academic Data for School A
        RlsManager::setTenantContext($this->schoolA->id);
        $this->campusA = Campus::create(['school_id' => $this->schoolA->id, 'name' => 'Sede Principal', 'code' => 'SP', 'status' => 'ACTIVE', 'is_main' => true]);
        $this->yearA = AcademicYear::create(['school_id' => $this->schoolA->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]);
        $this->periodA = AcademicPeriod::create(['school_id' => $this->schoolA->id, 'academic_year_id' => $this->yearA->id, 'name' => 'Periodo 1', 'period_order' => 1, 'weight_percentage' => 25.0, 'start_date' => '2026-01-15', 'end_date' => '2026-04-10', 'status' => 'OPEN']);

        $this->levelA = EducationalLevel::create(['school_id' => $this->schoolA->id, 'name' => 'Secundaria', 'code' => 'SEC', 'level_order' => 2]);
        $this->grade11A = Grade::create(['school_id' => $this->schoolA->id, 'educational_level_id' => $this->levelA->id, 'name' => 'Grado 11°', 'code' => 'G11', 'grade_order' => 11]);

        $this->course11A = Course::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'academic_year_id' => $this->yearA->id, 'grade_id' => $this->grade11A->id, 'name' => '11A', 'shift' => 'MAÑANA']);
        $this->course11B = Course::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'academic_year_id' => $this->yearA->id, 'grade_id' => $this->grade11A->id, 'name' => '11B', 'shift' => 'MAÑANA']);

        $this->subjectPhysics = Subject::create(['school_id' => $this->schoolA->id, 'name' => 'Física', 'code' => 'FIS-11']);

        $this->studentA1 = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Juan', 'last_name' => 'Pérez']);
        $this->studentA2 = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Maria', 'last_name' => 'Gómez']);

        $this->scaleA = GradingScale::create(['school_id' => $this->schoolA->id, 'name' => 'Escala Numérica 1-5', 'scale_type' => 'NUMERIC', 'min_score' => 1.0, 'max_score' => 5.0, 'passing_score' => 3.0, 'is_default' => true]);
        $this->scaleItem = GradingScaleItem::create(['school_id' => $this->schoolA->id, 'grading_scale_id' => $this->scaleA->id, 'name' => 'Alto', 'label' => 'Desempeño Alto', 'description' => 'Buen trabajo', 'min_value' => 4.0, 'max_value' => 4.5, 'equivalent_numeric_value' => 4.2, 'color' => '#3B82F6', 'item_order' => 3]);
        $this->templateA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Boletín General', 'is_active' => true]);

        PeriodFinalGrade::create(['school_id' => $this->schoolA->id, 'course_id' => $this->course11A->id, 'subject_id' => $this->subjectPhysics->id, 'student_id' => $this->studentA1->id, 'academic_period_id' => $this->periodA->id, 'final_entered_value' => '4.2', 'numeric_score' => 4.20, 'grading_scale_item_id' => $this->scaleItem->id, 'status' => 'FINAL']);
        PeriodFinalGrade::create(['school_id' => $this->schoolA->id, 'course_id' => $this->course11A->id, 'subject_id' => $this->subjectPhysics->id, 'student_id' => $this->studentA2->id, 'academic_period_id' => $this->periodA->id, 'final_entered_value' => '4.5', 'numeric_score' => 4.50, 'grading_scale_item_id' => $this->scaleItem->id, 'status' => 'FINAL']);

        // Generate Report Cards and store PDFs
        $this->reportCardA1 = $this->engine->generateReportCard($this->schoolA->id, $this->studentA1->id, $this->yearA->id, $this->periodA->id, $this->course11A);
        $this->reportCardA2 = $this->engine->generateReportCard($this->schoolA->id, $this->studentA2->id, $this->yearA->id, $this->periodA->id, $this->course11A);

        $this->pdfStorageService->generateAndStorePdf($this->reportCardA1, $this->userAdminA->id);
        $this->pdfStorageService->generateAndStorePdf($this->reportCardA2, $this->userAdminA->id);

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        RlsManager::purgeTenantContext();
    }

    protected function authHeaders(User $user, School $school, ?Campus $campus = null): array
    {
        TenantContext::clear();
        RlsManager::purgeTenantContext();
        $this->app['auth']->forgetGuards();

        $token = $user->createToken('test_token', ["tenant:{$school->id}"])->plainTextToken;

        $headers = [
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID' => $school->id,
            'Accept' => 'application/json',
        ];
        if ($campus) {
            $headers['X-Campus-ID'] = $campus->id;
        }
        return $headers;
    }

    public function test_user_with_reports_generate_can_dispatch_zip_batch_and_receives_202(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-pdf-zip', [
            'course_id' => $this->course11A->id,
            'academic_period_id' => $this->periodA->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_report_cards', 2)
            ->assertJsonPath('data.missing_count', 0);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1 &&
                $batch->options['school_id'] === $this->schoolA->id;
        });
    }

    public function test_zip_job_generates_valid_zip_archive_and_is_readable(): void
    {
        $job = new GenerateReportCardZipJob(
            $this->schoolA->id,
            [$this->reportCardA1->id, $this->reportCardA2->id],
            'single_course',
            false,
            $this->userAdminA->id
        );

        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaFile = MediaFile::where('school_id', $this->schoolA->id)
            ->where('file_category', 'REPORT_CARD_ZIP')
            ->first();

        $this->assertNotNull($mediaFile);
        $this->assertEquals('local', $mediaFile->disk);
        $this->assertFalse($mediaFile->is_public);
        $this->assertTrue(Storage::disk('local')->exists($mediaFile->path));

        // Verify ZIP contents using ZipArchive
        $zipPath = Storage::disk('local')->path($mediaFile->path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertEquals(2, $zip->numFiles);
        $zip->close();
    }

    public function test_report_cards_remain_strictly_immutable(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $initialA1 = $this->reportCardA1->fresh()->toArray();

        $job = new GenerateReportCardZipJob(
            $this->schoolA->id,
            [$this->reportCardA1->id],
            'single_course',
            false,
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $freshA1 = $this->reportCardA1->fresh();

        $this->assertEquals($initialA1['status'], $freshA1->status);
        $this->assertEquals($initialA1['version'], $freshA1->version);
        $this->assertEquals($initialA1['is_latest'], $freshA1->is_latest);
        $this->assertEquals($initialA1['data_snapshot'], $freshA1->data_snapshot);
        $this->assertEquals($initialA1['pdf_media_file_id'], $freshA1->pdf_media_file_id);
    }

    public function test_user_with_reports_view_can_query_zip_batch_status_and_download(): void
    {
        $dispatchRes = $this->postJson('/api/v1/report-cards/generate-pdf-zip', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $dispatchRes->assertStatus(202);
        $batchId = $dispatchRes->json('data.batch_id');

        // Query status with view-only user
        $statusRes = $this->getJson("/api/v1/report-cards/pdf-zip-batches/{$batchId}", $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA));

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.batch_id', $batchId)
            ->assertJsonPath('data.pdf_count', 2)
            ->assertJsonPath('data.missing_count', 0);

        // Download ZIP
        $downloadRes = $this->get("/api/v1/report-cards/pdf-zip-batches/{$batchId}/download", $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA));

        $downloadRes->assertStatus(200);
        $this->assertStringStartsWith('application/zip', $downloadRes->headers->get('Content-Type'));
        $fileContent = file_get_contents($downloadRes->getFile()->getPathname());
        $this->assertStringStartsWith("PK\x03\x04", $fileContent);
    }

    public function test_cross_tenant_zip_batch_status_and_download_rejected_with_404(): void
    {
        $dispatchRes = $this->postJson('/api/v1/report-cards/generate-pdf-zip', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $batchId = $dispatchRes->json('data.batch_id');

        // School B user attempts status
        $this->getJson("/api/v1/report-cards/pdf-zip-batches/{$batchId}", $this->authHeaders($this->userAdminB, $this->schoolB))
            ->assertStatus(404);

        // School B user attempts download
        $this->get("/api/v1/report-cards/pdf-zip-batches/{$batchId}/download", $this->authHeaders($this->userAdminB, $this->schoolB))
            ->assertStatus(404);
    }

    public function test_strict_mode_rejects_dispatch_if_pdfs_are_missing_with_422(): void
    {
        // Create student without PDF
        RlsManager::setTenantContext($this->schoolA->id);
        $studentA3 = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Carlos', 'last_name' => 'Sanchez']);
        $cardNoPdf = $this->engine->generateReportCard($this->schoolA->id, $studentA3->id, $this->yearA->id, $this->periodA->id, $this->course11A);
        $this->assertNull($cardNoPdf->pdf_media_file_id);

        $response = $this->postJson('/api/v1/report-cards/generate-pdf-zip', [
            'course_id' => $this->course11A->id,
            'strict' => true,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['strict']);
    }

    public function test_non_strict_mode_generates_zip_and_includes_missing_manifest(): void
    {
        // Create student without PDF
        RlsManager::setTenantContext($this->schoolA->id);
        $studentA3 = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Carlos', 'last_name' => 'Sanchez']);
        $cardNoPdf = $this->engine->generateReportCard($this->schoolA->id, $studentA3->id, $this->yearA->id, $this->periodA->id, $this->course11A);

        $job = new GenerateReportCardZipJob(
            $this->schoolA->id,
            [$this->reportCardA1->id, $this->reportCardA2->id, $cardNoPdf->id],
            'single_course',
            false,
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaFile = MediaFile::where('school_id', $this->schoolA->id)
            ->where('file_category', 'REPORT_CARD_ZIP')
            ->first();

        $zipPath = Storage::disk('local')->path($mediaFile->path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        // 2 valid PDFs + 1 boletines_faltantes.txt
        $this->assertEquals(3, $zip->numFiles);
        $manifestContent = $zip->getFromName('boletines_faltantes.txt');
        $this->assertNotEmpty($manifestContent);
        $this->assertStringContainsString('MANIFIESTO DE BOLETINES FALTANTES', $manifestContent);
        $this->assertStringContainsString('Carlos', $manifestContent);
        $zip->close();
    }

    public function test_missing_physical_file_or_media_file_counted_as_missing(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        // Delete physical file for reportCardA2
        $mediaFile2 = MediaFile::find($this->reportCardA2->pdf_media_file_id);
        Storage::disk('local')->delete($mediaFile2->path);

        $job = new GenerateReportCardZipJob(
            $this->schoolA->id,
            [$this->reportCardA1->id, $this->reportCardA2->id],
            'single_course',
            false,
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaZip = MediaFile::where('school_id', $this->schoolA->id)
            ->where('file_category', 'REPORT_CARD_ZIP')
            ->first();

        $zipPath = Storage::disk('local')->path($mediaZip->path);
        $zip = new \ZipArchive();
        $zip->open($zipPath);

        // 1 valid PDF + 1 boletines_faltantes.txt
        $this->assertEquals(2, $zip->numFiles);
        $manifest = $zip->getFromName('boletines_faltantes.txt');
        $this->assertStringContainsString('Gómez', $manifest);
        $zip->close();
    }

    public function test_only_is_latest_true_included_in_selection(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        // Create SUPERSEDED v1 card for student A1
        $this->reportCardA1->update(['is_latest' => false, 'status' => 'SUPERSEDED']);

        // Create new v2 card for student A1
        $cardV2 = $this->engine->generateReportCard($this->schoolA->id, $this->studentA1->id, $this->yearA->id, $this->periodA->id, $this->course11A, 'Regeneración');
        $this->pdfStorageService->generateAndStorePdf($cardV2, $this->userAdminA->id);

        $res = $this->postJson('/api/v1/report-cards/generate-pdf-zip', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $res->assertStatus(202)
            ->assertJsonPath('data.total_report_cards', 2); // v2 for A1 + v1 for A2
    }

    public function test_internal_zip_paths_are_sanitized(): void
    {
        $job = new GenerateReportCardZipJob(
            $this->schoolA->id,
            [$this->reportCardA1->id],
            'single_course',
            false,
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaZip = MediaFile::where('school_id', $this->schoolA->id)->where('file_category', 'REPORT_CARD_ZIP')->first();
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('local')->path($mediaZip->path));

        $stat = $zip->statIndex(0);
        $this->assertStringNotContainsString('../', $stat['name']);
        $this->assertStringStartsWith('11a/boletin-perez-juan-', $stat['name']);
        $zip->close();
    }
}
