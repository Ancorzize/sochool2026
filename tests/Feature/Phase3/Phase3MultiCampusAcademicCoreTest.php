<?php

namespace Tests\Feature\Phase3;

use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\CampusContext;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase3MultiCampusAcademicCoreTest extends \Tests\TestCase
{
    private array $schoolA;
    private array $schoolB;
    private array $userAdminA;
    private array $userAdminB;
    private array $userTeacherA;

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
        $sys->statement("TRUNCATE TABLE student_transfers, schedules, classrooms, campuses, course_enrollments, student_enrollments, course_subject_teachers, courses, teachers, students, academic_years, schools, users RESTART IDENTITY CASCADE;");

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

        $teacherAId = $sys->table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'email' => 'teacher.angel@example.com',
            'password' => Hash::make('password123'),
            'first_name' => 'Angel',
            'last_name' => 'Docente',
        ]);

        $this->userAdminA = ['id' => $adminAId, 'email' => 'admin.santa@example.com'];
        $this->userAdminB = ['id' => $adminBId, 'email' => 'admin.jose@example.com'];
        $this->userTeacherA = ['id' => $teacherAId, 'email' => 'teacher.angel@example.com'];

        // Assign School Memberships
        $suA = $sys->table('school_users')->insertGetId([
            'school_id' => $schoolAId, 'user_id' => $adminAId, 'status' => 'ACTIVE'
        ]);

        $suB = $sys->table('school_users')->insertGetId([
            'school_id' => $schoolBId, 'user_id' => $adminBId, 'status' => 'ACTIVE'
        ]);

        $sys->table('school_users')->insert([
            'school_id' => $schoolAId, 'user_id' => $teacherAId, 'status' => 'ACTIVE'
        ]);

        // Ensure Phase 3 permissions exist in permissions table
        $permissions = [
            'campuses.view', 'campuses.create', 'campuses.update',
            'classrooms.view', 'classrooms.create',
            'academic_years.view', 'academic_years.create', 'academic_years.update',
            'schedules.view', 'schedules.create',
            'enrollments.update',
        ];

        foreach ($permissions as $pName) {
            $sys->table('permissions')->updateOrInsert(
                ['name' => $pName],
                ['guard_name' => 'web', 'module' => 'academic', 'description' => $pName]
            );
        }

        $allPermIds = $sys->table('permissions')->whereIn('name', $permissions)->pluck('id');

        // Create Roles & Assign Permissions
        $roleAdminA = $sys->table('roles')->where('school_id', $schoolAId)->where('name', 'SCHOOL_ADMIN')->first();
        if (!$roleAdminA) {
            $roleAdminAId = $sys->table('roles')->insertGetId([
                'school_id' => $schoolAId, 'name' => 'SCHOOL_ADMIN', 'guard_name' => 'web', 'is_system' => true
            ]);
        } else {
            $roleAdminAId = $roleAdminA->id;
        }

        $roleAdminB = $sys->table('roles')->where('school_id', $schoolBId)->where('name', 'SCHOOL_ADMIN')->first();
        if (!$roleAdminB) {
            $roleAdminBId = $sys->table('roles')->insertGetId([
                'school_id' => $schoolBId, 'name' => 'SCHOOL_ADMIN', 'guard_name' => 'web', 'is_system' => true
            ]);
        } else {
            $roleAdminBId = $roleAdminB->id;
        }

        $sys->table('school_user_roles')->insertOrIgnore([
            ['school_id' => $schoolAId, 'school_user_id' => $suA, 'role_id' => $roleAdminAId],
            ['school_id' => $schoolBId, 'school_user_id' => $suB, 'role_id' => $roleAdminBId],
        ]);

        foreach ($allPermIds as $pId) {
            $sys->table('role_permissions')->insertOrIgnore([
                ['role_id' => $roleAdminAId, 'permission_id' => $pId],
                ['role_id' => $roleAdminBId, 'permission_id' => $pId],
            ]);
        }
    }

    private function getSanctumTenantToken(string $email, int $schoolId): string
    {
        $user = User::where('email', $email)->first();
        return $user->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;
    }

    /** Test Area A & B: Multi-Campus & Cross-School Isolation */
    public function test_multi_campus_and_cross_school_isolation(): void
    {
        $sys = DB::connection('pgsql_system');

        // Create Campus for School A & School B
        $campusA1 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        $campusB1 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolB['id'],
            'name' => 'Sede Norte',
            'code' => 'NORTE',
            'is_main' => true,
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Query campuses as School A Admin
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/campuses');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Sede Principal', $data[0]['name']);
    }

    /** Test Area D: Main Campus Uniqueness Constraint */
    public function test_main_campus_uniqueness_constraint(): void
    {
        $sys = DB::connection('pgsql_system');

        $sys->table('campuses')->insert([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        // Attempt to insert another main campus for School A -> DB unique index must reject
        $this->expectException(\Illuminate\Database\QueryException::class);

        $sys->table('campuses')->insert([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede San Isidro',
            'code' => 'SAN_ISIDRO',
            'is_main' => true,
        ]);
    }

    /** Test Area F: Course Duplicate Codes Across Campuses */
    public function test_course_duplicate_codes_across_campuses(): void
    {
        $sys = DB::connection('pgsql_system');

        $campus1 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        $campus2 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede San Isidro',
            'code' => 'SAN_ISIDRO',
            'is_main' => false,
        ]);

        $yearId = $sys->table('academic_years')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Año 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ACTIVE',
        ]);

        $levelId = $sys->table('educational_levels')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Primaria',
            'code' => 'PRIM',
        ]);

        $gradeId = $sys->table('grades')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'educational_level_id' => $levelId,
            'name' => 'Quinto',
            'code' => '5',
        ]);

        // Create 5A in Sede Principal
        $c1 = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '5A',
        ]);

        // Create 5A in Sede San Isidro -> Must succeed cleanly!
        $c2 = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus2,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '5A',
        ]);

        $this->assertNotNull($c1);
        $this->assertNotNull($c2);
        $this->assertNotEquals($c1, $c2);
    }

    /** Test Area G & H: Student Course & Campus Transfers */
    public function test_student_course_and_campus_transfers(): void
    {
        $sys = DB::connection('pgsql_system');

        $campus1 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        $campus2 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede San Isidro',
            'code' => 'SAN_ISIDRO',
            'is_main' => false,
        ]);

        $yearId = $sys->table('academic_years')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Año 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ACTIVE',
        ]);

        $levelId = $sys->table('educational_levels')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Secundaria',
            'code' => 'SEC',
        ]);

        $gradeId = $sys->table('grades')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'educational_level_id' => $levelId,
            'name' => 'Once',
            'code' => '11',
        ]);

        $course11A = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '11A',
        ]);

        $course11B = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '11B',
        ]);

        $courseSanIsidro = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus2,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '11A',
        ]);

        $studentId = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA['id'],
            'student_code' => 'STU-12345',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'document_type' => 'CC',
            'document_number' => '123456789',
        ]);

        $seId = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'enrollment_date' => '2026-01-15',
            'status' => 'ACTIVE',
        ]);

        $ceId = $sys->table('course_enrollments')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'student_enrollment_id' => $seId,
            'course_id' => $course11A,
            'enrolled_at' => '2026-01-15 08:00:00',
            'status' => 'ACTIVE',
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // 1. Perform Course Transfer (11A -> 11B)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/transfers/course', [
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
                'from_course_id' => $course11A,
                'to_course_id' => $course11B,
                'reason' => 'Cambio de grupo solicitado',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('student_transfers', [
            'school_id' => $this->schoolA['id'],
            'student_id' => $studentId,
            'transfer_type' => 'COURSE_TRANSFER',
            'from_course_id' => $course11A,
            'to_course_id' => $course11B,
        ], 'pgsql_system');

        // Old enrollment TRANSFERRED, new enrollment ACTIVE
        $this->assertDatabaseHas('course_enrollments', ['id' => $ceId, 'status' => 'TRANSFERRED'], 'pgsql_system');
        $this->assertDatabaseHas('course_enrollments', ['course_id' => $course11B, 'status' => 'ACTIVE'], 'pgsql_system');

        // 2. Perform Campus Transfer (Principal -> San Isidro)
        $response2 = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/transfers/campus', [
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
                'from_campus_id' => $campus1,
                'to_campus_id' => $campus2,
                'to_course_id' => $courseSanIsidro,
                'reason' => 'Cambio de residencia a San Isidro',
            ]);

        $response2->assertStatus(201);
        $this->assertDatabaseHas('student_transfers', [
            'school_id' => $this->schoolA['id'],
            'student_id' => $studentId,
            'transfer_type' => 'CAMPUS_TRANSFER',
            'from_campus_id' => $campus1,
            'to_campus_id' => $campus2,
        ], 'pgsql_system');
    }

    /** Test Area O, P, Q, R: Timetable Conflict Engine */
    public function test_timetable_conflict_engine(): void
    {
        $sys = DB::connection('pgsql_system');

        $campus1 = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Sede Principal',
            'code' => 'MAIN',
            'is_main' => true,
        ]);

        $yearId = $sys->table('academic_years')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Año 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ACTIVE',
        ]);

        $levelId = $sys->table('educational_levels')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Primaria',
            'code' => 'PRIM',
        ]);

        $gradeId = $sys->table('grades')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'educational_level_id' => $levelId,
            'name' => 'Cuarto',
            'code' => '4',
        ]);

        $course1 = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '4A',
        ]);

        $course2 = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => '4B',
        ]);

        $areaId = $sys->table('knowledge_areas')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'name' => 'Matemáticas',
            'code' => 'MAT',
        ]);

        $subjectId = $sys->table('subjects')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'knowledge_area_id' => $areaId,
            'name' => 'Matemáticas',
            'code' => 'MAT',
        ]);

        $teacherId = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA['id'],
            'user_id' => $this->userTeacherA['id'],
            'teacher_code' => 'TCH-98765',
            'first_name' => 'Angel',
            'last_name' => 'Docente',
            'email' => 'teacher.angel@example.com',
            'document_type' => 'CC',
            'document_number' => '987654321',
        ]);

        $classroomId = $sys->table('classrooms')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $campus1,
            'name' => 'Aula 101',
            'code' => '101',
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // 1. Create valid schedule for Course 4A (Monday 07:00 - 08:00)
        $response1 = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/schedules', [
                'campus_id' => $campus1,
                'academic_year_id' => $yearId,
                'course_id' => $course1,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'classroom_id' => $classroomId,
                'day_of_week' => 1,
                'start_time' => '07:00',
                'end_time' => '08:00',
                'valid_from' => '2026-01-01',
            ]);

        $response1->assertStatus(201);

        // 2. Create conflicting schedule for SAME teacher in Course 4B at same time -> Must return 422
        $response2 = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/schedules', [
                'campus_id' => $campus1,
                'academic_year_id' => $yearId,
                'course_id' => $course2,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'classroom_id' => null,
                'day_of_week' => 1,
                'start_time' => '07:30',
                'end_time' => '08:30',
                'valid_from' => '2026-01-01',
            ]);

        $response2->assertStatus(422);
        $this->assertStringContainsString('Docente no disponible', $response2->json('message'));
    }

    /** Test Area U & V: Campus Scope Bypass & Cross-Tenant Manipulation Attempts */
    public function test_campus_scope_bypass_and_cross_tenant_manipulation(): void
    {
        $sys = DB::connection('pgsql_system');

        $campusB = $sys->table('campuses')->insertGetId([
            'school_id' => $this->schoolB['id'],
            'name' => 'Sede San José Norte',
            'code' => 'SJ_NORTE',
            'is_main' => true,
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // User Admin A passes X-Campus-ID belonging to School B -> Must return 403
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Campus-ID', (string) $campusB)
            ->getJson('/api/v1/classrooms');

        $response->assertStatus(403);
        $this->assertStringContainsString('Sede no válida', $response->json('message'));
    }

    /** Test Area W: Reused PDO Connection Cleanup */
    public function test_reused_pdo_connection_cleanup(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/campuses');

        $response->assertStatus(200);

        // Verify context is purged after request terminates
        $this->assertNull(TenantContext::id());
        $this->assertNull(CampusContext::id());
    }
}
