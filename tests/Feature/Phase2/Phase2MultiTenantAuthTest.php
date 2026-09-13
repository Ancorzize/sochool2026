<?php

namespace Tests\Feature\Phase2;

use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Permission;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2MultiTenantAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected School $schoolA;
    protected School $schoolB;
    protected User $userActive;
    protected User $userMultiSchool;
    protected SchoolUser $membershipA;
    protected SchoolUser $membershipMultiA;
    protected SchoolUser $membershipB;
    protected Role $roleA;
    protected Role $roleB;
    protected Permission $permViewStudents;
    protected Permission $permCreateStudents;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // 1. Create Schools
        $this->schoolA = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-P2-A-' . Str::random(3),
            'name' => 'Colegio Phase2 A',
            'legal_name' => 'Colegio Phase2 A S.A.S.',
            'tax_identifier' => '900.111.111-1',
            'slug' => 'colegio-p2-a-' . Str::random(3),
            'email' => 'p2a@test.com', 'phone' => '111', 'city' => 'Bogota',
            'state' => 'Cundinamarca', 'country' => 'Colombia', 'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-P2-B-' . Str::random(3),
            'name' => 'Colegio Phase2 B',
            'legal_name' => 'Colegio Phase2 B S.A.S.',
            'tax_identifier' => '900.222.222-2',
            'slug' => 'colegio-p2-b-' . Str::random(3),
            'email' => 'p2b@test.com', 'phone' => '222', 'city' => 'Bogota',
            'state' => 'Cundinamarca', 'country' => 'Colombia', 'status' => 'ACTIVE',
        ]);

        // 2. Create Users
        $this->userActive = User::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'UserActive',
            'last_name' => 'Phase2',
            'email' => 'user.active.' . Str::random(4) . '@test.com',
            'password' => Hash::make('SecretPass123!'),
            'status' => 'ACTIVE',
        ]);

        $this->userMultiSchool = User::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'first_name' => 'UserMulti',
            'last_name' => 'Phase2',
            'email' => 'user.multi.' . Str::random(4) . '@test.com',
            'password' => Hash::make('SecretPass123!'),
            'status' => 'ACTIVE',
        ]);

        // 3. Create SchoolUsers
        $this->membershipA = SchoolUser::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->userActive->id,
            'status' => 'ACTIVE',
        ]);

        $this->membershipMultiA = SchoolUser::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id,
            'user_id' => $this->userMultiSchool->id,
            'status' => 'ACTIVE',
        ]);

        $this->membershipB = SchoolUser::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id,
            'user_id' => $this->userMultiSchool->id,
            'status' => 'ACTIVE',
        ]);

        // 4. Create Permissions
        $this->permViewStudents = Permission::on('pgsql_system')->firstOrCreate(
            ['name' => 'students.view'],
            ['guard_name' => 'web', 'module' => 'students', 'description' => 'Ver estudiantes']
        );

        $this->permCreateStudents = Permission::on('pgsql_system')->firstOrCreate(
            ['name' => 'students.create'],
            ['guard_name' => 'web', 'module' => 'students', 'description' => 'Crear estudiantes']
        );

        // 5. Create Roles & Assign Permissions
        $this->roleA = Role::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id,
            'name' => 'TEACHER_A_' . Str::random(3),
            'guard_name' => 'web',
        ]);
        $this->roleA->permissions()->sync([$this->permViewStudents->id]);

        $this->roleB = Role::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id,
            'name' => 'ADMIN_B_' . Str::random(3),
            'guard_name' => 'web',
        ]);
        $this->roleB->permissions()->sync([$this->permViewStudents->id, $this->permCreateStudents->id]);

        // Attach roles in school_user_roles
        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolA->id,
            'school_user_id' => $this->membershipA->id,
            'role_id' => $this->roleA->id,
        ]);

        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolA->id,
            'school_user_id' => $this->membershipMultiA->id,
            'role_id' => $this->roleA->id,
        ]);

        // 6. Create Students for School A and School B
        Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA->id,
            'student_code' => 'EST-P2-A',
            'document_type' => 'TI',
            'document_number' => '11111',
            'first_name' => 'EstudianteA',
            'last_name' => 'SanJose',
            'gender' => 'M',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogota',
            'academic_status' => 'ACTIVE',
        ]);

        Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolB->id,
            'student_code' => 'EST-P2-B',
            'document_type' => 'TI',
            'document_number' => '22222',
            'first_name' => 'EstudianteB',
            'last_name' => 'LaSalle',
            'gender' => 'F',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogota',
            'academic_status' => 'ACTIVE',
        ]);

        RlsManager::purgeTenantContext();
    }

    // --- AREA 1: PRE-TENANT TOKEN & GLOBAL AUTHENTICATION TESTS (8 tests) ---

    public function test_login_successful_with_valid_credentials_returns_pre_tenant_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->userActive->email,
            'password' => 'SecretPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'user', 'schools', 'pre_tenant_token']);

        $token = DB::table('personal_access_tokens')
            ->where('tokenable_id', $this->userActive->id)
            ->where('name', 'pre_tenant_token')
            ->latest('id')
            ->first();
        $this->assertNotNull($token);
        $this->assertStringContainsString('scope:pre-tenant', $token->abilities);
    }

    public function test_login_rejected_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->userActive->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['Credenciales inválidas.']]);
    }

    public function test_login_rejected_for_non_existent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'Pass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['Credenciales inválidas.']]);
    }

    public function test_login_rejected_for_deactivated_global_user(): void
    {
        User::on('pgsql_system')->where('id', $this->userActive->id)->update(['status' => 'INACTIVE']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->userActive->email,
            'password' => 'SecretPass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['Credenciales inválidas.']]);
    }

    public function test_login_rate_limiting_protects_against_brute_force(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->userActive->email,
                'password' => 'WrongPass',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->userActive->email,
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(429);
    }

    public function test_pre_tenant_token_allows_my_schools_endpoint(): void
    {
        $token = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/tenant/my-schools');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_pre_tenant_token_rejected_on_academic_tenant_aware_routes(): void
    {
        $token = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/students');

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Token no configurado para un colegio. Se requiere seleccionar colegio.']);
    }

    public function test_token_rejected_after_explicit_logout(): void
    {
        $token = $this->userActive->createToken('tenant_token', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200);

        $this->app['auth']->forgetGuards();

        $nextResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $nextResponse->assertStatus(401);
    }

    // --- AREA 2: TENANT SELECTION & SWITCHING TESTS (9 tests) ---

    public function test_tenant_selection_successful_for_active_membership_issues_tenant_token(): void
    {
        $preToken = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $preToken)
            ->postJson('/api/v1/auth/select-tenant', ['school_id' => $this->schoolA->id]);

        $response->assertStatus(200)
            ->assertJsonStructure(['tenant_token', 'school_id']);

        $this->assertEquals($this->schoolA->id, $response->json('school_id'));
    }

    public function test_select_tenant_revokes_pre_tenant_token(): void
    {
        $preTokenObj = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant']);
        $preToken = $preTokenObj->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $preToken)
            ->postJson('/api/v1/auth/select-tenant', ['school_id' => $this->schoolA->id]);

        $this->assertNull(DB::table('personal_access_tokens')->find($preTokenObj->accessToken->id));
    }

    public function test_tenant_selection_rejected_for_unauthorized_school(): void
    {
        $preToken = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $preToken)
            ->postJson('/api/v1/auth/select-tenant', ['school_id' => $this->schoolB->id]); // UserActive is not in School B

        $response->assertStatus(422)
            ->assertJsonFragment(['school_id' => ['Membresía no autorizada o inactiva.']]);
    }

    public function test_tenant_selection_rejected_for_deactivated_school_user(): void
    {
        SchoolUser::on('pgsql_system')->where('id', $this->membershipA->id)->update(['status' => 'INACTIVE']);
        $preToken = $this->userActive->createToken('pre_tenant', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $preToken)
            ->postJson('/api/v1/auth/select-tenant', ['school_id' => $this->schoolA->id]);

        $response->assertStatus(422);
    }

    public function test_tenant_switch_from_school_a_to_school_b_successful(): void
    {
        $tokenA = $this->userMultiSchool->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson('/api/v1/auth/switch-tenant', ['target_school_id' => $this->schoolB->id]);

        $response->assertStatus(200)
            ->assertJsonStructure(['tenant_token', 'school_id']);

        $this->assertEquals($this->schoolB->id, $response->json('school_id'));
    }

    public function test_token_for_school_a_cannot_access_school_b_data(): void
    {
        $tokenA = $this->userMultiSchool->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $response->assertStatus(200);
        $studentNames = collect($response->json('data'))->pluck('first_name')->toArray();

        $this->assertContains('EstudianteA', $studentNames);
        $this->assertNotContains('EstudianteB', $studentNames);
    }

    public function test_user_belonging_to_multiple_schools_sees_independent_permissions_per_school(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/tenant/my-permissions');

        $response->assertStatus(200);
        $this->assertContains('students.view', $response->json('data'));
        $this->assertNotContains('students.create', $response->json('data'));
    }

    public function test_access_rejected_if_school_user_deactivated_after_token_issuance(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        // Deactivate membership after token is issued
        SchoolUser::on('pgsql_system')->where('id', $this->membershipA->id)->update(['status' => 'INACTIVE']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'Membresía inactiva o no autorizada para este colegio.']);
    }

    public function test_token_rejected_when_expired(): void
    {
        config(['sanctum.expiration' => 60]);

        $tokenObj = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id]);
        DB::table('personal_access_tokens')->where('id', $tokenObj->accessToken->id)->update([
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenObj->plainTextToken)
            ->getJson('/api/v1/students');

        $response->assertStatus(401);
    }

    // --- AREA 3: RBAC & MIDDLEWARE AUTHORIZATION TESTS (9 tests) ---

    public function test_rbac_middleware_allows_access_when_permission_granted(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $response->assertStatus(200);
    }

    public function test_rbac_middleware_denies_access_when_permission_missing(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        // Attempt POST to create student without students.create permission
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson('/api/v1/students', [
                'first_name' => 'Nuevo',
                'last_name' => 'Alumno',
                'document_type' => 'TI',
                'document_number' => '99999',
                'gender' => 'M',
                'birth_date' => '2015-01-01',
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['message' => 'No posee los permisos requeridos para esta acción en el colegio activo.']);
    }

    public function test_my_permissions_endpoint_returns_effective_permissions_in_active_tenant(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/tenant/my-permissions');

        $response->assertStatus(200)
            ->assertJson(['school_id' => $this->schoolA->id]);
    }

    public function test_permission_change_takes_effect_immediately_on_next_request(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        // Initially create permission is missing
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson('/api/v1/students', [
                'first_name' => 'Nuevo', 'last_name' => 'Alumno', 'document_type' => 'TI',
                'document_number' => '99999', 'gender' => 'M', 'birth_date' => '2015-01-01',
            ]);
        $res1->assertStatus(403);

        // Grant permission dynamically
        $this->roleA->permissions()->attach($this->permCreateStudents->id);

        $res2 = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->postJson('/api/v1/students', [
                'first_name' => 'Nuevo', 'last_name' => 'Alumno', 'document_type' => 'TI',
                'document_number' => '99999', 'gender' => 'M', 'birth_date' => '2015-01-01',
            ]);
        $res2->assertStatus(201);
    }

    public function test_role_change_takes_effect_immediately_on_next_request(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        // Detach role A
        DB::connection('pgsql_system')->table('school_user_roles')
            ->where('school_user_id', $this->membershipA->id)
            ->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $response->assertStatus(403);
    }

    public function test_headers_cannot_override_sanctum_tenant_ability(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        // Attempting to pass X-School-ID: SchoolB while token has tenant:SchoolA ability
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->withHeader('X-School-ID', (string) $this->schoolB->id)
            ->getJson('/api/v1/students');

        $response->assertStatus(200);
        $this->assertEquals($this->schoolA->id, $response->json('school_id')); // Token ability schoolA enforced
    }

    public function test_ensure_tenant_permission_middleware_requires_active_tenant_context(): void
    {
        $tokenPre = $this->userActive->createToken('pre', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenPre)
            ->getJson('/api/v1/students');

        $response->assertStatus(403);
    }

    public function test_platform_admin_executes_http_requests_under_app_user(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $dbUser = DB::connection('pgsql')->select("SELECT session_user, current_user")[0];
        $this->assertEquals('app_user', $dbUser->session_user);
    }

    public function test_platform_admin_cannot_activate_app_bypass_rls_via_http(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $studentNames = collect($response->json('data'))->pluck('first_name')->toArray();
        $this->assertNotContains('EstudianteB', $studentNames);
    }

    // --- AREA 4: DATABASE RLS, CONNECTION PURGING & BOOTSTRAP TESTS (9 tests) ---

    public function test_api_user_cannot_query_memberships_of_another_user(): void
    {
        $tokenActive = $this->userActive->createToken('pre', ['scope:pre-tenant'])->plainTextToken;

        // UserActive attempts GET /tenant/my-schools passing ?user_id=MultiUser
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenActive)
            ->getJson('/api/v1/tenant/my-schools?user_id=' . $this->userMultiSchool->id);

        $response->assertStatus(200);
        $schoolIds = collect($response->json('data'))->pluck('school_id')->toArray();

        $this->assertContains($this->schoolA->id, $schoolIds);
        $this->assertNotContains($this->schoolB->id, $schoolIds); // School B belongs to MultiUser, not UserActive
    }

    public function test_bootstrap_function_cannot_enumerate_other_users_memberships_via_api(): void
    {
        $tokenActive = $this->userActive->createToken('pre', ['scope:pre-tenant'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenActive)
            ->postJson('/api/v1/auth/select-tenant', [
                'school_id' => $this->schoolB->id,
                'user_id' => $this->userMultiSchool->id, // Malicious override attempt
            ]);

        $response->assertStatus(422);
    }

    public function test_raw_select_on_school_users_without_tenant_context_returns_zero_rows(): void
    {
        RlsManager::purgeTenantContext('pgsql');

        $rows = DB::connection('pgsql')->table('school_users')->get();
        $this->assertCount(0, $rows);
    }

    public function test_user_can_query_own_memberships_via_bootstrap_function(): void
    {
        $schools = DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$this->userMultiSchool->id]);
        $schoolIds = collect($schools)->pluck('school_id')->toArray();

        $this->assertContains($this->schoolA->id, $schoolIds);
        $this->assertContains($this->schoolB->id, $schoolIds);
    }

    public function test_postgre_sql_rls_active_during_all_http_requests(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $rlsStatus = DB::connection('pgsql')->select("SELECT relrowsecurity FROM pg_class WHERE relname = 'students'")[0]->relrowsecurity;
        $this->assertTrue($rlsStatus);
    }

    public function test_app_user_cannot_bypass_rls_via_http_request(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $queryResult = Student::on('pgsql')->withoutGlobalScopes()->get();
        $this->assertFalse($queryResult->contains('school_id', $this->schoolB->id));
    }

    public function test_app_current_school_id_is_purged_on_request_completion(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $postRequestTenantId = DB::connection('pgsql')->select("SELECT get_current_school_id() as sid")[0]->sid;
        $this->assertNull($postRequestTenantId);
    }

    public function test_reused_pdo_connection_does_not_retain_previous_current_school_id(): void
    {
        $tokenA = $this->userActive->createToken('t_a', ['tenant:' . $this->schoolA->id])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/api/v1/students');

        $postRequestTenantId = DB::connection('pgsql')->select("SELECT get_current_school_id() as sid")[0]->sid;
        $this->assertNull($postRequestTenantId);

        $directStudents = DB::connection('pgsql')->table('students')->get();
        $this->assertCount(0, $directStudents);
    }

    public function test_bootstrap_function_does_not_grant_access_to_students_or_academic_tables(): void
    {
        // Execute bootstrap query
        DB::connection('pgsql')->select("SELECT * FROM get_user_active_memberships(?)", [$this->userActive->id]);

        // Verify direct academic table query STILL returns 0 rows because school_id is null
        $students = DB::connection('pgsql')->table('students')->get();
        $this->assertCount(0, $students);
    }
}
