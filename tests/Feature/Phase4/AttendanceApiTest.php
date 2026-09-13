<?php

namespace Tests\Feature\Phase4;

use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AttendanceApiTest extends \Tests\TestCase
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
    private int $courseA1;
    private int $courseA2;
    private int $courseB1;
    private int $studentA1;
    private int $studentA2;
    private int $studentB1;
    private int $statusPresentA;
    private int $statusAbsentA;
    private int $statusPresentB;

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
        $sys->statement("TRUNCATE TABLE attendances, attendance_statuses, course_enrollments, student_enrollments, students, courses, grades, educational_levels, academic_years, campuses, school_user_roles, school_users, roles, permissions, schools, users RESTART IDENTITY CASCADE;");

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
        $permissions = ['attendance.view', 'attendance.create', 'campuses.view', 'courses.view'];
        foreach ($permissions as $pName) {
            $sys->table('permissions')->updateOrInsert(
                ['name' => $pName],
                ['guard_name' => 'web', 'module' => 'attendance', 'description' => $pName]
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

        // Attendance Statuses
        $this->statusPresentA = $sys->table('attendance_statuses')->insertGetId([
            'school_id' => $schoolAId,
            'code' => 'PRESENT',
            'name' => 'Presente',
            'is_absence' => false,
            'color' => '#10B981',
            'status_order' => 1,
            'is_active' => true,
        ]);

        $this->statusAbsentA = $sys->table('attendance_statuses')->insertGetId([
            'school_id' => $schoolAId,
            'code' => 'ABSENT',
            'name' => 'Ausente',
            'is_absence' => true,
            'color' => '#EF4444',
            'status_order' => 2,
            'is_active' => true,
        ]);

        $this->statusPresentB = $sys->table('attendance_statuses')->insertGetId([
            'school_id' => $schoolBId,
            'code' => 'PRESENT_B',
            'name' => 'Presente B',
            'is_absence' => false,
            'color' => '#10B981',
            'status_order' => 1,
            'is_active' => true,
        ]);

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

        $this->studentA2 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolAId,
            'student_code' => 'STD-1002',
            'first_name' => 'Carlos',
            'last_name' => 'López',
            'document_type' => 'TI',
            'document_number' => '100010002',
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

        // Enroll Student A1 & A2 in Course A1 (Campus A1)
        $seA1 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'student_id' => $this->studentA1, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'enrollment_date' => '2026-01-15', 'status' => 'ACTIVE'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'student_enrollment_id' => $seA1, 'course_id' => $this->courseA1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);

        $seA2 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'student_id' => $this->studentA2, 'academic_year_id' => $this->academicYearA, 'grade_id' => $this->gradeA, 'enrollment_date' => '2026-01-15', 'status' => 'ACTIVE'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolAId, 'campus_id' => $this->campusA1, 'student_enrollment_id' => $seA2, 'course_id' => $this->courseA1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);

        // Enroll Student B1 in Course B1 (School B)
        $seB1 = $sys->table('student_enrollments')->insertGetId([
            'school_id' => $schoolBId, 'campus_id' => $this->campusB1, 'student_id' => $this->studentB1, 'academic_year_id' => $this->academicYearB, 'grade_id' => $this->gradeB, 'enrollment_date' => '2026-01-15', 'status' => 'ACTIVE'
        ]);
        $sys->table('course_enrollments')->insert([
            'school_id' => $schoolBId, 'campus_id' => $this->campusB1, 'student_enrollment_id' => $seB1, 'course_id' => $this->courseB1, 'enrolled_at' => now(), 'status' => 'ACTIVE'
        ]);
    }

    private function getSanctumTenantToken(string $email, int $schoolId): string
    {
        $user = User::where('email', $email)->first();
        return $user->createToken('tenant_token', ["tenant:{$schoolId}"])->plainTextToken;
    }

    /** 1. Authorized user can query attendance */
    public function test_authorized_user_can_query_attendance(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance?course_id=' . $this->courseA1);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'school_id', 'course_id', 'student_id', 'attendance_status_id', 'date']]]);
        $this->assertCount(1, $response->json('data'));
    }

    /** 2. Authorized user can record batch attendance */
    public function test_authorized_user_can_record_batch_attendance(): void
    {
        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    [
                        'student_id' => $this->studentA1,
                        'attendance_status_id' => $this->statusPresentA,
                        'remarks' => 'Present on time',
                    ],
                    [
                        'student_id' => $this->studentA2,
                        'attendance_status_id' => $this->statusAbsentA,
                        'is_justified' => false,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Asistencia registrada exitosamente.');
        $this->assertCount(2, $response->json('data'));

        $this->assertDatabaseHas('attendances', [
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
        ], 'pgsql_system');

        $this->assertDatabaseHas('attendances', [
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA2,
            'attendance_status_id' => $this->statusAbsentA,
            'date' => '2026-02-01',
        ], 'pgsql_system');
    }

    /** 3. User without attendance.view receives 403 when querying */
    public function test_user_without_attendance_view_permission_receives_403(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance');

        $response->assertStatus(403);
    }

    /** 4. User without attendance.create receives 403 when recording */
    public function test_user_without_attendance_create_permission_receives_403(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentA1, 'attendance_status_id' => $this->statusPresentA]
                ],
            ]);

        $response->assertStatus(403);
    }

    /** 5. Course from another tenant cannot be used */
    public function test_cannot_use_course_from_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseB1, // Belongs to School B
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentA1, 'attendance_status_id' => $this->statusPresentA]
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Curso no encontrado', $response->json('message'));
    }

    /** 6. Student from another tenant cannot be used */
    public function test_cannot_use_student_from_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentB1, 'attendance_status_id' => $this->statusPresentA] // Student B1 belongs to School B
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no son válidos', $response->json('message'));
    }

    /** 7. Attendance status from another tenant cannot be used */
    public function test_cannot_use_attendance_status_from_another_tenant(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentA1, 'attendance_status_id' => $this->statusPresentB] // Status Present B belongs to School B
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('estados de asistencia no son válidos', $response->json('message'));
    }

    /** 8. Student not enrolled in course cannot receive attendance */
    public function test_student_not_enrolled_in_course_cannot_receive_attendance(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Create student A3 in School A, but DO NOT enroll student A3 in course A1
        $sys = DB::connection('pgsql_system');
        $studentA3 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA['id'],
            'student_code' => 'STD-1003',
            'first_name' => 'Pedro',
            'last_name' => 'Ramírez',
            'document_type' => 'TI',
            'document_number' => '100010003',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $studentA3, 'attendance_status_id' => $this->statusPresentA]
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no posee una matrícula activa', $response->json('message'));
    }

    /** 9. Batch operation is atomic on error */
    public function test_batch_operation_is_atomic_on_error(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Student A3 not enrolled
        $sys = DB::connection('pgsql_system');
        $studentA3 = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA['id'],
            'student_code' => 'STD-1004',
            'first_name' => 'Luis',
            'last_name' => 'Torres',
            'document_type' => 'TI',
            'document_number' => '100010004',
        ]);

        // Submit batch with 1 valid student (studentA1) and 1 invalid student (studentA3)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA1,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentA1, 'attendance_status_id' => $this->statusPresentA],
                    ['student_id' => $studentA3, 'attendance_status_id' => $this->statusPresentA],
                ],
            ]);

        $response->assertStatus(422);

        // Confirm database contains 0 attendance records for studentA1 on that date
        $this->assertDatabaseMissing('attendances', [
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'date' => '2026-02-01',
        ], 'pgsql_system');
    }

    /** 10. Tenant context prevents escaping via school_id manipulation */
    public function test_tenant_context_prevents_escaping_via_school_id(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Even if someone attempts to pass a different school_id in body (ignored by Controller/Service)
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/attendance?school_id=' . $this->schoolB['id']);

        $response->assertStatus(200);

        // All returned items must belong to School A only
        foreach ($response->json('data') as $item) {
            $this->assertEquals($this->schoolA['id'], $item['school_id']);
        }
    }

    /** 11. Campus context behavior */
    public function test_campus_context_behavior(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Authenticated in Campus A1 context, try to record attendance for Course A2 (belongs to Campus A2) -> Rejected 422
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->postJson('/api/v1/attendance', [
                'course_id' => $this->courseA2,
                'date' => '2026-02-01',
                'records' => [
                    ['student_id' => $this->studentA1, 'attendance_status_id' => $this->statusPresentA]
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no pertenece a la sede activa', $response->json('message'));
    }

    // ==========================================
    // BLOCK A2 TESTS: ATTENDANCE UPDATE / CORRECTION
    // ==========================================

    /** 12. Authorized user can update attendance status, justification, and remarks */
    public function test_authorized_user_can_update_attendance_status_justification_and_remarks(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'is_justified' => false,
            'remarks' => 'Inicial',
            'recorded_by_user_id' => $this->userAdminA['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/attendance/{$attId}", [
                'attendance_status_id' => $this->statusAbsentA,
                'is_justified' => true,
                'remarks' => 'Justificado por excusa médica',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Registro de asistencia actualizado exitosamente.');
        $response->assertJsonPath('data.attendance_status_id', $this->statusAbsentA);
        $response->assertJsonPath('data.is_justified', true);
        $response->assertJsonPath('data.remarks', 'Justificado por excusa médica');

        $this->assertDatabaseHas('attendances', [
            'id' => $attId,
            'school_id' => $this->schoolA['id'],
            'attendance_status_id' => $this->statusAbsentA,
            'is_justified' => true,
            'remarks' => 'Justificado por excusa médica',
            'recorded_by_user_id' => $this->userAdminA['id'],
        ], 'pgsql_system');
    }

    /** 13. User without attendance.create permission receives 403 on update */
    public function test_user_without_attendance_create_permission_receives_403_on_update(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/attendance/{$attId}", [
                'attendance_status_id' => $this->statusAbsentA,
            ]);

        $response->assertStatus(403);
    }

    /** 14. Cannot update attendance belonging to another tenant */
    public function test_cannot_update_attendance_belonging_to_another_tenant(): void
    {
        $sys = DB::connection('pgsql_system');
        $attIdB = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolB['id'],
            'course_id' => $this->courseB1,
            'student_id' => $this->studentB1,
            'attendance_status_id' => $this->statusPresentB,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt update using School A token
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/v1/attendance/{$attIdB}", [
                'attendance_status_id' => $this->statusAbsentA,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no encontrado o no pertenece', $response->json('message'));
    }

    /** 15. Cannot update attendance with status from another tenant */
    public function test_cannot_update_attendance_with_status_from_another_tenant(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/v1/attendance/{$attId}", [
                'attendance_status_id' => $this->statusPresentB, // Status Present B belongs to School B
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('estado de asistencia especificado no es válido', $response->json('message'));
    }

    /** 16. Cannot update attendance outside active campus scope */
    public function test_cannot_update_attendance_outside_active_campus_scope(): void
    {
        $sys = DB::connection('pgsql_system');
        $attIdA2 = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA2, // Course A2 belongs to Campus A2
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Authenticated with X-Campus-ID set to Campus A1
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->putJson("/api/v1/attendance/{$attIdA2}", [
                'attendance_status_id' => $this->statusAbsentA,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no pertenece a la sede activa', $response->json('message'));
    }

    /** 17. Client cannot spoof recorded_by_user_id */
    public function test_client_cannot_spoof_recorded_by_user_id(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'recorded_by_user_id' => $this->userAdminA['id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/v1/attendance/{$attId}", [
                'attendance_status_id' => $this->statusAbsentA,
                'recorded_by_user_id' => 99999, // Attempted spoof
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'id' => $attId,
            'school_id' => $this->schoolA['id'],
            'recorded_by_user_id' => $this->userAdminA['id'], // Preserved authenticated user ID
        ], 'pgsql_system');

        $this->assertDatabaseMissing('attendances', [
            'id' => $attId,
            'recorded_by_user_id' => 99999,
        ], 'pgsql_system');
    }

    /** 18. Update attendance validates field constraints */
    public function test_update_attendance_validates_field_constraints(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/v1/attendance/{$attId}", [
                'remarks' => str_repeat('a', 501), // Exceeds max:500
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['remarks']);
    }

    /** 19. Partial update preserves unmodified fields */
    public function test_partial_update_preserves_unmodified_fields(): void
    {
        $sys = DB::connection('pgsql_system');
        $attId = $sys->table('attendances')->insertGetId([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusAbsentA,
            'date' => '2026-02-01',
            'is_justified' => false,
            'remarks' => 'Observación original',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Update ONLY is_justified
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->patchJson("/api/v1/attendance/{$attId}", [
                'is_justified' => true,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('attendances', [
            'id' => $attId,
            'attendance_status_id' => $this->statusAbsentA, // Unchanged
            'is_justified' => true, // Updated
            'remarks' => 'Observación original', // Unchanged
        ], 'pgsql_system');
    }

    /** 20. Update returns 422 when attendance id does not exist */
    public function test_update_returns_422_when_attendance_id_does_not_exist(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson('/api/v1/attendance/999999', [
                'attendance_status_id' => $this->statusPresentA,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('no encontrado o no pertenece', $response->json('message'));
    }

    // ==========================================
    // BLOCK A3 TESTS: STUDENT ATTENDANCE HISTORY
    // ==========================================

    /** 21. Authorized user can query student attendance history */
    public function test_authorized_user_can_query_student_attendance_history(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusAbsentA,
                'date' => '2026-02-02',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'school_id', 'course_id', 'student_id', 'attendance_status_id', 'date']]]);
        $this->assertCount(2, $response->json('data'));
    }

    /** 22. Student attendance history returns only requested student records */
    public function test_student_attendance_history_returns_only_requested_student_records(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA2,
                'attendance_status_id' => $this->statusAbsentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $token = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->studentA1, $response->json('data.0.student_id'));
    }

    /** 23. Student belonging to another tenant cannot be queried */
    public function test_student_belonging_to_another_tenant_cannot_be_queried(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentB1}/attendance"); // Student B1 belongs to School B

        $response->assertStatus(422);
        $this->assertStringContainsString('no encontrado o no pertenece', $response->json('message'));
    }

    /** 24. User without attendance.view permission receives 403 on student history */
    public function test_user_without_attendance_view_permission_receives_403_on_student_history(): void
    {
        $token = $this->getSanctumTenantToken($this->userNoPerms['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance");

        $response->assertStatus(403);
    }

    /** 25. Client cannot escape tenant via school_id parameter on student history */
    public function test_student_history_school_id_param_does_not_allow_tenant_escape(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            'school_id' => $this->schoolA['id'],
            'course_id' => $this->courseA1,
            'student_id' => $this->studentA1,
            'attendance_status_id' => $this->statusPresentA,
            'date' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance?school_id={$this->schoolB['id']}");

        $response->assertStatus(200);
        foreach ($response->json('data') as $item) {
            $this->assertEquals($this->schoolA['id'], $item['school_id']);
        }
    }

    /** 26. Student history respects active campus context */
    public function test_student_history_respects_active_campus_context(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1, // Campus A1
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA2, // Campus A2
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-02',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        // Query with X-Campus-ID set to Campus A1
        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Campus-ID', (string) $this->campusA1)
            ->getJson("/api/v1/students/{$this->studentA1}/attendance");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->courseA1, $response->json('data.0.course_id'));
    }

    /** 27. Date filters work correctly on student history */
    public function test_student_history_date_filters_work_correctly(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusAbsentA,
                'date' => '2026-02-15',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance?from_date=2026-02-10&to_date=2026-02-20");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('2026-02-15', $response->json('data.0.date'));
    }

    /** 28. Course, subject, and status filters work correctly on student history */
    public function test_student_history_course_subject_status_filters_work_correctly(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusAbsentA,
                'date' => '2026-02-02',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance?attendance_status_id={$this->statusAbsentA}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->statusAbsentA, $response->json('data.0.attendance_status_id'));
    }

    /** 29. Student with no attendance records returns 200 with empty array */
    public function test_student_with_no_attendance_records_returns_200_with_empty_array(): void
    {
        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentA2}/attendance"); // Student A2 has no attendance

        $response->assertStatus(200);
        $response->assertJson(['data' => []]);
    }

    /** 30. Student history has deterministic ordering (date desc, id asc) */
    public function test_student_history_has_deterministic_ordering(): void
    {
        $sys = DB::connection('pgsql_system');
        $sys->table('attendances')->insert([
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'school_id' => $this->schoolA['id'],
                'course_id' => $this->courseA1,
                'student_id' => $this->studentA1,
                'attendance_status_id' => $this->statusPresentA,
                'date' => '2026-02-05',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $tokenA = $this->getSanctumTenantToken($this->userAdminA['email'], $this->schoolA['id']);

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/v1/students/{$this->studentA1}/attendance");

        $response->assertStatus(200);
        $dates = collect($response->json('data'))->pluck('date')->map(fn($d) => substr($d, 0, 10))->toArray();
        $this->assertEquals(['2026-02-10', '2026-02-05', '2026-02-01'], $dates);
    }
}
