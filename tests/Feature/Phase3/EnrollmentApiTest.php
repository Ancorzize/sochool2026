<?php

namespace Tests\Feature\Phase3;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnrollmentApiTest extends \Tests\TestCase
{
    private array $schoolA;
    private array $schoolB;
    private array $userAdminA;
    private array $userAdminB;
    private array $userNoPerms;
    private int $campusA1;
    private int $campusA2;
    private int $campusB1;
    private int $academicYearA1;
    private int $academicYearA2;
    private int $academicYearB1;
    private int $gradeA;
    private int $gradeB;
    private int $courseA1;
    private int $courseA2;
    private int $courseB1;
    private int $studentA1;
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

        // Cleanup test data
        $sys->statement("TRUNCATE TABLE student_transfers, course_enrollments, student_enrollments, students, course_subject_teachers, courses, teachers, subjects, knowledge_areas, grades, educational_levels, academic_years, classrooms, campuses, school_user_roles, school_users, roles, permissions, schools, users RESTART IDENTITY CASCADE;");

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
        ]);

        $adminBId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'admin.jose@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'Admin',
            'last_name' => 'Jose',
        ]);

        $noPermsId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'noperms@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'No',
            'last_name' => 'Perms',
        ]);

        $this->userAdminA = ['id' => $adminAId, 'email' => 'admin.santa@example.com'];
        $this->userAdminB = ['id' => $adminBId, 'email' => 'admin.jose@example.com'];
        $this->userNoPerms = ['id' => $noPermsId, 'email' => 'noperms@example.com'];

        // School memberships
        $suA = $sys->table('school_users')->insertGetId(['school_id' => $schoolAId, 'user_id' => $adminAId, 'status' => 'ACTIVE']);
        $suB = $sys->table('school_users')->insertGetId(['school_id' => $schoolBId, 'user_id' => $adminBId, 'status' => 'ACTIVE']);
        $sys->table('school_users')->insert(['school_id' => $schoolAId, 'user_id' => $noPermsId, 'status' => 'ACTIVE']);

        // Permissions
        $permissions = ['enrollments.create', 'enrollments.update', 'campuses.view', 'courses.view'];
        foreach ($permissions as $pName) {
            $sys->table('permissions')->updateOrInsert(
                ['name' => $pName],
                ['guard_name' => 'web', 'module' => 'academic', 'description' => $pName]
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

        // Academic Years
        $this->academicYearA1 = $sys->table('academic_years')->insertGetId(['school_id' => $schoolAId, 'name' => 'Año 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);
        $this->academicYearA2 = $sys->table('academic_years')->insertGetId(['school_id' => $schoolAId, 'name' => 'Año 2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'status' => 'ACTIVE']);
        $this->academicYearB1 = $sys->table('academic_years')->insertGetId(['school_id' => $schoolBId, 'name' => 'Año 2026 B', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);

        // Grades
        $levelA = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolAId, 'name' => 'Secundaria', 'code' => 'SEC']);
        $levelB = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolBId, 'name' => 'Secundaria B', 'code' => 'SEC_B']);

        $this->gradeA = $sys->table('grades')->insertGetId(['school_id' => $schoolAId, 'educational_level_id' => $levelA, 'name' => 'Once', 'code' => '11']);
        $this->gradeB = $sys->table('grades')->insertGetId(['school_id' => $schoolBId, 'educational_level_id' => $levelB, 'name' => 'Once B', 'code' => '11B']);

        // Courses
        $this->courseA1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA1,
            'grade_id' => $this->gradeA,
            'name' => '11A',
        ]);

        $this->courseA2 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA2,
            'academic_year_id' => $this->academicYearA1,
            'grade_id' => $this->gradeA,
            'name' => '11B_SANISIDRO',
        ]);

        $this->courseB1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolBId,
            'campus_id' => $this->campusB1,
            'academic_year_id' => $this->academicYearB1,
            'grade_id' => $this->gradeB,
            'name' => '11B_JOSE',
        ]);

        // Students
        $this->studentA1 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolAId,
            'student_code' => 'STD-1001',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'document_type' => 'TI',
            'document_number' => '100010001',
        ]);

        $this->studentB1 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolBId,
            'student_code' => 'STD-2001',
            'first_name' => 'María',
            'last_name' => 'Gómez',
            'document_type' => 'TI',
            'document_number' => '200020002',
        ]);
    }

    private function getSanctumTenantToken(string $email, int $schoolId): string
    {
        $user = User::where('email', $email)->first();
        return $user->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;
    }

    /** Test 1: Authorized user can create initial enrollment */
    public function test_authorized_user_can_create_initial_enrollment(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
                'enrollment_number' => 'MAT-2026-001',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Matrícula creada exitosamente.');
        $response->assertJsonPath('data.status', 'ACTIVE');
        $response->assertJsonPath('data.campus_id', $this->campusA1);
        $response->assertJsonPath('data.student_id', $this->studentA1);

        $enrollmentId = $response->json('data.id');

        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollmentId,
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'student_id' => $this->studentA1,
            'academic_year_id' => $this->academicYearA1,
            'grade_id' => $this->gradeA,
            'status' => 'ACTIVE',
        ], 'pgsql_system');

        $this->assertDatabaseHas('course_enrollments', [
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'student_enrollment_id' => $enrollmentId,
            'course_id' => $this->courseA1,
            'status' => 'ACTIVE',
        ], 'pgsql_system');
    }

    /** Test 2: Duplicate active enrollment in the same academic year is rejected (HTTP 422) */
    public function test_duplicate_active_enrollment_same_year_is_rejected(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // First enrollment
        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);
        $res1->assertStatus(201);

        // Second enrollment attempt for same student & year -> Rejected with 422
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);

        $res2->assertStatus(422);
        $this->assertStringContainsString('ya posee una matrícula activa', $res2->json('message'));
    }

    /** Test 3: Can enroll same student in a different academic year */
    public function test_can_enroll_same_student_in_different_academic_years(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Enroll in Year 2026
        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);
        $res1->assertStatus(201);

        // Create course for Year 2027
        $sys = DB::connection('pgsql_system');
        $course2027 = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA2,
            'grade_id' => $this->gradeA,
            'name' => '11A-2027',
        ]);

        // Enroll in Year 2027 -> Succeeds!
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA2,
                'grade_id' => $this->gradeA,
                'course_id' => $course2027,
            ]);
        $res2->assertStatus(201);
    }

    /** Test 4: Cannot enroll with campus belonging to another tenant */
    public function test_cannot_enroll_with_campus_from_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusB1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Sede no válida', $response->json('message'));
    }

    /** Test 5: Cannot enroll with student belonging to another tenant */
    public function test_cannot_enroll_with_student_from_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentB1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Estudiante no válido', $response->json('message'));
    }

    /** Test 6: Cannot enroll with course belonging to another campus */
    public function test_cannot_enroll_with_course_from_another_campus(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Pass campusA1 but courseA2 belongs to campusA2
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA2,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Curso no válido', $response->json('message'));
    }

    /** Test 7: User without permission receives 403 */
    public function test_user_without_permission_receives_403(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);

        $response->assertStatus(403);
    }

    /** Test 8: Historical TRANSFERRED enrollment allows new ACTIVE enrollment (verifies partial index) */
    public function test_historical_transferred_enrollment_allows_new_active_enrollment(): void
    {
        $sys = DB::connection('pgsql_system');

        // Create historical TRANSFERRED student_enrollment for studentA1 in academicYearA1
        $sys->table('student_enrollments')->insert([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA2,
            'student_id' => $this->studentA1,
            'academic_year_id' => $this->academicYearA1,
            'grade_id' => $this->gradeA,
            'enrollment_date' => '2026-01-10',
            'status' => 'TRANSFERRED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // New enrollment for studentA1 in academicYearA1 with status ACTIVE -> Must succeed cleanly!
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', [
                'campus_id' => $this->campusA1,
                'student_id' => $this->studentA1,
                'academic_year_id' => $this->academicYearA1,
                'grade_id' => $this->gradeA,
                'course_id' => $this->courseA1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Matrícula creada exitosamente.');

        // Verify database contains both: 1 TRANSFERRED and 1 ACTIVE
        $this->assertEquals(2, $sys->table('student_enrollments')
            ->where('school_id', $this->schoolA['id'])
            ->where('student_id', $this->studentA1)
            ->where('academic_year_id', $this->academicYearA1)
            ->count());
    }

    /** Test 9: Validation errors when required fields missing */
    public function test_validation_errors_when_required_fields_missing(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/enrollments', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['campus_id', 'student_id', 'academic_year_id', 'grade_id', 'course_id']);
    }
}
