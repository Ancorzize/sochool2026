<?php

namespace Tests\Feature\Phase3;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CourseApiTest extends \Tests\TestCase
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
    private int $gradeA;
    private int $gradeB;
    private int $subjectA;
    private int $subjectB;
    private int $teacherA;
    private int $teacherB;

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
        $sys->statement("TRUNCATE TABLE course_subject_teachers, courses, teachers, subjects, knowledge_areas, grades, educational_levels, academic_years, classrooms, campuses, school_user_roles, school_users, roles, permissions, schools, users RESTART IDENTITY CASCADE;");

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
        $permissions = ['courses.view', 'courses.create', 'courses.update', 'campuses.view'];
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
        $this->campusA1 = $sys->table('campuses')->insertGetId(['school_id' => $schoolAId, 'name' => 'Sede Principal', 'code' => 'MAIN', 'is_main' => true]);
        $this->campusA2 = $sys->table('campuses')->insertGetId(['school_id' => $schoolAId, 'name' => 'Sede San Isidro', 'code' => 'SAN_ISIDRO', 'is_main' => false]);
        $this->campusB1 = $sys->table('campuses')->insertGetId(['school_id' => $schoolBId, 'name' => 'Sede Norte', 'code' => 'NORTE', 'is_main' => true]);

        // Academic Years
        $this->academicYearA = $sys->table('academic_years')->insertGetId(['school_id' => $schoolAId, 'name' => 'Año 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);
        $this->academicYearB = $sys->table('academic_years')->insertGetId(['school_id' => $schoolBId, 'name' => 'Año 2026 B', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);

        // Grades
        $levelA = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolAId, 'name' => 'Secundaria', 'code' => 'SEC']);
        $levelB = $sys->table('educational_levels')->insertGetId(['school_id' => $schoolBId, 'name' => 'Secundaria B', 'code' => 'SEC_B']);

        $this->gradeA = $sys->table('grades')->insertGetId(['school_id' => $schoolAId, 'educational_level_id' => $levelA, 'name' => 'Once', 'code' => '11']);
        $this->gradeB = $sys->table('grades')->insertGetId(['school_id' => $schoolBId, 'educational_level_id' => $levelB, 'name' => 'Once B', 'code' => '11B']);

        // Knowledge Areas & Subjects
        $areaA = $sys->table('knowledge_areas')->insertGetId(['school_id' => $schoolAId, 'name' => 'Matemáticas', 'code' => 'MAT']);
        $areaB = $sys->table('knowledge_areas')->insertGetId(['school_id' => $schoolBId, 'name' => 'Matemáticas B', 'code' => 'MAT_B']);

        $this->subjectA = $sys->table('subjects')->insertGetId(['school_id' => $schoolAId, 'knowledge_area_id' => $areaA, 'name' => 'Álgebra', 'code' => 'ALG']);
        $this->subjectB = $sys->table('subjects')->insertGetId(['school_id' => $schoolBId, 'knowledge_area_id' => $areaB, 'name' => 'Geometría', 'code' => 'GEO']);

        // Teachers
        $teacherUserA = $sys->table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'email' => 'teacher.a@example.com', 'password' => Hash::make('password123'), 'first_name' => 'Profesor', 'last_name' => 'A']);
        $teacherUserB = $sys->table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'email' => 'teacher.b@example.com', 'password' => Hash::make('password123'), 'first_name' => 'Profesor', 'last_name' => 'B']);

        $sys->table('school_users')->insert(['school_id' => $schoolAId, 'user_id' => $teacherUserA, 'status' => 'ACTIVE']);
        $sys->table('school_users')->insert(['school_id' => $schoolBId, 'user_id' => $teacherUserB, 'status' => 'ACTIVE']);

        $this->teacherA = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolAId, 'user_id' => $teacherUserA, 'teacher_code' => 'TCH-001', 'first_name' => 'Profesor', 'last_name' => 'A', 'email' => 'teacher.a@example.com', 'document_type' => 'CC', 'document_number' => '111111'
        ]);

        $this->teacherB = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'school_id' => $schoolBId, 'user_id' => $teacherUserB, 'teacher_code' => 'TCH-002', 'first_name' => 'Profesor', 'last_name' => 'B', 'email' => 'teacher.b@example.com', 'document_type' => 'CC', 'document_number' => '222222'
        ]);
    }

    private function getSanctumTenantToken(string $email, int $schoolId): string
    {
        $user = User::where('email', $email)->first();
        return $user->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;
    }

    /** Test 1 & 9: Authorized user can list courses & JSON structure is consistent */
    public function test_authorized_user_can_list_courses(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('courses')->insert([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA,
            'grade_id' => $this->gradeA,
            'name' => '11A',
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/courses');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'school_id', 'campus_id', 'academic_year_id', 'grade_id', 'name']]]);
        $this->assertCount(1, $response->json('data'));
    }

    /** Test 2 & 8: Authorized user can create course & duplicate names allowed in different campuses */
    public function test_authorized_user_can_create_course_and_duplicate_across_campuses(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Create 11A in Campus 1
        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/courses', [
                'campus_id' => $this->campusA1,
                'academic_year_id' => $this->academicYearA,
                'grade_id' => $this->gradeA,
                'name' => '11A',
            ]);

        $res1->assertStatus(201);
        $res1->assertJsonPath('message', 'Curso creado exitosamente.');
        $course1Id = $res1->json('data.id');

        // Create 11A in Campus 2 -> Must succeed cleanly!
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/courses', [
                'campus_id' => $this->campusA2,
                'academic_year_id' => $this->academicYearA,
                'grade_id' => $this->gradeA,
                'name' => '11A',
            ]);

        $res2->assertStatus(201);
        $course2Id = $res2->json('data.id');

        $this->assertNotEquals($course1Id, $course2Id);
    }

    /** Test 3: Authorized user can assign teacher to course-subject */
    public function test_authorized_user_can_assign_teacher_to_course(): void
    {
        $sys = DB::connection('pgsql_system');
        $courseId = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA,
            'grade_id' => $this->gradeA,
            'name' => '11A',
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/courses/{$courseId}/assign-teacher", [
                'subject_id' => $this->subjectA,
                'teacher_id' => $this->teacherA,
                'hours_per_week' => 4,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Docente asignado al curso exitosamente.');
        $this->assertDatabaseHas('course_subject_teachers', [
            'school_id' => $this->schoolA['id'],
            'course_id' => $courseId,
            'subject_id' => $this->subjectA,
            'teacher_id' => $this->teacherA,
            'hours_per_week' => 4,
        ], 'pgsql_system');
    }

    /** Test 4: User without permission receives 403 */
    public function test_user_without_permission_receives_403(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/courses');

        $response->assertStatus(403);
    }

    /** Test 5: Tenant cannot access courses of another tenant */
    public function test_tenant_cannot_access_courses_of_another_tenant(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('courses')->insert([
            'school_id' => $this->schoolB['id'],
            'campus_id' => $this->campusB1,
            'academic_year_id' => $this->academicYearB,
            'grade_id' => $this->gradeB,
            'name' => '11B_JOSE',
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Query courses as Tenant A -> Must NOT return Tenant B course!
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/courses');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertNotContains('11B_JOSE', $names);
    }

    /** Test 6: Cannot associate a course with a campus of another tenant */
    public function test_cannot_associate_course_with_campus_of_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Tenant A tries to create course using Tenant B campus -> Must be rejected (422)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/courses', [
                'campus_id' => $this->campusB1,
                'academic_year_id' => $this->academicYearA,
                'grade_id' => $this->gradeA,
                'name' => '11A_INVALID',
            ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Sede no válida', $response->json('message'));
    }

    /** Test 7: Cannot assign a teacher belonging to another tenant */
    public function test_cannot_assign_teacher_belonging_to_another_tenant(): void
    {
        $sys = DB::connection('pgsql_system');
        $courseId = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA,
            'grade_id' => $this->gradeA,
            'name' => '11A',
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Tenant A tries to assign Teacher B (from School B) -> Must be rejected (422)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/v1/courses/{$courseId}/assign-teacher", [
                'subject_id' => $this->subjectA,
                'teacher_id' => $this->teacherB,
                'hours_per_week' => 3,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Docente no válido', $response->json('message'));
    }
}
