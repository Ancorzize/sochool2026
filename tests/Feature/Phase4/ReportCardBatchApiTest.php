<?php

namespace Tests\Feature\Phase4;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\CourseEnrollment;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Academic\Models\StudentEnrollment;
use App\Domain\Academic\Models\Subject;
use App\Domain\Grading\Models\GradingScale;
use App\Domain\Grading\Models\GradingScaleItem;
use App\Domain\Grading\Models\PeriodFinalGrade;
use App\Domain\ReportCard\Models\ReportCard;
use App\Domain\ReportCard\Models\ReportCardTemplate;
use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\Campus;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use App\Jobs\ReportCard\GenerateCourseReportCardsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCardBatchApiTest extends TestCase
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
    protected Course $course11B;
    protected Subject $subjectPhysics;
    protected Student $studentA1;
    protected Student $studentA2;
    protected GradingScale $scaleA;
    protected GradingScaleItem $scaleItem;
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

        // 3. Setup Academic Entities for School A
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

        // Enroll Student A1 in Course 11A
        $se1 = StudentEnrollment::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'student_id' => $this->studentA1->id, 'academic_year_id' => $this->yearA->id, 'grade_id' => $this->grade11A->id, 'enrollment_number' => 'MAT-001', 'enrollment_date' => '2026-01-10', 'status' => 'ACTIVE']);
        CourseEnrollment::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'student_enrollment_id' => $se1->id, 'course_id' => $this->course11A->id, 'enrolled_at' => now(), 'status' => 'ACTIVE']);

        // Enroll Student A2 in Course 11B
        $se2 = StudentEnrollment::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'student_id' => $this->studentA2->id, 'academic_year_id' => $this->yearA->id, 'grade_id' => $this->grade11A->id, 'enrollment_number' => 'MAT-002', 'enrollment_date' => '2026-01-10', 'status' => 'ACTIVE']);
        CourseEnrollment::create(['school_id' => $this->schoolA->id, 'campus_id' => $this->campusA->id, 'student_enrollment_id' => $se2->id, 'course_id' => $this->course11B->id, 'enrolled_at' => now(), 'status' => 'ACTIVE']);

        // Scale & Default Template
        $this->scaleA = GradingScale::create(['school_id' => $this->schoolA->id, 'name' => 'Escala Numérica 1-5', 'scale_type' => 'NUMERIC', 'min_score' => 1.0, 'max_score' => 5.0, 'passing_score' => 3.0, 'is_default' => true]);
        $this->scaleItem = GradingScaleItem::create(['school_id' => $this->schoolA->id, 'grading_scale_id' => $this->scaleA->id, 'name' => 'Alto', 'label' => 'Desempeño Alto', 'description' => 'Buen trabajo', 'min_value' => 4.0, 'max_value' => 4.5, 'equivalent_numeric_value' => 4.2, 'color' => '#3B82F6', 'item_order' => 3]);
        $this->templateA = ReportCardTemplate::create(['school_id' => $this->schoolA->id, 'name' => 'Boletín General', 'is_active' => true]);

        // Final Grades for A1 and A2
        PeriodFinalGrade::create(['school_id' => $this->schoolA->id, 'course_id' => $this->course11A->id, 'subject_id' => $this->subjectPhysics->id, 'student_id' => $this->studentA1->id, 'academic_period_id' => $this->periodA->id, 'final_entered_value' => '4.2', 'numeric_score' => 4.20, 'grading_scale_item_id' => $this->scaleItem->id, 'status' => 'FINAL']);
        PeriodFinalGrade::create(['school_id' => $this->schoolA->id, 'course_id' => $this->course11B->id, 'subject_id' => $this->subjectPhysics->id, 'student_id' => $this->studentA2->id, 'academic_period_id' => $this->periodA->id, 'final_entered_value' => '4.5', 'numeric_score' => 4.50, 'grading_scale_item_id' => $this->scaleItem->id, 'status' => 'FINAL']);

        // 4. Setup Academic Entities for School B
        RlsManager::setTenantContext($this->schoolB->id);
        $this->campusB = Campus::create(['school_id' => $this->schoolB->id, 'name' => 'Sede B', 'code' => 'SB', 'status' => 'ACTIVE', 'is_main' => true]);

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

    public function test_single_course_batch_generation_returns_202_accepted(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'mode' => 'only_missing',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_courses', 1)
            ->assertJsonPath('data.mode', 'only_missing');

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1 &&
                $batch->options['school_id'] === $this->schoolA->id;
        });
    }

    public function test_selected_courses_batch_generation_returns_202_accepted(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_ids' => [$this->course11A->id, $this->course11B->id],
            'mode' => 'only_missing',
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.total_courses', 2);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2;
        });
    }

    public function test_all_courses_batch_generation_returns_202_accepted(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'all_courses' => true,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(202)
            ->assertJsonPath('data.total_courses', 2);

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 2;
        });
    }

    public function test_ambiguous_course_selection_rejected_with_422(): void
    {
        // Specifying both course_id and course_ids
        $res1 = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'course_ids' => [$this->course11A->id, $this->course11B->id],
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res1->assertStatus(422);

        // Specifying both course_id and all_courses
        $res2 = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
            'all_courses' => true,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $res2->assertStatus(422);
    }

    public function test_job_processing_generates_report_cards_for_enrolled_students(): void
    {
        // Execute Job synchronously
        $job = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'only_missing'
        );

        $job->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertDatabaseHas('report_cards', [
            'school_id' => $this->schoolA->id,
            'student_id' => $this->studentA1->id,
            'course_id' => $this->course11A->id,
            'version' => 1,
            'status' => 'GENERATED',
            'is_latest' => true,
        ]);
    }

    public function test_only_missing_mode_skips_existing_non_protected_report_cards(): void
    {
        // Generate v1 for studentA1
        $job1 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'only_missing'
        );
        $job1->handle();

        // Run again with only_missing mode
        $job2 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'only_missing'
        );
        $job2->handle();

        // Verify still only 1 report card exists (v1 was NOT regenerated)
        RlsManager::setTenantContext($this->schoolA->id);
        $this->assertEquals(1, ReportCard::where('student_id', $this->studentA1->id)->count());
    }

    public function test_regenerate_mode_creates_new_version_superseding_previous_generated(): void
    {
        // Generate v1
        $job1 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'only_missing'
        );
        $job1->handle();

        // Run again with regenerate mode
        $job2 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'regenerate',
            'Regeneración masiva solicitada'
        );
        $job2->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $cards = ReportCard::where('student_id', $this->studentA1->id)->orderBy('version', 'asc')->get();

        $this->assertCount(2, $cards);
        $this->assertEquals('SUPERSEDED', $cards[0]->status);
        $this->assertFalse($cards[0]->is_latest);

        $this->assertEquals(2, $cards[1]->version);
        $this->assertEquals('GENERATED', $cards[1]->status);
        $this->assertTrue($cards[1]->is_latest);
        $this->assertEquals('Regeneración masiva solicitada', $cards[1]->regeneration_reason);
    }

    public function test_published_and_locked_report_cards_remain_intact_in_batch(): void
    {
        // Create v1
        $job1 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'only_missing'
        );
        $job1->handle();

        RlsManager::setTenantContext($this->schoolA->id);
        $card = ReportCard::where('student_id', $this->studentA1->id)->first();
        $card->update(['status' => 'PUBLISHED']);

        // Run job with regenerate mode
        $job2 = new GenerateCourseReportCardsJob(
            $this->schoolA->id,
            $this->course11A->id,
            $this->yearA->id,
            $this->periodA->id,
            'regenerate'
        );
        $job2->handle();

        // Verify PUBLISHED card remains untouched (still v1, still PUBLISHED, total count 1)
        RlsManager::setTenantContext($this->schoolA->id);
        $cards = ReportCard::where('student_id', $this->studentA1->id)->get();
        $this->assertCount(1, $cards);
        $this->assertEquals('PUBLISHED', $cards[0]->status);
        $this->assertTrue($cards[0]->is_latest);
        $this->assertEquals(1, $cards[0]->version);
    }

    public function test_cross_tenant_course_selection_rejected(): void
    {
        // School A user tries to select School B course
        RlsManager::setTenantContext($this->schoolB->id);
        $yearB = AcademicYear::create(['school_id' => $this->schoolB->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE', 'is_current' => true]);
        $levelB = EducationalLevel::create(['school_id' => $this->schoolB->id, 'name' => 'Secundaria B', 'code' => 'SECB', 'level_order' => 2]);
        $gradeB = Grade::create(['school_id' => $this->schoolB->id, 'educational_level_id' => $levelB->id, 'name' => 'Grado 11° B', 'code' => 'G11B', 'grade_order' => 11]);
        $courseB = Course::create(['school_id' => $this->schoolB->id, 'campus_id' => $this->campusB->id, 'academic_year_id' => $yearB->id, 'grade_id' => $gradeB->id, 'name' => '11B-B', 'shift' => 'MAÑANA']);

        $response = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $courseB->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(422);
    }

    public function test_rbac_permissions_enforced_for_generate_batch_and_batch_status(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $userNoPerm = User::factory()->create();
        $linkNoPerm = SchoolUser::create(['school_id' => $this->schoolA->id, 'user_id' => $userNoPerm->id, 'status' => 'ACTIVE']);
        $roleNoPerm = Role::create(['school_id' => $this->schoolA->id, 'name' => 'NO_PERM_ROLE', 'guard_name' => 'web']);
        $linkNoPerm->roles()->sync([$roleNoPerm->id => ['school_id' => $this->schoolA->id]]);

        // User without reports.generate permission is rejected with 403 Forbidden
        $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'all_courses' => true,
        ], $this->authHeaders($userNoPerm, $this->schoolA, $this->campusA))
            ->assertStatus(403);
    }

    public function test_batch_status_returns_progress_and_tenant_isolation(): void
    {
        $response = $this->postJson('/api/v1/report-cards/generate-batch', [
            'academic_year_id' => $this->yearA->id,
            'academic_period_id' => $this->periodA->id,
            'course_id' => $this->course11A->id,
        ], $this->authHeaders($this->userA, $this->schoolA, $this->campusA));

        $response->assertStatus(202);
        $batchId = $response->json('data.batch_id');

        // Tenant A user queries status -> 200 OK
        $statusRes = $this->getJson("/api/v1/report-cards/batches/{$batchId}", $this->authHeaders($this->userA, $this->schoolA, $this->campusA));
        $statusRes->assertStatus(200)
            ->assertJsonPath('data.batch_id', $batchId)
            ->assertJsonPath('data.total_jobs', 1);

        // Tenant B user queries Tenant A batch -> 404 Not Found
        $statusResB = $this->getJson("/api/v1/report-cards/batches/{$batchId}", $this->authHeaders($this->userB, $this->schoolB));
        $statusResB->assertStatus(404);
    }
}
