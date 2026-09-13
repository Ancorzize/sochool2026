<?php

namespace Tests\Feature\Phase4;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\Subject;
use App\Domain\Attendance\Models\Attendance;
use App\Domain\Attendance\Models\AttendanceStatus;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Models\TeacherPeriodObservation;
use App\Domain\ReportCard\Services\ReportCardEngine;
use App\Domain\Student\Models\Student;
use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\Campus;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCardPdfApiTest extends TestCase
{
    protected School $schoolA;
    protected School $schoolB;
    protected User $userA;
    protected User $userB;
    protected User $userNoPerm;
    protected Campus $campusA;
    protected Campus $campusB;
    protected AcademicYear $yearA;
    protected AcademicPeriod $periodA;
    protected EducationalLevel $levelA;
    protected Grade $grade11A;
    protected Course $course11A;
    protected Subject $subjectPhysics;
    protected Student $studentA1;
    protected Teacher $teacherA1;
    protected GradingScale $scaleA;
    protected GradingScaleItem $scaleItemExcellent;
    protected ReportCardTemplate $templateA;
    protected ReportCardEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        $this->engine = app(ReportCardEngine::class);

        // 1. Create Schools
        $this->schoolA = School::factory()->create(['name' => 'Colegio La Matia']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio San Juan']);

        // 2. Setup Permissions & Roles
        $permView = Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $permGen = Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // School A Admin User
        RlsManager::setTenantContext($this->schoolA->id);
        $roleA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'ADMIN_A', 'guard_name' => 'web']);
        $roleA->permissions()->sync([$permView->id, $permGen->id]);

        $this->userA = User::factory()->create();
        $linkA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userA->id, 'status' => 'ACTIVE']);
        $linkA->roles()->sync([$roleA->id => ['school_id' => $this->schoolA->id]]);

        // School A User without reports.view
        $roleNoPerm = Role::create(['school_id' => $this->schoolA->id, 'name' => 'NO_PERM', 'guard_name' => 'web']);
        $this->userNoPerm = User::factory()->create();
        $linkNoPerm = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userNoPerm->id, 'status' => 'ACTIVE']);
        $linkNoPerm->roles()->sync([$roleNoPerm->id => ['school_id' => $this->schoolA->id]]);

        // School B Admin User
        RlsManager::setTenantContext($this->schoolB->id);
        $roleB = Role::create(['school_id' => $this->schoolB->id, 'name' => 'ADMIN_B', 'guard_name' => 'web']);
        $roleB->permissions()->sync([$permView->id, $permGen->id]);

        $this->userB = User::factory()->create();
        $linkB = SchoolUser::create(['school_id' => $this->schoolB->id, 'user_id' => $this->userB->id, 'status' => 'ACTIVE']);
        $linkB->roles()->sync([$roleB->id => ['school_id' => $this->schoolB->id]]);

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
        $teacherUser = User::factory()->create();
        $this->teacherA1 = Teacher::create(['school_id' => $this->schoolA->id, 'user_id' => $teacherUser->id, 'teacher_code' => 'T1', 'document_type' => 'CC', 'document_number' => '12345', 'first_name' => 'Jaime', 'last_name' => 'Docente', 'email' => 'teacher@schoola.com']);

        $this->scaleA = GradingScale::create(['school_id' => $this->schoolA->id, 'name' => 'Escala Nacional', 'scale_type' => 'QUALITATIVE', 'is_active' => true]);
        $this->scaleItemExcellent = GradingScaleItem::create(['school_id' => $this->schoolA->id, 'grading_scale_id' => $this->scaleA->id, 'name' => 'Superior', 'label' => 'SUP', 'description' => 'Excelente desempeño', 'color' => '#10B981', 'equivalent_numeric_value' => 5.0, 'order' => 1]);

        $this->templateA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Plantilla General', 'is_active' => true, 'layout_config' => ['paper_size' => 'letter', 'orientation' => 'portrait']]);

        // Academic results
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

        TeacherPeriodObservation::create([
            'school_id' => $this->schoolA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'student_id' => $this->studentA1->id,
            'subject_id' => $this->subjectPhysics->id,
            'teacher_id' => $this->teacherA1->id,
            'observation' => 'Demuestra excelente comprensión de los temas.',
        ]);

        $statusPresent = AttendanceStatus::create(['school_id' => $this->schoolA->id, 'name' => 'Presente', 'code' => 'P', 'is_absence' => false]);
        Attendance::create([
            'school_id' => $this->schoolA->id,
            'course_id' => $this->course11A->id,
            'student_id' => $this->studentA1->id,
            'subject_id' => $this->subjectPhysics->id,
            'date' => '2026-02-10',
            'attendance_status_id' => $statusPresent->id,
            'recorded_by' => $this->userA->id,
        ]);

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'off', false);");
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

    public function test_can_render_pdf_for_valid_report_card(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $response = $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_rendering_is_strictly_snapshot_driven(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        DB::enableQueryLog();

        $response = $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(200);

        $executedQueries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify no queries were executed against source academic tables during PDF rendering
        foreach ($executedQueries as $query) {
            $sql = strtolower($query['query']);
            $this->assertStringNotContainsString('period_final_grades', $sql);
            $this->assertStringNotContainsString('student_grades', $sql);
            $this->assertStringNotContainsString('attendances', $sql);
            $this->assertStringNotContainsString('teacher_period_observations', $sql);
        }
    }

    public function test_published_and_locked_report_cards_can_be_rendered(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // Test LOCKED report card
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard->update(['status' => 'LOCKED']);
        $responseLocked = $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $responseLocked->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $responseLocked->getContent());
        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals('LOCKED', $reportCard->fresh()->status);

        // Test PUBLISHED report card
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard->update(['status' => 'PUBLISHED']);
        $responsePublished = $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $responsePublished->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $responsePublished->getContent());
        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals('PUBLISHED', $reportCard->fresh()->status);
    }

    public function test_pdf_generation_causes_no_database_mutations(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        $initialAttributes = $reportCard->fresh()->toArray();

        $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userA, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        RlsManager::setTenantContext($this->schoolA->id);
        $freshReportCard = $reportCard->fresh();
        $this->assertEquals($initialAttributes['status'], $freshReportCard->status);
        $this->assertEquals($initialAttributes['version'], $freshReportCard->version);
        $this->assertEquals($initialAttributes['is_latest'], $freshReportCard->is_latest);
        $this->assertEquals($initialAttributes['pdf_media_file_id'], $freshReportCard->pdf_media_file_id);
        $this->assertEquals($initialAttributes['data_snapshot'], $freshReportCard->data_snapshot);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_cross_tenant_pdf_access_returns_404(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCardA = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // User B from School B tries to access School A report card PDF
        $response = $this->get('/api/v1/report-cards/' . $reportCardA->id . '/pdf', $this->authHeaders($this->userB, $this->schoolB));

        $response->assertStatus(404);
    }

    public function test_rbac_permission_reports_view_required(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $reportCard = $this->engine->generateReportCard(
            $this->schoolA->id,
            $this->studentA1->id,
            $this->yearA->id,
            $this->periodA->id,
            $this->course11A
        );

        // User without reports.view permission
        $response = $this->get('/api/v1/report-cards/' . $reportCard->id . '/pdf', $this->authHeaders($this->userNoPerm, $this->schoolA, $this->campusA));

        $response->assertStatus(403);
    }
}
