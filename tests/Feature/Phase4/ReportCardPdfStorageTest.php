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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportCardPdfStorageTest extends TestCase
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
    protected Subject $subjectPhysics;
    protected Student $studentA1;
    protected GradingScale $scaleA;
    protected GradingScaleItem $scaleItemExcellent;
    protected ReportCardTemplate $templateA;
    protected ReportCardEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        $this->engine = app(ReportCardEngine::class);

        // 1. Create Schools
        $this->schoolA = School::factory()->create(['name' => 'Colegio La Matia']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio San Juan']);

        // 2. Setup Permissions & Roles
        $permView = Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $permGen = Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // School A Admin User (both view & generate)
        RlsManager::setTenantContext($this->schoolA->id);
        $roleAdminA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'ADMIN_A', 'guard_name' => 'web']);
        $roleAdminA->permissions()->sync([$permView->id, $permGen->id]);

        $this->userAdminA = User::factory()->create();
        $linkAdminA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userAdminA->id, 'status' => 'ACTIVE']);
        $linkAdminA->roles()->sync([$roleAdminA->id => ['school_id' => $this->schoolA->id]]);

        // School A User with view only
        $roleViewA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'VIEW_ONLY_A', 'guard_name' => 'web']);
        $roleViewA->permissions()->sync([$permView->id]);

        $this->userViewOnlyA = User::factory()->create();
        $linkViewA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userViewOnlyA->id, 'status' => 'ACTIVE']);
        $linkViewA->roles()->sync([$roleViewA->id => ['school_id' => $this->schoolA->id]]);

        // School A User without permissions
        $roleNoPerm = Role::create(['school_id' => $this->schoolA->id, 'name' => 'NO_PERM', 'guard_name' => 'web']);
        $this->userNoPermA = User::factory()->create();
        $linkNoPerm = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userNoPermA->id, 'status' => 'ACTIVE']);
        $linkNoPerm->roles()->sync([$roleNoPerm->id => ['school_id' => $this->schoolA->id]]);

        // School B Admin User
        RlsManager::setTenantContext($this->schoolB->id);
        $roleAdminB = Role::create(['school_id' => $this->schoolB->id, 'name' => 'ADMIN_B', 'guard_name' => 'web']);
        $roleAdminB->permissions()->sync([$permView->id, $permGen->id]);

        $this->userAdminB = User::factory()->create();
        $linkAdminB = SchoolUser::create(['school_id' => $this->schoolB->id, 'user_id' => $this->userAdminB->id, 'status' => 'ACTIVE']);
        $linkAdminB->roles()->sync([$roleAdminB->id => ['school_id' => $this->schoolB->id]]);

        // 3. Create Academic Structure & Data for School A
        RlsManager::setTenantContext($this->schoolA->id);
        $this->campusA = Campus::create(['school_id' => $this->schoolA->id, 'name' => 'Sede Principal', 'code' => 'SP', 'is_main' => true]);
        $this->yearA = AcademicYear::create(['school_id' => $this->schoolA->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]);
        $this->periodA = AcademicPeriod::create(['school_id' => $this->schoolA->id, 'academic_year_id' => $this->yearA->id, 'name' => 'Periodo 1', 'period_order' => 1, 'weight_percentage' => 25.0, 'start_date' => '2026-01-15', 'end_date' => '2026-04-10', 'status' => 'OPEN']);

        $this->levelA = EducationalLevel::create(['school_id' => $this->schoolA->id, 'name' => 'Secundaria', 'code' => 'SEC', 'level_order' => 2]);
        $this->grade11A = Grade::create(['school_id' => $this->schoolA->id, 'educational_level_id' => $this->levelA->id, 'name' => 'Grado 11°', 'code' => 'G11', 'grade_order' => 11]);

        $this->course11A = Course::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'academic_year_id' => $this->yearA->id, 'grade_id' => $this->grade11A->id, 'name' => '11A', 'shift' => 'MAÑANA']);
        $this->subjectPhysics = Subject::create(['school_id' => $this->schoolA->id, 'name' => 'Física', 'code' => 'FIS-11']);

        $this->studentA1 = Student::factory()->create(['school_id' => $this->schoolA->id, 'first_name' => 'Juan', 'last_name' => 'Pérez']);

        $this->scaleA = GradingScale::create(['school_id' => $this->schoolA->id, 'name' => 'Escala Nacional', 'scale_type' => 'QUALITATIVE', 'is_active' => true]);
        $this->scaleItemExcellent = GradingScaleItem::create(['school_id' => $this->schoolA->id, 'grading_scale_id' => $this->scaleA->id, 'name' => 'Superior', 'label' => 'SUP', 'description' => 'Excelente desempeño', 'color' => '#10B981', 'equivalent_numeric_value' => 5.0, 'order' => 1]);

        $this->templateA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla General', 'is_active' => true, 'layout_config' => ['paper_size' => 'letter', 'orientation' => 'portrait']]);

        PeriodFinalGrade::create([
            'school_id' => $this->schoolA->id,
            'course_id' => $this->course11A->id,
            'student_id' => $this->studentA1->id,
            'subject_id' => $this->subjectPhysics->id,
            'academic_period_id' => $this->periodA->id,
            'grading_scale_item_id' => $this->scaleItemExcellent->id,
            'final_entered_value' => '5.0',
            'numeric_score' => 5.0,
            'status' => 'FINAL',
        ]);

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
    }

    protected function resetAuthContext(): void
    {
        $this->app['auth']->forgetGuards();
        TenantContext::clear();
        RlsManager::purgeTenantContext('pgsql');
    }

    protected function authHeaders(User $user, School $school, ?Campus $campus = null): array
    {
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

    public function test_user_with_reports_generate_can_generate_and_store_pdf(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $this->resetAuthContext();
        $response = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(200);
        $mediaFileId = $response->json('data.pdf_media_file_id');
        $this->assertNotNull($mediaFileId);

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaFile = MediaFile::find($mediaFileId);
        $this->assertNotNull($mediaFile);
        $this->assertEquals('REPORT_CARD_PDF', $mediaFile->file_category->value);
        $this->assertEquals('local', $mediaFile->disk);
        $this->assertFalse($mediaFile->is_public);
        $this->assertTrue(Storage::disk('local')->exists($mediaFile->path));
    }

    public function test_user_with_reports_view_can_download_stored_pdf(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Store PDF
        $this->resetAuthContext();
        $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        // Download PDF with view-only user
        $this->resetAuthContext();
        $response = $this->get("/api/v1/report-cards/{$reportCard->id}/pdf/download", $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA));

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $fileContent = file_get_contents($response->getFile()->getPathname());
        $this->assertStringStartsWith('%PDF-', $fileContent);
    }

    public function test_reports_view_user_without_reports_generate_cannot_trigger_storage(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $this->resetAuthContext();
        $response = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userViewOnlyA, $this->schoolA, $this->campusA));

        $response->assertStatus(403);
    }

    public function test_user_without_reports_view_cannot_download_pdf(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $this->resetAuthContext();
        $response = $this->get("/api/v1/report-cards/{$reportCard->id}/pdf/download", $this->authHeaders($this->userNoPermA, $this->schoolA, $this->campusA));

        $response->assertStatus(403);
    }

    public function test_cross_tenant_access_to_report_card_or_download_returns_404(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Store PDF by School A
        $this->resetAuthContext();
        $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        // User Admin B from School B tries to generate PDF for School A report card
        $this->resetAuthContext();
        $responseStore = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminB, $this->schoolB));
        $responseStore->assertStatus(404);

        // User Admin B from School B tries to download PDF for School A report card
        $this->resetAuthContext();
        $responseDownload = $this->get("/api/v1/report-cards/{$reportCard->id}/pdf/download", $this->authHeaders($this->userAdminB, $this->schoolB));
        $responseDownload->assertStatus(404);
    }

    public function test_cross_tenant_media_file_tampering_rejected(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCardA = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Create MediaFile belonging to School B
        RlsManager::setTenantContext($this->schoolB->id);
        $mediaFileB = MediaFile::create([
            'school_id' => $this->schoolB->id,
            'entity_type' => ReportCard::class,
            'entity_id' => 999,
            'file_category' => 'REPORT_CARD_PDF',
            'file_name' => 'fake.pdf',
            'original_name' => 'fake.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'disk' => 'local',
            'path' => 'tenants/' . $this->schoolB->id . '/REPORT_CARD_PDF/fake.pdf',
            'is_public' => false,
        ]);

        // Tamper School A Report Card to point to School B MediaFile
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCardA->update(['pdf_media_file_id' => $mediaFileB->id]);

        // Attempt download under School A
        $this->resetAuthContext();
        $response = $this->get("/api/v1/report-cards/{$reportCardA->id}/pdf/download", $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $response->assertStatus(404);
    }

    public function test_published_and_locked_report_cards_can_store_pdf(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Test LOCKED
        $reportCard->update(['status' => 'LOCKED']);
        $this->resetAuthContext();
        $resLocked = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $resLocked->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals('LOCKED', $reportCard->fresh()->status);
        $this->assertNotNull($reportCard->fresh()->pdf_media_file_id);

        // Test PUBLISHED
        $reportCard->update(['status' => 'PUBLISHED']);
        $this->resetAuthContext();
        $resPublished = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $resPublished->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals('PUBLISHED', $reportCard->fresh()->status);
    }

    public function test_strict_immutability_guarantee(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $initial = $reportCard->fresh()->toArray();

        // Perform PDF storage
        $this->resetAuthContext();
        $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $fresh = $reportCard->fresh();

        $this->assertEquals($initial['status'], $fresh->status);
        $this->assertEquals($initial['version'], $fresh->version);
        $this->assertEquals($initial['is_latest'], $fresh->is_latest);
        $this->assertEquals($initial['parent_report_card_id'], $fresh->parent_report_card_id);
        $this->assertEquals($initial['data_snapshot'], $fresh->data_snapshot);
        $this->assertNotNull($fresh->pdf_media_file_id);
    }

    public function test_reusing_existing_pdf_avoids_duplicate_media_files(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Store PDF first time
        $this->resetAuthContext();
        $res1 = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $res1->assertStatus(200);
        $mediaId1 = $res1->json('data.pdf_media_file_id');

        // Store PDF second time (should reuse existing MediaFile)
        $this->resetAuthContext();
        $res2 = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $res2->assertStatus(200);
        $mediaId2 = $res2->json('data.pdf_media_file_id');

        $this->assertEquals($mediaId1, $mediaId2);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseCount('media_files', 1);
    }

    public function test_reconstructs_physical_file_if_missing_from_disk(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Store PDF
        $this->resetAuthContext();
        $res1 = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $res1->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $mediaFile = MediaFile::find($res1->json('data.pdf_media_file_id'));
        $this->assertTrue(Storage::disk('local')->exists($mediaFile->path));

        // Delete physical file from disk
        Storage::disk('local')->delete($mediaFile->path);
        $this->assertFalse(Storage::disk('local')->exists($mediaFile->path));

        // Call generate/store again -> should reconstruct file from data_snapshot
        $this->resetAuthContext();
        $res2 = $this->post("/api/v1/report-cards/{$reportCard->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));
        $res2->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $newMediaFile = MediaFile::find($res2->json('data.pdf_media_file_id'));
        $this->assertTrue(Storage::disk('local')->exists($newMediaFile->path));
    }

    public function test_independent_pdfs_for_version_1_and_version_2(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        // Version 1
        $reportCardV1 = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $this->resetAuthContext();
        $this->post("/api/v1/report-cards/{$reportCardV1->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA))->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $v1MediaId = $reportCardV1->fresh()->pdf_media_file_id;

        // Version 2 (Regeneration)
        $reportCardV2 = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A,
            'Actualización de notas'
        );

        $this->resetAuthContext();
        $this->post("/api/v1/report-cards/{$reportCardV2->id}/pdf", [], $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA))->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $v2MediaId = $reportCardV2->fresh()->pdf_media_file_id;

        $this->assertNotEquals($v1MediaId, $v2MediaId);

        $mediaV1 = MediaFile::find($v1MediaId);
        $mediaV2 = MediaFile::find($v2MediaId);

        $this->assertNotEquals($mediaV1->path, $mediaV2->path);
        $this->assertTrue(Storage::disk('local')->exists($mediaV1->path));
        $this->assertTrue(Storage::disk('local')->exists($mediaV2->path));
    }

    public function test_c3_b1_in_memory_renderer_endpoint_remains_functional(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $this->resetAuthContext();
        $response = $this->get("/api/v1/report-cards/{$reportCard->id}/pdf", $this->authHeaders($this->userAdminA, $this->schoolA, $this->campusA));

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
