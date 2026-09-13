<?php

namespace Tests\Feature\Phase4;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GradingApiTest extends TestCase
{
    private array $schoolA;
    private array $schoolB;
    private array $userAdminA;
    private array $userAdminB;
    private array $userNoPerms;
    private int $campusA1;
    private int $campusA2;
    private int $campusB1;
    private int $academicYearA;
    private int $academicYearB;
    private int $periodOpenA;
    private int $periodClosedA;
    private int $periodLockedA;
    private int $periodOpenB;
    private int $gradeA;
    private int $gradeB;
    private int $courseA1;
    private int $courseA2;
    private int $courseA3Campus2;
    private int $courseB1;
    private int $subjectMathA;
    private int $subjectSpanishA;
    private int $subjectMathB;
    private int $categoryQuizA;
    private int $categoryExamB;
    private int $teacherA1;
    private int $studentA1;
    private int $studentA2;
    private int $studentA3NotEnrolled;
    private int $studentB1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDatabaseFixtures();
    }

    private function seedDatabaseFixtures(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // Clean tables
        $sys->statement("TRUNCATE TABLE assessments, assessment_categories, teachers, subjects, knowledge_areas, courses, grades, educational_levels, academic_periods, academic_years, campuses, school_user_roles, school_users, roles, permissions, schools, users RESTART IDENTITY CASCADE;");

        // Create Schools
        $schoolAId = $sys->table('schools')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Colegio Santa Cecilia',
            'code' => 'SANTA_CECILIA',
            'slug' => 'colegio-santa-cecilia-' . Str::random(4),
            'status' => 'ACTIVE',
        ]);

        $schoolBId = $sys->table('schools')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Colegio San José',
            'code' => 'SAN_JOSE',
            'slug' => 'colegio-san-jose-' . Str::random(4),
            'status' => 'ACTIVE',
        ]);

        $this->schoolA = ['id' => $schoolAId, 'name' => 'Colegio Santa Cecilia'];
        $this->schoolB = ['id' => $schoolBId, 'name' => 'Colegio San José'];

        // Create Users
        $adminAId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'admin.santa@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'Admin',
            'last_name' => 'Santa',
            'status' => 'ACTIVE',
        ]);

        $adminBId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'admin.jose@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'Admin',
            'last_name' => 'Jose',
            'status' => 'ACTIVE',
        ]);

        $noPermsId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'noperms@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'No',
            'last_name' => 'Perms',
            'status' => 'ACTIVE',
        ]);

        $this->userAdminA = ['id' => $adminAId, 'email' => 'admin.santa@example.com'];
        $this->userAdminB = ['id' => $adminBId, 'email' => 'admin.jose@example.com'];
        $this->userNoPerms = ['id' => $noPermsId, 'email' => 'noperms@example.com'];

        // School memberships
        $suA = $sys->table('school_users')->insertGetId(['school_id' => $schoolAId, 'user_id' => $adminAId, 'status' => 'ACTIVE']);
        $suB = $sys->table('school_users')->insertGetId(['school_id' => $schoolBId, 'user_id' => $adminBId, 'status' => 'ACTIVE']);
        $sys->table('school_users')->insert(['school_id' => $schoolAId, 'user_id' => $noPermsId, 'status' => 'ACTIVE']);

        // Permissions
        $permissions = ['grades.view', 'grades.create', 'courses.view', 'campuses.view'];
        foreach ($permissions as $pName) {
            $sys->table('permissions')->updateOrInsert(
                ['name' => $pName],
                ['guard_name' => 'web', 'module' => 'grading', 'description' => $pName]
            );
        }

        $allPermIds = $sys->table('permissions')->whereIn('name', $permissions)->pluck('id');

        $roleAdminAId = $sys->table('roles')->insertGetId(['school_id' => $schoolAId, 'name' => 'SCHOOL_ADMIN', 'guard_name' => 'web', 'is_system' => true]);
        $roleAdminBId = $sys->table('roles')->insertGetId(['school_id' => $schoolBId, 'name' => 'SCHOOL_ADMIN', 'guard_name' => 'web', 'is_system' => true]);

        $sys->table('school_user_roles')->insert([
            ['school_id' => $schoolAId, 'school_user_id' => $suA, 'role_id' => $roleAdminAId],
            ['school_id' => $schoolBId, 'school_user_id' => $suB, 'role_id' => $roleAdminBId],
        ]);

        foreach ($allPermIds as $pId) {
            $sys->table('role_permissions')->insert([
                ['role_id' => $roleAdminAId, 'permission_id' => $pId],
                ['role_id' => $roleAdminBId, 'permission_id' => $pId],
            ]);
        }

        // Campuses
        $this->campusA1 = $sys->table('campuses')->insertGetId(['school_id' => $schoolAId, 'name' => 'Sede Principal', 'code' => 'MAIN', 'is_main' => true, 'status' => 'ACTIVE']);
        $this->campusA2 = $sys->table('campuses')->insertGetId(['school_id' => $schoolAId, 'name' => 'Sede San Isidro', 'code' => 'SAN_ISIDRO', 'is_main' => false, 'status' => 'ACTIVE']);
        $this->campusB1 = $sys->table('campuses')->insertGetId(['school_id' => $schoolBId, 'name' => 'Sede Norte', 'code' => 'NORTE', 'is_main' => true, 'status' => 'ACTIVE']);

        // Academic Years & Periods
        $this->academicYearA = $sys->table('academic_years')->insertGetId(['school_id' => $schoolAId, 'name' => 'Año 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);
        $this->academicYearB = $sys->table('academic_years')->insertGetId(['school_id' => $schoolBId, 'name' => 'Año 2026 B', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);

        $this->periodOpenA = $sys->table('academic_periods')->insertGetId([
            'school_id' => $schoolAId, 'academic_year_id' => $this->academicYearA, 'name' => 'Periodo 1', 'period_order' => 1, 'weight_percentage' => 25.00, 'start_date' => '2026-01-15', 'end_date' => '2026-04-10', 'status' => 'OPEN'
        ]);

        $this->periodClosedA = $sys->table('academic_periods')->insertGetId([
            'school_id' => $schoolAId, 'academic_year_id' => $this->academicYearA, 'name' => 'Periodo 2', 'period_order' => 2, 'weight_percentage' => 25.00, 'start_date' => '2026-04-11', 'end_date' => '2026-06-25', 'status' => 'CLOSED'
        ]);

        $this->periodLockedA = $sys->table('academic_periods')->insertGetId([
            'school_id' => $schoolAId, 'academic_year_id' => $this->academicYearA, 'name' => 'Periodo 3', 'period_order' => 3, 'weight_percentage' => 25.00, 'start_date' => '2026-06-26', 'end_date' => '2026-09-15', 'status' => 'LOCKED'
        ]);

        $this->periodOpenB = $sys->table('academic_periods')->insertGetId([
            'school_id' => $schoolBId, 'academic_year_id' => $this->academicYearB, 'name' => 'Periodo 1 B', 'period_order' => 1, 'weight_percentage' => 25.00, 'start_date' => '2026-01-15', 'end_date' => '2026-04-10', 'status' => 'OPEN'
        ]);

        // Educational Levels & Grades
        $levelA = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolAId, 'name' => 'Secundaria', 'code' => 'SEC']);
        $levelB = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolBId, 'name' => 'Secundaria B', 'code' => 'SEC_B']);

        $this->gradeA = $sys->table('grades')->insertGetId(['school_id' => $schoolAId, 'educational_level_id' => $levelA, 'name' => 'Once', 'code' => '11']);
        $this->gradeB = $sys->table('grades')->insertGetId(['school_id' => $schoolBId, 'educational_level_id' => $levelB, 'name' => 'Once B', 'code' => '11B']);

        // Courses
        $this->courseA1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'name' => '11A',
        ]);
        $this->courseA2 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'name' => '11B',
        ]);
        $this->courseA3Campus2 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA2, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'name' => '11C_SANISIDRO',
        ]);
        $this->courseB1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolBId, 'campus_id' => $this->campusB1, 'academic_year_id' => $this->academicYearB, 'grade_id' => $this->gradeB, 'name' => '11B_JOSE',
        ]);

        // Knowledge Area & Subjects
        $kaA = $sys->table('knowledge_areas')->insertGetId(['school_id' => $schoolAId, 'name' => 'Matemáticas Area', 'code' => 'MAT_AREA']);
        $kaB = $sys->table('knowledge_areas')->insertGetId(['school_id' => $schoolBId, 'name' => 'Matemáticas Area B', 'code' => 'MAT_AREA_B']);

        $this->subjectMathA = $sys->table('subjects')->insertGetId(['school_id' => $schoolAId, 'knowledge_area_id' => $kaA, 'name' => 'Matemáticas', 'code' => 'MAT']);
        $this->subjectSpanishA = $sys->table('subjects')->insertGetId(['school_id' => $schoolAId, 'knowledge_area_id' => $kaA, 'name' => 'Español', 'code' => 'ESP']);
        $this->subjectMathB = $sys->table('subjects')->insertGetId(['school_id' => $schoolBId, 'knowledge_area_id' => $kaB, 'name' => 'Matemáticas B', 'code' => 'MAT_B']);

        // Assessment Categories
        $this->categoryQuizA = $sys->table('assessment_categories')->insertGetId([
            'school_id' => $schoolAId, 'name' => 'Quices', 'default_weight_percentage' => 20.00
        ]);
        $this->categoryExamB = $sys->table('assessment_categories')->insertGetId([
            'school_id' => $schoolBId, 'name' => 'Exámenes B', 'default_weight_percentage' => 30.00
        ]);

        // Teachers
        $teacherUserA = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'email' => 'teacher.a1@example.com', 'password' => Hash::make('password123'), 'first_name' => 'Pedro', 'last_name' => 'Ramírez'
        ]);
        $sys->table('school_users')->insert(['school_id' => $schoolAId, 'user_id' => $teacherUserA, 'status' => 'ACTIVE']);
        $this->teacherA1 = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolAId, 'user_id' => $teacherUserA, 'teacher_code' => 'DOC-001', 'document_type' => 'CC', 'document_number' => '10203040', 'first_name' => 'Pedro', 'last_name' => 'Ramírez', 'email' => 'teacher.a1@example.com'
        ]);

        // Students in School A
        $this->studentA1 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolAId, 'student_code' => 'STD-A1', 'first_name' => 'Ana', 'last_name' => 'Gómez', 'document_type' => 'TI', 'document_number' => '1001001'
        ]);
        $this->studentA2 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolAId, 'student_code' => 'STD-A2', 'first_name' => 'Bernardo', 'last_name' => 'Pérez', 'document_type' => 'TI', 'document_number' => '1001002'
        ]);
        $this->studentA3NotEnrolled = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolAId, 'student_code' => 'STD-A3', 'first_name' => 'Carlos', 'last_name' => 'López', 'document_type' => 'TI', 'document_number' => '1001003'
        ]);

        // Student in School B
        $this->studentB1 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolBId, 'student_code' => 'STD-B1', 'first_name' => 'Diana', 'last_name' => 'Martínez', 'document_type' => 'TI', 'document_number' => '2002001'
        ]);

        // Enrollments for Student A1 & A2 in Course A1
        $seA1 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolAId, 'student_id' => $this->studentA1, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'enrollment_date' => '2026-01-15', 'status' => 'ENROLLED'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolAId, 'student_enrollment_id' => $seA1, 'course_id' => $this->courseA1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);

        $seA2 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolAId, 'student_id' => $this->studentA2, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'enrollment_date' => '2026-01-15', 'status' => 'ENROLLED'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolAId, 'student_enrollment_id' => $seA2, 'course_id' => $this->courseA1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);

        // Enrollment for Student B1 in Course B1
        $seB1 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolBId, 'student_id' => $this->studentB1, 'academic_year_id' => $this->academicYearB, 'grade_id' => $this->gradeB, 'enrollment_date' => '2026-01-15', 'status' => 'ENROLLED'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolBId, 'student_enrollment_id' => $seB1, 'course_id' => $this->courseB1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);

        // Numeric Scale for School A
        $scaleA = $sys->table('grading_scales')->insertGetId([
            'school_id' => $schoolAId, 'name' => 'Escala 1-5', 'scale_type' => 'NUMERIC', 'min_score' => 1.00, 'max_score' => 5.00, 'passing_score' => 3.00, 'decimal_places' => 1, 'is_default' => true
        ]);
        $sys->table('grading_scale_items')->insert([
            ['school_id' => $schoolAId, 'grading_scale_id' => $scaleA, 'name' => 'Bajo', 'min_value' => 1.00, 'max_value' => 2.90, 'equivalent_numeric_value' => 2.00, 'item_order' => 1],
            ['school_id' => $schoolAId, 'grading_scale_id' => $scaleA, 'name' => 'Básico', 'min_value' => 3.00, 'max_value' => 3.90, 'equivalent_numeric_value' => 3.50, 'item_order' => 2],
            ['school_id' => $schoolAId, 'grading_scale_id' => $scaleA, 'name' => 'Alto', 'min_value' => 4.00, 'max_value' => 4.50, 'equivalent_numeric_value' => 4.20, 'item_order' => 3],
            ['school_id' => $schoolAId, 'grading_scale_id' => $scaleA, 'name' => 'Excelente', 'min_value' => 4.60, 'max_value' => 5.00, 'equivalent_numeric_value' => 4.80, 'item_order' => 4],
        ]);
    }

    private function authHeaders(array $user, int $schoolId, ?int $campusId = null): array
    {
        $userModel = User::find($user['id']);
        $token = $userModel->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;

        $headers = [
            'Authorization' => "Bearer {$token}",
        ];

        if ($campusId !== null) {
            $headers['X-Campus-ID'] = (string) $campusId;
        }

        return $headers;
    }

    // -------------------------------------------------------------
    // POST /api/v1/assessments TESTS
    // -------------------------------------------------------------

    public function test_guest_cannot_create_assessment(): void
    {
        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz 1',
            'weight_percentage' => 20.00,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_grades_create_permission_cannot_create_assessment(): void
    {
        $headers = $this->authHeaders($this->userNoPerms, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz 1',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(403);
    }

    public function test_create_single_assessment_success(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $payload = [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'assessment_category_id' => $this->categoryQuizA,
            'teacher_id' => $this->teacherA1,
            'title' => 'Quiz 1: Fracciones',
            'description' => 'Evaluación de fracciones heterogéneas',
            'weight_percentage' => 25.00,
            'due_date' => '2026-03-15',
            'max_score' => 5.00,
        ];

        $response = $this->json('POST', '/api/v1/assessments', $payload, $headers);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Evaluación(es) creada(s) exitosamente.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Quiz 1: Fracciones')
            ->assertJsonPath('data.0.school_id', $this->schoolA['id']);

        $sys = DB::connection('pgsql_system');
        $this->assertEquals(1, $sys->table('assessments')->where([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz 1: Fracciones',
        ])->count());
    }

    public function test_create_mass_assessments_across_multiple_courses_success(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $payload = [
            'course_ids' => [$this->courseA1, $this->courseA2],
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'assessment_category_id' => $this->categoryQuizA,
            'title' => 'Examen Parcial 1',
            'weight_percentage' => 30.00,
            'max_score' => 5.00,
        ];

        $response = $this->json('POST', '/api/v1/assessments', $payload, $headers);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.course_id', $this->courseA1)
            ->assertJsonPath('data.1.course_id', $this->courseA2);

        $sys = DB::connection('pgsql_system');
        $this->assertEquals(1, $sys->table('assessments')->where([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'title' => 'Examen Parcial 1',
        ])->count());

        $this->assertEquals(1, $sys->table('assessments')->where([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA2,
            'title' => 'Examen Parcial 1',
        ])->count());
    }

    public function test_cannot_create_assessment_when_weight_exceeds_100_percent(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // First assessment: 60%
        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Evaluación 1',
            'weight_percentage' => 60.00,
        ], $headers)->assertStatus(201);

        // Second assessment: 50% (60 + 50 = 110%) -> should fail
        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Evaluación 2',
            'weight_percentage' => 50.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La suma total de pesos de las evaluaciones para el curso ID ' . $this->courseA1 . ' supera el 100% (actual: 60%, nuevo: 50%).');
    }

    public function test_progressive_weight_configuration_succeeds_up_to_100_percent(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // 30% + 30% + 40% = 100%
        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'title' => 'A1', 'weight_percentage' => 30.00,
        ], $headers)->assertStatus(201);

        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'title' => 'A2', 'weight_percentage' => 30.00,
        ], $headers)->assertStatus(201);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'title' => 'A3', 'weight_percentage' => 40.00,
        ], $headers);

        $response->assertStatus(201);
    }

    public function test_cannot_create_assessment_in_closed_academic_period(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodClosedA,
            'title' => 'Quiz en periodo cerrado',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No se pueden crear evaluaciones en un período académico cerrado o bloqueado.');
    }

    public function test_cannot_create_assessment_in_locked_academic_period(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodLockedA,
            'title' => 'Quiz en periodo bloqueado',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No se pueden crear evaluaciones en un período académico cerrado o bloqueado.');
    }

    public function test_cannot_create_assessment_for_course_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseB1, // Belongs to School B
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz Cross Tenant',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso con ID ' . $this->courseB1 . ' no existe o no pertenece al colegio activo.');
    }

    public function test_cannot_create_assessment_for_subject_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathB, // Belongs to School B
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz Cross Subject',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La asignatura no existe o no pertenece al colegio activo.');
    }

    public function test_cannot_create_assessment_for_category_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'assessment_category_id' => $this->categoryExamB, // Belongs to School B
            'title' => 'Quiz Cross Category',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La categoría de evaluación no existe o no pertenece al colegio activo.');
    }

    public function test_campus_scope_isolation_prevents_creating_assessment_for_course_in_another_campus(): void
    {
        // Authenticated as Admin A, Header X-Campus-ID = CampusA1
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id'], $this->campusA1);

        // CourseA3Campus2 belongs to CampusA2
        $response = $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA3Campus2,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz Cross Campus',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso con ID ' . $this->courseA3Campus2 . ' no pertenece a la sede activa.');
    }

    public function test_validation_fails_for_missing_required_fields(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', '/api/v1/assessments', [], $headers);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id', 'academic_period_id', 'title', 'weight_percentage']);
    }

    public function test_atomic_mass_creation_rolls_back_entire_transaction_if_one_course_fails_weight_rule(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // First pre-fill courseA1 with 90%
        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Pre-fill 90%',
            'weight_percentage' => 90.00,
        ], $headers)->assertStatus(201);

        // Try mass assignment for courseA1 and courseA2 with 20%
        // courseA2 would fit (0 + 20 = 20%), but courseA1 will fail (90 + 20 = 110%)
        $response = $this->json('POST', '/api/v1/assessments', [
            'course_ids' => [$this->courseA2, $this->courseA1],
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Mass Fail',
            'weight_percentage' => 20.00,
        ], $headers);

        $response->assertStatus(422);

        // Verify no assessment titled 'Mass Fail' exists for either course
        $this->assertDatabaseMissing('assessments', [
            'school_id' => $this->schoolA['id'],
            'title' => 'Mass Fail',
        ]);
    }

    // -------------------------------------------------------------
    // GET /api/v1/courses/{course}/subjects/{subject}/assessments TESTS
    // -------------------------------------------------------------

    public function test_guest_cannot_list_assessments(): void
    {
        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/assessments");

        $response->assertStatus(401);
    }

    public function test_user_without_grades_view_permission_cannot_list_assessments(): void
    {
        $headers = $this->authHeaders($this->userNoPerms, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/assessments", [], $headers);

        $response->assertStatus(403);
    }

    public function test_list_assessments_for_course_and_subject_success(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Create 2 assessments
        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz 1',
            'weight_percentage' => 30.00,
            'due_date' => '2026-03-10',
        ], $headers)->assertStatus(201);

        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz 2',
            'weight_percentage' => 30.00,
            'due_date' => '2026-03-20',
        ], $headers)->assertStatus(201);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/assessments", [], $headers);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Quiz 1')
            ->assertJsonPath('data.1.title', 'Quiz 2');
    }

    public function test_list_assessments_filtered_by_academic_period(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Assessment in Period P1
        $this->json('POST', '/api/v1/assessments', [
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'academic_period_id' => $this->periodOpenA,
            'title' => 'Quiz P1',
            'weight_percentage' => 30.00,
        ], $headers)->assertStatus(201);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/assessments?academic_period_id={$this->periodOpenA}", [], $headers);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Quiz P1');
    }

    public function test_list_assessments_returns_empty_array_when_no_assessments_exist(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/assessments", [], $headers);

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_cannot_list_assessments_for_course_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseB1}/subjects/{$this->subjectMathA}/assessments", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso no existe o no pertenece al colegio activo.');
    }

    public function test_campus_scope_isolation_prevents_listing_assessments_for_course_in_another_campus(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id'], $this->campusA1);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA3Campus2}/subjects/{$this->subjectMathA}/assessments", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso no pertenece a la sede activa.');
    }

    // -------------------------------------------------------------
    // POST /api/v1/assessments/{assessment}/grades (BLOQUE B2 TESTS)
    // -------------------------------------------------------------

    public function test_guest_cannot_record_assessment_grades(): void
    {
        // First create an assessment
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Quiz Test B2', 'weight_percentage' => 30.00, 'max_score' => 5.00,
        ]);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.5]
            ]
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_grades_create_permission_cannot_record_grades(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Quiz Test B2 NoPerm', 'weight_percentage' => 30.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userNoPerms, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.5]
            ]
        ], $headers);

        $response->assertStatus(403);
    }

    public function test_record_single_student_grade_success(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Parcial 1 B2', 'weight_percentage' => 100.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.5, 'comments' => 'Excelente desempeño']
            ]
        ], $headers);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Calificaciones registradas exitosamente.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student_id', $this->studentA1)
            ->assertJsonPath('data.0.entered_value', '4.5')
            ->assertJsonPath('data.0.equivalent_numeric_value', 4.5);

        // Verify DB record in student_grades using system connection to bypass RLS in test assertion
        $this->assertEquals(1, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'],
            'assessment_id' => $assId,
            'student_id' => $this->studentA1,
            'entered_value' => '4.5',
            'comments' => 'Excelente desempeño',
        ])->count());

        // Verify PeriodFinalGrade updated by GradeCalculationEngine
        $this->assertEquals(1, $sys->table('period_final_grades')->where([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'student_id' => $this->studentA1,
            'academic_period_id' => $this->periodOpenA,
            'final_entered_value' => '4.5',
        ])->count());
    }

    public function test_record_mass_student_grades_success(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Taller Grupal B2', 'weight_percentage' => 50.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.0],
                ['student_id' => $this->studentA2, 'entered_value' => 3.5],
            ]
        ], $headers);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data');

        $this->assertEquals(1, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'], 'assessment_id' => $assId, 'student_id' => $this->studentA1, 'entered_value' => '4',
        ])->count());

        $this->assertEquals(1, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'], 'assessment_id' => $assId, 'student_id' => $this->studentA2, 'entered_value' => '3.5',
        ])->count());
    }

    public function test_updating_existing_student_grade_success(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Update Test B2', 'weight_percentage' => 100.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // First insert 3.0
        $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 3.0]]
        ], $headers)->assertStatus(201);

        // Update to 4.8
        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.8]]
        ], $headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.0.entered_value', '4.8');

        // Only 1 record exists in student_grades for (school, assessment, student)
        $this->assertEquals(1, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'], 'assessment_id' => $assId, 'student_id' => $this->studentA1
        ])->count());

        $this->assertEquals(1, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'], 'assessment_id' => $assId, 'student_id' => $this->studentA1, 'entered_value' => '4.8'
        ])->count());
    }

    public function test_cannot_record_grades_for_assessment_belonging_to_another_school(): void
    {
        $sys = DB::connection('pgsql_system');
        $teacherUserB = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(), 'email' => 'teacher.b1@example.com', 'password' => Hash::make('password123'), 'first_name' => 'Mario', 'last_name' => 'Bros', 'status' => 'ACTIVE'
        ]);
        $sys->table('school_users')->insert(['school_id' => $this->schoolB['id'], 'user_id' => $teacherUserB, 'status' => 'ACTIVE']);
        $teacherB1 = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolB['id'], 'user_id' => $teacherUserB, 'teacher_code' => 'DOC-B-001', 'document_type' => 'CC', 'document_number' => '99887766', 'first_name' => 'Mario', 'last_name' => 'Bros', 'email' => 'teacher.b1@example.com'
        ]);

        $assBId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolB['id'], 'course_id' => $this->courseB1, 'subject_id' => $this->subjectMathB, 'academic_period_id' => $this->periodOpenB, 'teacher_id' => $teacherB1, 'title' => 'Quiz B', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assBId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La evaluación no existe o no pertenece al colegio activo.');
    }

    public function test_cannot_record_grades_for_student_belonging_to_another_school(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Cross Student Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentB1, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Uno o más estudiantes no existen o no pertenecen al colegio activo.');
    }

    public function test_cannot_record_grades_for_student_not_enrolled_in_course(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Unenrolled Student Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA3NotEnrolled, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', "El estudiante ID {$this->studentA3NotEnrolled} no está matriculado activamente en el curso de la evaluación.");
    }

    public function test_campus_scope_isolation_prevents_recording_grades_for_assessment_in_another_campus(): void
    {
        $sys = DB::connection('pgsql_system');
        $assCampus2Id = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA3Campus2, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Campus 2 Assessment', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        // Request with Header X-Campus-ID = Campus A1
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id'], $this->campusA1);

        $response = $this->json('POST', "/api/v1/assessments/{$assCampus2Id}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La evaluación no pertenece a la sede activa.');
    }

    public function test_cannot_record_grades_when_entered_value_exceeds_max_score(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Max Score Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 10.0]] // max_score is 5.0
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La calificación 10 está fuera del rango permitido (1 - 5).');
    }

    public function test_cannot_record_grades_when_entered_value_is_invalid_for_numeric_scale(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Non Numeric Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 'INVALID_GRADE']]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', "La calificación 'INVALID_GRADE' debe ser numérica.");
    }

    public function test_cannot_record_grades_in_closed_academic_period(): void
    {
        $sys = DB::connection('pgsql_system');
        $assClosedId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodClosedA, 'teacher_id' => $this->teacherA1, 'title' => 'Closed Period Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assClosedId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No se pueden registrar calificaciones en un período académico cerrado o bloqueado.');
    }

    public function test_cannot_record_grades_in_locked_academic_period(): void
    {
        $sys = DB::connection('pgsql_system');
        $assLockedId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodLockedA, 'teacher_id' => $this->teacherA1, 'title' => 'Locked Period Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assLockedId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.0]]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No se pueden registrar calificaciones en un período académico cerrado o bloqueado.');
    }

    public function test_request_with_duplicate_student_ids_rejected(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Duplicate Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.0],
                ['student_id' => $this->studentA1, 'entered_value' => 4.5],
            ]
        ], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El request contiene registros duplicados para el mismo estudiante.');
    }

    public function test_atomic_mass_grade_recording_rolls_back_if_one_student_invalid(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Atomic Rollback Test B2', 'weight_percentage' => 20.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // studentA1 is valid, studentA3NotEnrolled is invalid
        $response = $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [
                ['student_id' => $this->studentA1, 'entered_value' => 4.0],
                ['student_id' => $this->studentA3NotEnrolled, 'entered_value' => 4.5],
            ]
        ], $headers);

        $response->assertStatus(422);

        // Verify studentA1 did NOT get saved in student_grades due to transaction rollback
        $this->assertEquals(0, $sys->table('student_grades')->where([
            'school_id' => $this->schoolA['id'], 'assessment_id' => $assId
        ])->count());
    }

    public function test_record_exempt_student_grade_success_and_skips_in_weighted_calculation(): void
    {
        $sys = DB::connection('pgsql_system');
        // Assessment 1: 50%
        $ass1Id = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Ass 1 Exempt Test', 'weight_percentage' => 50.00, 'max_score' => 5.00,
        ]);
        // Assessment 2: 50%
        $ass2Id = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Ass 2 Exempt Test', 'weight_percentage' => 50.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Ass 1: Exempt
        $this->json('POST', "/api/v1/assessments/{$ass1Id}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 1.0, 'is_exempt' => true]]
        ], $headers)->assertStatus(201);

        // Ass 2: 4.0
        $this->json('POST', "/api/v1/assessments/{$ass2Id}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.0, 'is_exempt' => false]]
        ], $headers)->assertStatus(201);

        // Final grade should equal 4.0 (since Ass 1 was exempt, only Ass 2 count applies)
        $this->assertEquals(1, $sys->table('period_final_grades')->where([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectMathA,
            'student_id' => $this->studentA1,
            'academic_period_id' => $this->periodOpenA,
            'final_entered_value' => '4',
        ])->count());
    }

    // -------------------------------------------------------------
    // GET /api/v1/courses/{course}/subjects/{subject}/grades-matrix (BLOQUE B3 TESTS)
    // -------------------------------------------------------------

    public function test_guest_cannot_get_grades_matrix(): void
    {
        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/grades-matrix");

        $response->assertStatus(401);
    }

    public function test_user_without_grades_view_permission_cannot_get_matrix(): void
    {
        $headers = $this->authHeaders($this->userNoPerms, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/grades-matrix", [], $headers);

        $response->assertStatus(403);
    }

    public function test_get_grades_matrix_success(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Parcial Matrix B3', 'weight_percentage' => 100.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Record a grade
        $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.2]]
        ], $headers)->assertStatus(201);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/grades-matrix", [], $headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.course.id', $this->courseA1)
            ->assertJsonPath('data.subject.id', $this->subjectMathA)
            ->assertJsonPath('data.students.0.student_id', $this->studentA1)
            ->assertJsonPath('data.students.0.grades.0.grade.entered_value', '4.2')
            ->assertJsonPath('data.students.0.final_grades.0.final_entered_value', '4.2');
    }

    public function test_grades_matrix_filtered_by_academic_period(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Matrix Period Filter Test', 'weight_percentage' => 100.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/grades-matrix?academic_period_id={$this->periodOpenA}", [], $headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.academic_period_id', $this->periodOpenA);
    }

    public function test_cannot_get_matrix_for_course_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseB1}/subjects/{$this->subjectMathA}/grades-matrix", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso no existe o no pertenece al colegio activo.');
    }

    public function test_cannot_get_matrix_for_subject_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathB}/grades-matrix", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'La asignatura no existe o no pertenece al colegio activo.');
    }

    public function test_campus_scope_isolation_prevents_getting_matrix_for_course_in_another_campus(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id'], $this->campusA1);

        $response = $this->json('GET', "/api/v1/courses/{$this->courseA3Campus2}/subjects/{$this->subjectMathA}/grades-matrix", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'El curso no pertenece a la sede activa.');
    }

    public function test_matrix_read_only_does_not_mutate_db_or_create_student_grades(): void
    {
        $sys = DB::connection('pgsql_system');
        $initialGradeCount = $sys->table('student_grades')->count();
        $initialFinalCount = $sys->table('period_final_grades')->count();

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $this->json('GET', "/api/v1/courses/{$this->courseA1}/subjects/{$this->subjectMathA}/grades-matrix", [], $headers)
            ->assertStatus(200);

        $this->assertEquals($initialGradeCount, $sys->table('student_grades')->count());
        $this->assertEquals($initialFinalCount, $sys->table('period_final_grades')->count());
    }

    // -------------------------------------------------------------
    // GET /api/v1/students/{student}/grades (BLOQUE B3 TESTS)
    // -------------------------------------------------------------

    public function test_guest_cannot_get_student_grades(): void
    {
        $response = $this->json('GET', "/api/v1/students/{$this->studentA1}/grades");

        $response->assertStatus(401);
    }

    public function test_user_without_grades_view_permission_cannot_get_student_grades(): void
    {
        $headers = $this->authHeaders($this->userNoPerms, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/students/{$this->studentA1}/grades", [], $headers);

        $response->assertStatus(403);
    }

    public function test_get_student_grades_success(): void
    {
        $sys = DB::connection('pgsql_system');
        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $this->schoolA['id'], 'course_id' => $this->courseA1, 'subject_id' => $this->subjectMathA, 'academic_period_id' => $this->periodOpenA, 'teacher_id' => $this->teacherA1, 'title' => 'Student Query Test B3', 'weight_percentage' => 100.00, 'max_score' => 5.00,
        ]);

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Record grade for studentA1
        $this->json('POST', "/api/v1/assessments/{$assId}/grades", [
            'grades' => [['student_id' => $this->studentA1, 'entered_value' => 4.6]]
        ], $headers)->assertStatus(201);

        $response = $this->json('GET', "/api/v1/students/{$this->studentA1}/grades", [], $headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.student.id', $this->studentA1)
            ->assertJsonPath('data.assessments.0.grade.entered_value', '4.6')
            ->assertJsonPath('data.period_final_grades.0.final_entered_value', '4.6');
    }

    public function test_cannot_get_student_grades_for_student_belonging_to_another_school(): void
    {
        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $response = $this->json('GET', "/api/v1/students/{$this->studentB1}/grades", [], $headers);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Estudiante no encontrado o no pertenece al colegio activo.');
    }

    public function test_student_grades_read_only_does_not_mutate_db(): void
    {
        $sys = DB::connection('pgsql_system');
        $initialGradeCount = $sys->table('student_grades')->count();
        $initialFinalCount = $sys->table('period_final_grades')->count();

        $headers = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        $this->json('GET', "/api/v1/students/{$this->studentA1}/grades", [], $headers)
            ->assertStatus(200);

        $this->assertEquals($initialGradeCount, $sys->table('student_grades')->count());
        $this->assertEquals($initialFinalCount, $sys->table('period_final_grades')->count());
    }

    public function test_cross_tenant_student_isolation_by_identical_personal_data(): void
    {
        $sys = DB::connection('pgsql_system');

        // Student in Tenant A with name "Juan Pérez", doc "999999"
        $stDupA = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolA['id'], 'student_code' => 'DUP-A', 'first_name' => 'Juan', 'last_name' => 'Pérez', 'document_type' => 'CC', 'document_number' => '999999'
        ]);

        // Student in Tenant B with IDENTICAL name "Juan Pérez", doc "999999"
        $stDupB = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolB['id'], 'student_code' => 'DUP-B', 'first_name' => 'Juan', 'last_name' => 'Pérez', 'document_type' => 'CC', 'document_number' => '999999'
        ]);

        // Authenticate as Tenant A
        $headersA = $this->authHeaders($this->userAdminA, $this->schoolA['id']);

        // Requesting Student B ID from Tenant A must return 422
        $response = $this->json('GET', "/api/v1/students/{$stDupB}/grades", [], $headersA);
        $response->assertStatus(422)
            ->assertJsonPath('message', 'Estudiante no encontrado o no pertenece al colegio activo.');

        // Requesting Student A ID from Tenant A returns OK
        $responseA = $this->json('GET', "/api/v1/students/{$stDupA}/grades", [], $headersA);
        $responseA->assertStatus(200)
            ->assertJsonPath('data.student.id', $stDupA)
            ->assertJsonPath('data.student.student_code', 'DUP-A');
    }
}


