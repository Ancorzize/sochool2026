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
use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\Campus;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use App\Jobs\ReportCard\GenerateCourseReportCardPdfsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardPdfBatchApiTest extends TestCase
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
    protected ReportCard $reportCardA1;
    protected ReportCard $reportCardA2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        $this->engine = app(ReportCardEngine::class);

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

        // Generate Report Cards for Student A1 & Student A2 in Course 11A
        $this->reportCardA1 = $this->engine->generateReportCard($this->schoolA->id, $this->studentA1->id, $this->yearA->id, $this->periodA->id, $this->course11A);
        $this->reportCardA2 = $this->engine->generateReportCard($this->schoolA->id, $this->studentA2->id, $this->yearA->id, $this->periodA->id, $this->course11A);

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

    public function test_user_with_reports_generate_can_dispatch_bulk_pdf_batch_by_course(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'course_id' => $this->course11A->id,
            'academic_period_id' => $this->periodA->id,
            'mode' => 'only_missing',
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_courses', 1)
            ->assertJsonPath('data.mode', 'only_missing');

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1 &&
                $batch->options['school_id'] === $this->schoolA->id;
        });
    }

    public function test_job_processing_generates_and_stores_pdfs_for_course(): void
    {
        $job = new GenerateCourseReportCardPdfsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->periodA->id,
            'only_missing',
            $this->userAdminA->id
        );

        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $card1 = $this->reportCardA1->fresh();
        $card2 = $this->reportCardA2->fresh();

        $this->assertNotNull($card1->pdf_media_file_id);
        $this->assertNotNull($card2->pdf_media_file_id);

        $media1 = MediaFile::find($card1->pdf_media_file_id);
        $media2 = MediaFile::find($card2->pdf_media_file_id);

        $this->assertTrue(Storage::disk('local')->exists($media1->path));
        $this->assertTrue(Storage::disk('local')->exists($media2->path));
    }

    public function test_bulk_pdf_generation_mode_only_missing_skips_existing_valid_pdfs(): void
    {
        // Pre-generate PDF for ReportCard A1
        $pdfService = app(\App\Domain\ReportCard\Services\ReportCardPdfStorageService::class);
        RlsManager::setTenantContext($this->schoolA->id);
        $mediaA1 = $pdfService->generateAndStorePdf($this->reportCardA1, $this->userAdminA->id);
        $this->assertNull($this->reportCardA2->fresh()->pdf_media_file_id);

        // Run batch job in only_missing mode
        $job = new GenerateCourseReportCardPdfsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->periodA->id,
            'only_missing',
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals($mediaA1->id, $this->reportCardA1->fresh()->pdf_media_file_id);
        $this->assertNotNull($this->reportCardA2->fresh()->pdf_media_file_id);
    }

    public function test_bulk_pdf_generation_mode_regenerate_updates_existing_pdfs(): void
    {
        // Pre-generate PDF for ReportCard A1
        $pdfService = app(\App\Domain\ReportCard\Services\ReportCardPdfStorageService::class);
        RlsManager::setTenantContext($this->schoolA->id);
        $mediaA1Old = $pdfService->generateAndStorePdf($this->reportCardA1, $this->userAdminA->id);

        // Run batch job in regenerate mode
        $job = new GenerateCourseReportCardPdfsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->periodA->id,
            'regenerate',
            $this->userAdminA->id
        );
        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $card1Fresh = $this->reportCardA1->fresh();
        $this->assertNotEquals($mediaA1Old->id, $card1Fresh->pdf_media_file_id);

        // Immutability Check: status, version, is_latest remain unchanged
        $this->assertEquals($this->reportCardA1->status, $card1Fresh->status);
        $this->assertEquals($this->reportCardA1->version, $card1Fresh->version);
        $this->assertEquals($this->reportCardA1->is_latest, $card1Fresh->is_latest);
        $this->assertEquals($this->reportCardA1->data_snapshot, $card1Fresh->data_snapshot);
    }

    public function test_user_with_reports_view_can_query_pdf_batch_status(): void
    {
        $dispatchRes = $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'course_id' => $this->course11A->id,
            'academic_period_id' => $this->periodA->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $dispatchRes->assertStatus(202);
        $batchId = $dispatchRes->json('data.batch_id');

        // View-only user queries status
        $statusRes = $this->getJson("/api/v1/report-cards/pdf-batches/{$batchId}", $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA));

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.batch_id', $batchId)
            ->assertJsonPath('data.total_jobs', 1);
    }

    public function test_cross_tenant_pdf_batch_status_access_returns_404(): void
    {
        $dispatchRes = $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $dispatchRes->assertStatus(202);
        $batchId = $dispatchRes->json('data.batch_id');

        // School B user queries School A batch -> 404 Not Found
        $statusResB = $this->getJson("/api/v1/report-cards/pdf-batches/{$batchId}", $this->authHeaders($this->userAdminB, $this->schoolB));
        $statusResB->assertStatus(404);
    }

    public function test_user_without_reports_generate_cannot_dispatch_pdf_batch(): void
    {
        $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA))
            ->assertStatus(403);
    }

    public function test_user_without_reports_view_cannot_query_pdf_batch_status(): void
    {
        $dispatchRes = $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $dispatchRes->assertStatus(202);
        $batchId = $dispatchRes->json('data.batch_id');

        $this->getJson("/api/v1/report-cards/pdf-batches/{$batchId}", $this->authHeaders($this->userNoPermA, $this->schoolA, $this->campusA))
            ->assertStatus(403);
    }

    public function test_bulk_pdf_batch_selection_all_courses_dispatches_jobs_for_all_courses(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-pdf-batch', [
            'all_courses' => true,
        ], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.total_courses', 2);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2;
        });
    }
}
