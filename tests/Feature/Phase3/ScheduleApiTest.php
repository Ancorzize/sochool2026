<?php

namespace Tests\Feature\Phase3;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ScheduleApiTest extends \Tests\TestCase
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
    private int $courseA1;
    private int $courseA2;
    private int $courseB1;
    private int $scheduleA1;
    private int $scheduleA2;
    private int $scheduleB1;

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
        $sys->statement("TRUNCATE TABLE schedules, student_transfers, course_enrollments, student_enrollments, students, course_subject_teachers, courses, teachers, subjects, knowledge_areas, grades, educational_levels, academic_years, classrooms, campuses, school_user_roles, school_users, roles, permissions, schools, users RESTART IDENTITY CASCADE;");

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
        $permissions = ['schedules.view', 'schedules.create', 'schedules.delete', 'campuses.view'];
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
        $this->academicYearA = $sys->table('academic_years')->insertGetId(['school_id' => $schoolAId, 'name' => 'Año 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);
        $this->academicYearB = $sys->table('academic_years')->insertGetId(['school_id' => $schoolBId, 'name' => 'Año 2026 B', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'ACTIVE']);

        // Educational Levels & Grades
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

        // Courses
        $this->courseA1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA,
            'grade_id' => $this->gradeA,
            'name' => '11A',
        ]);

        $this->courseA2 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA2,
            'academic_year_id' => $this->academicYearA,
            'grade_id' => $this->gradeA,
            'name' => '11B_SANISIDRO',
        ]);

        $this->courseB1 = $sys->table('courses')->insertGetId([
            'school_id' => $schoolBId,
            'campus_id' => $this->campusB1,
            'academic_year_id' => $this->academicYearB,
            'grade_id' => $this->gradeB,
            'name' => '11B_JOSE',
        ]);

        // Schedules
        $this->scheduleA1 = $sys->table('schedules')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA1,
            'academic_year_id' => $this->academicYearA,
            'course_id' => $this->courseA1,
            'subject_id' => $this->subjectA,
            'teacher_id' => $this->teacherA,
            'day_of_week' => 1,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'valid_from' => '2026-01-15',
            'status' => 'ACTIVE',
        ]);

        $this->scheduleA2 = $sys->table('schedules')->insertGetId([
            'school_id' => $schoolAId,
            'campus_id' => $this->campusA2,
            'academic_year_id' => $this->academicYearA,
            'course_id' => $this->courseA2,
            'subject_id' => $this->subjectA,
            'teacher_id' => $this->teacherA,
            'day_of_week' => 2,
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'valid_from' => '2026-01-15',
            'status' => 'ACTIVE',
        ]);

        $this->scheduleB1 = $sys->table('schedules')->insertGetId([
            'school_id' => $schoolBId,
            'campus_id' => $this->campusB1,
            'academic_year_id' => $this->academicYearB,
            'course_id' => $this->courseB1,
            'subject_id' => $this->subjectB,
            'teacher_id' => $this->teacherB,
            'day_of_week' => 1,
            'start_time' => '07:00:00',
            'end_time' => '08:30:00',
            'valid_from' => '2026-01-15',
            'status' => 'ACTIVE',
        ]);
    }

    private function getSanctumTenantToken(string $email, int $schoolId): string
    {
        $user = User::where('email', $email)->first();
        return $user->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;
    }

    /** Test 1: Authorized user can deactivate/delete schedule */
    public function test_authorized_user_can_delete_schedule(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->deleteJson("/api/v1/schedules/{$this->scheduleA1}");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Horario desactivado exitosamente.');
        $response->assertJsonPath('data.status', 'INACTIVE');

        // Confirm database still contains historical record with status INACTIVE
        $this->assertDatabaseHas('schedules', [
            'id' => $this->scheduleA1,
            'school_id' => $this->schoolA['id'],
            'status' => 'INACTIVE',
        ], 'pgsql_system');

        // Confirm schedule is no longer returned in active list
        $listResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->getJson('/api/v1/schedules');

        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id');
        $this->assertNotContains($this->scheduleA1, $ids);
    }

    /** Test 2: User without permission receives 403 */
    public function test_user_without_permission_receives_403(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/schedules/{$this->scheduleA1}");

        $response->assertStatus(403);
    }

    /** Test 3: Cannot delete schedule belonging to another tenant */
    public function test_cannot_delete_schedule_belonging_to_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // User from School A attempts to delete Schedule from School B -> Rejected (422)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->deleteJson("/api/v1/schedules/{$this->scheduleB1}");

        $response->assertStatus(422);
        $this->assertStringContainsString('Horario no encontrado', $response->json('message'));
    }

    /** Test 4: Cannot delete schedule belonging to another campus when campus scoped */
    public function test_cannot_delete_schedule_belonging_to_another_campus(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Authenticated with Campus A1 context, try to delete Schedule from Campus A2 -> Rejected (422)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->deleteJson("/api/v1/schedules/{$this->scheduleA2}");

        $response->assertStatus(422);
        $this->assertStringContainsString('Horario no encontrado', $response->json('message'));
    }

    /** Test 5: Soft deactivation preserves historical record in database */
    public function test_soft_deactivation_preserves_historical_record(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/schedules/{$this->scheduleA1}");

        $response->assertStatus(200);

        // Verify physical row count in database is unchanged (record was not hard deleted)
        $sys = DB::connection('pgsql_system');
        $record = $sys->table('schedules')->where('id', $this->scheduleA1)->first();

        $this->assertNotNull($record);
        $this->assertEquals('INACTIVE', $record->status);
    }
}
