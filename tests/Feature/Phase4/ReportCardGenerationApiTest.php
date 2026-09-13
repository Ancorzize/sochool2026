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
use App\Domain\ReportCard\Models\ReportCardAssignment;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\ReportCard\Models\TeacherPeriodObservation;
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

class ReportCardGenerationApiTest extends TestCase
{
    protected School $schoolA;
    protected School $schoolB;
    protected User $userA;
    protected User $userB;
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

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SET app.bypass_rls = 'on';");
        DB::connection('pgsql')->statement("SET app.bypass_rls = 'on';");

        // 1. Create School A & School B
        $this->schoolA = School::factory()->create(['name' => 'Colegio La Matia']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio San Juan']);

        // 2. Setup Permissions
        $permView = Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $permGen = Permission::firstOrCreate(['name' => 'reports.generate', 'guard_name' => 'web']);

        // Setup School A User & Roles
        RlsManager::setTenantContext($this->schoolA->id);
        $roleA = Role::create(['school_id' => $this->schoolA->id, 'name' => 'ADMIN_A', 'guard_name' => 'web']);
        $roleA->permissions()->sync([$permView->id, $permGen->id]);

        $this->userA = User::factory()->create();
        $linkA = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $this->userA->id, 'status' => 'ACTIVE']);
        $linkA->roles()->sync([$roleA->id => ['school_id' => $this->schoolA->id]]);

        // Setup School B User & Roles
        RlsManager::setTenantContext($this->schoolB->id);
        $roleB = Role::create(['school_id' => $this->schoolB->id, 'name' => 'ADMIN_B', 'guard_name' => 'web']);
        $roleB->permissions()->sync([$permView->id, $permGen->id]);

        $this->userB = User::factory()->create();
        $linkB = SchoolUser::create(['school_id' => $this->schoolB->id, 'user_id' => $this->userB->id, 'status' => 'ACTIVE']);
        $linkB->roles()->sync([$roleB->id => ['school_id' => $this->schoolB->id]]);

        // 4. Create Academic Entities for School A
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

        // Scale & Scale Item
        $this->scaleA = GradingScale::create(['school_id' => $this->schoolA->id, 'name' => 'Escala Numérica 1-5', 'scale_type' => 'NUMERIC', 'min_score' => 1.0, 'max_score' => 5.0, 'passing_score' => 3.0, 'is_default' => true]);
        $this->scaleItemExcellent = GradingScaleItem::create(['school_id' => $this->schoolA->id, 'grading_scale_id' => $this->scaleA->id, 'name' => 'Excelente', 'label' => 'Desempeño Superior', 'description' => 'Supera con excelencia los logros', 'min_value' => 4.6, 'max_value' => 5.0, 'equivalent_numeric_value' => 4.8, 'color' => '#10B981', 'item_order' => 4]);

        // Default Template
        $this->templateA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Boletín General', 'is_active' => true]);

        // Create initial source records for Student A1
        PeriodFinalGrade::create([
            'school_id' => $this->schoolA->id,
            'course_id' => $this->course11A->id,
            'subject_id' => $this->subjectPhysics->id,
            'student_id' => $this->studentA1->id,
            'academic_period_id' => $this->periodA->id,
            'final_entered_value' => '4.8',
            'numeric_score' => 4.80,
            'grading_scale_item_id' => $this->scaleItemExcellent->id,
            'status' => 'FINAL',
        ]);

        $attPresent = AttendanceStatus::create(['school_id' => $this->schoolA->id, 'code' => 'PRESENT', 'name' => 'Presente', 'status_order' => 1]);
        Attendance::create(['school_id' => $this->schoolA->id, 'course_id' => $this->course11A->id, 'student_id' => $this->studentA1->id, 'date' => '2026-02-01', 'attendance_status_id' => $attPresent->id]);

        TeacherPeriodObservation::create([
            'school_id' => $this->schoolA->id,
            'student_id' => $this->studentA1->id,
            'course_id' => $this->course11A->id,
            'subject_id' => $this->subjectPhysics->id,
            'academic_period_id' => $this->periodA->id,
            'teacher_id' => $this->teacherA1->id,
            'observation' => 'Excelente participación en experimentos de física.',
        ]);

        // 5. Create Academic Entities for School B
        RlsManager::setTenantContext($this->schoolB->id);
        $this->campusB = Campus::create(['school_id' => $this->schoolB->id, 'name' => 'Sede B', 'code' => 'SB', 'is_main' => true]);

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

    public function test_can_generate_report_card_for_student_context(): void
    {
        $response = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201)
            ->assertJsonPath('data.student_id', $this->studentA1->id)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.is_latest', true)
            ->assertJsonPath('data.status', 'GENERATED');

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseHas('report_cards', [
            'school_id' => $this->schoolA->id,
            'student_id' => $this->studentA1->id,
            'version' => 1,
            'is_latest' => true,
        ]);
    }

    public function test_snapshot_contains_grades_scales_attendance_and_observations(): void
    {
        $response = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(201);
        $snapshot = $response->json('data.data_snapshot');

        $this->assertEquals('Física', $snapshot['grades'][0]['subject_name']);
        $this->assertEquals(4.80, $snapshot['grades'][0]['numeric_score']);
        $this->assertEquals('Excelente', $snapshot['grades'][0]['performance_item']['name']);
        $this->assertEquals('Desempeño Superior', $snapshot['grades'][0]['performance_item']['label']);
        $this->assertEquals(1, $snapshot['attendance_summary']['total_records']);
        $this->assertEquals(1, $snapshot['attendance_summary']['present_count']);
        $this->assertEquals('Excelente participación en experimentos de física.', $snapshot['observations'][0]['observation']);
    }

    public function test_snapshot_is_historically_immutable_when_underlying_data_changes(): void
    {
        // 1. Generate Report Card v1
        $res = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $cardId = $res->json('data.id');

        // 2. Modify underlying source grade in database
        RlsManager::setTenantContext($this->schoolA->id);
        PeriodFinalGrade::where('student_id', $this->studentA1->id)
            ->update(['numeric_score' => 2.00, 'final_entered_value' => '2.0']);

        // 3. Query historical report card v1
        $resReread = $this->getJson("/api/v1/report-cards/{$cardId}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $resReread->assertStatus(200);

        // Confirm snapshot v1 still retains historic score 4.80!
        $this->assertEquals(4.80, $resReread->json('data.data_snapshot.grades.0.numeric_score'));
    }

    public function test_regeneration_creates_version_2_supersedes_version_1_and_links_parent_id(): void
    {
        // Version 1
        $res1 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $id1 = $res1->json('data.id');

        // Version 2 (Regeneration)
        $res2 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'regeneration_reason' => 'Actualización de notas por recuperación',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $res2->assertStatus(201)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.is_latest', true)
            ->assertJsonPath('data.parent_report_card_id', $id1)
            ->assertJsonPath('data.regeneration_reason', 'Actualización de notas por recuperación');

        // Check Version 1 status passed to SUPERSEDED
        $res1Reread = $this->getJson("/api/v1/report-cards/{$id1}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res1Reread->assertStatus(200)
            ->assertJsonPath('data.is_latest', false)
            ->assertJsonPath('data.status', 'SUPERSEDED');
    }

    public function test_locked_report_card_rejects_regeneration(): void
    {
        // Version 1
        $res1 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $id1 = $res1->json('data.id');

        // Lock Version 1
        RlsManager::setTenantContext($this->schoolA->id);
        ReportCard::find($id1)->update(['status' => 'LOCKED']);

        // Attempt regeneration
        $res2 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $res2->assertStatus(422);
    }

    public function test_published_report_card_rejects_regeneration(): void
    {
        // Version 1
        $res1 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $id1 = $res1->json('data.id');
        $snapshot1 = $res1->json('data.data_snapshot');

        // Mark Version 1 as PUBLISHED
        RlsManager::setTenantContext($this->schoolA->id);
        ReportCard::find($id1)->update(['status' => 'PUBLISHED']);

        // Read published report card before regeneration attempt
        $res1Before = $this->getJson("/api/v1/report-cards/{$id1}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res1Before->assertStatus(200);
        $dataBefore = $res1Before->json('data');

        // Attempt regeneration -> Rejected with 422
        $res2 = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'regeneration_reason' => 'Intento de nueva versión post-publicación',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $res2->assertStatus(422);

        // Verify total count of report cards in database is still 1 (no new version created)
        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals(1, ReportCard::where('student_id', $this->studentA1->id)->count());

        // Version 1 reread -> remains intact
        $res1After = $this->getJson("/api/v1/report-cards/{$id1}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res1After->assertStatus(200);
        $dataAfter = $res1After->json('data');

        $this->assertEquals('PUBLISHED', $dataAfter['status']);
        $this->assertTrue($dataAfter['is_latest']);
        $this->assertEquals(1, $dataAfter['version']);
        $this->assertNull($dataAfter['parent_report_card_id']);
        $this->assertEquals($dataBefore['data_snapshot'], $dataAfter['data_snapshot']);
    }

    public function test_cross_tenant_student_or_course_generation_rejected(): void
    {
        // School A tries to generate report card for School B student
        RlsManager::setTenantContext($this->schoolB->id);
        $studentB = Student::factory()->create(['school_id' => $this->schoolB->id]);

        $response = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $studentB->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(404);
    }

    public function test_rbac_reports_view_and_reports_generate_permissions_enforced(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);

        $userNoPerm = User::factory()->create();
        $linkNoPerm = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $userNoPerm->id, 'status' => 'ACTIVE']);
        $roleNoPerm = Role::create(['school_id' => $this->schoolA->id, 'name' => 'READONLY_ROLE', 'guard_name' => 'web']);
        $permView = Permission::where('name', 'reports.view')->first();
        $roleNoPerm->permissions()->sync([$permView->id]);
        $linkNoPerm->roles()->sync([$roleNoPerm->id => ['school_id' => $this->schoolA->id]]);

        // GET allowed
        $this->getJson('/api/v1/report-cards', $this->authHeaders($userNoPerm, $this->schoolA, $this->campusA))
            ->assertStatus(200);

        // POST generate rejected with 403 Forbidden
        $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($userNoPerm, $this->schoolA, $this->campusA))
            ->assertStatus(403);
    }

    public function test_read_only_guarantee_source_grades_are_never_mutated_during_generation(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $gradeBefore = PeriodFinalGrade::where('student_id', $this->studentA1->id)->first();

        $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $this->studentA1->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA))->assertStatus(201);

        RlsManager::setTenantContext($this->schoolA->id);
        $gradeAfter = PeriodFinalGrade::where('student_id', $this->studentA1->id)->first();

        // Source grade attributes remain 100% identical
        $this->assertEquals($gradeBefore->numeric_score, $gradeAfter->numeric_score);
        $this->assertEquals($gradeBefore->final_entered_value, $gradeAfter->final_entered_value);
        $this->assertEquals($gradeBefore->updated_at->toDateTimeString(), $gradeAfter->updated_at->toDateTimeString());
    }

    public function test_template_resolution_grade_plus_subject_precedence(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);

        $gradeSubjTpl = ReportCardTemplate::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Plantilla Específica Grado+Subject',
            'is_active' => true,
        ]);

        ReportCardAssignment::create([
            'school_id' => $this->schoolA->id,
            'template_id' => $gradeSubjTpl->id,
            'grade_id' => $this->grade11A->id,
            'subject_id' => $this->subjectPhysics->id,
        ]);

        $res = $this->getJson("/api/v1/report-card-templates/resolve?course_id={$this->course11A->id}&subject_id={$this->subjectPhysics->id}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res->assertStatus(200)->assertJsonPath('data.id', $gradeSubjTpl->id);
    }

    public function test_generation_when_student_has_no_grades_yet(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);

        $studentNoGrades = Student::factory()->create(['school_id' => $this->schoolA->id]);

        $res = $this->postJson('/api/v1/report-cards/generate', [
            'student_id' => $studentNoGrades->id,
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $res->assertStatus(201)
            ->assertJsonPath('data.student_id', $studentNoGrades->id)
            ->assertJsonCount(0, 'data.data_snapshot.grades');
    }
}
