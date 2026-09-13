<?php

namespace Tests\Feature;

use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\Role;
use App\Domain\User\Models\SchoolUser;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\Contracts\TenantAwareJob;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolUserRolesJobTestImpl extends TenantAwareJob
{
    public array $visibleRoleUserIds = [];

    protected function executeJob(): void
    {
        $this->visibleRoleUserIds = DB::connection('pgsql')->table('school_user_roles')
            ->pluck('school_user_id')
            ->toArray();
    }
}

class SchoolUserRolesIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected School $schoolA;
    protected School $schoolB;
    protected SchoolUser $schoolUserA;
    protected SchoolUser $schoolUserB;
    protected Role $roleA;
    protected Role $roleB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // 1. Create School A & B
        $this->schoolA = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-SUR-A-' . Str::random(3),
            'name' => 'Colegio SUR A',
            'legal_name' => 'Colegio SUR A S.A.S.',
            'tax_identifier' => '900.111.111-1',
            'slug' => 'colegio-sur-a-' . Str::random(3),
            'email' => 'sura@test.com', 'phone' => '111', 'city' => 'Bogota',
            'state' => 'Cundinamarca', 'country' => 'Colombia', 'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-SUR-B-' . Str::random(3),
            'name' => 'Colegio SUR B',
            'legal_name' => 'Colegio SUR B S.A.S.',
            'tax_identifier' => '900.222.222-2',
            'slug' => 'colegio-sur-b-' . Str::random(3),
            'email' => 'surb@test.com', 'phone' => '222', 'city' => 'Bogota',
            'state' => 'Cundinamarca', 'country' => 'Colombia', 'status' => 'ACTIVE',
        ]);

        // 2. Create Users & SchoolUsers
        $userA = User::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(), 'first_name' => 'UserA', 'last_name' => 'Test',
            'email' => 'usera-' . Str::random(4) . '@test.com', 'password' => 'secret', 'status' => 'ACTIVE',
        ]);

        $userB = User::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(), 'first_name' => 'UserB', 'last_name' => 'Test',
            'email' => 'userb-' . Str::random(4) . '@test.com', 'password' => 'secret', 'status' => 'ACTIVE',
        ]);

        $this->schoolUserA = SchoolUser::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id, 'user_id' => $userA->id, 'status' => 'ACTIVE',
        ]);

        $this->schoolUserB = SchoolUser::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id, 'user_id' => $userB->id, 'status' => 'ACTIVE',
        ]);

        // 3. Create Roles for School A & B
        $this->roleA = Role::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id, 'name' => 'ROLE_A_' . Str::random(3), 'guard_name' => 'web',
        ]);

        $this->roleB = Role::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id, 'name' => 'ROLE_B_' . Str::random(3), 'guard_name' => 'web',
        ]);

        // 4. Insert valid school_user_roles for School A and School B
        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolA->id,
            'school_user_id' => $this->schoolUserA->id,
            'role_id' => $this->roleA->id,
        ]);

        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolB->id,
            'school_user_id' => $this->schoolUserB->id,
            'role_id' => $this->roleB->id,
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_school_a_can_query_its_own_school_user_roles(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $rows = DB::connection('pgsql')->table('school_user_roles')->get();

        $this->assertCount(1, $rows);
        $this->assertEquals($this->schoolUserA->id, $rows[0]->school_user_id);
        $this->assertEquals($this->roleA->id, $rows[0]->role_id);
    }

    public function test_school_a_cannot_query_school_user_roles_of_school_b(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $schoolBRows = DB::connection('pgsql')->table('school_user_roles')
            ->where('school_user_id', $this->schoolUserB->id)
            ->get();

        $this->assertCount(0, $schoolBRows);
    }

    public function test_school_a_cannot_modify_school_user_roles_of_school_b(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $affected = DB::connection('pgsql')->table('school_user_roles')
            ->where('school_user_id', $this->schoolUserB->id)
            ->update(['role_id' => $this->roleA->id]);

        $this->assertEquals(0, $affected);
    }

    public function test_school_a_cannot_delete_school_user_roles_of_school_b(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $affected = DB::connection('pgsql')->table('school_user_roles')
            ->where('school_user_id', $this->schoolUserB->id)
            ->delete();

        $this->assertEquals(0, $affected);
    }

    public function test_app_user_cannot_use_bypass_rls_variable_on_school_user_roles(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        // Malicious attempt by app_user to bypass RLS
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $schoolBRows = DB::connection('pgsql')->table('school_user_roles')
            ->where('school_user_id', $this->schoolUserB->id)
            ->get();

        $this->assertCount(0, $schoolBRows);
    }

    public function test_postgre_sql_rejects_school_user_a_with_role_b(): void
    {
        $this->expectException(QueryException::class);

        // Attempting to link SchoolUser of A with Role of B under School A context
        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolA->id,
            'school_user_id' => $this->schoolUserA->id,
            'role_id' => $this->roleB->id, // Role from School B
        ]);
    }

    public function test_postgre_sql_rejects_school_user_b_with_role_a(): void
    {
        $this->expectException(QueryException::class);

        // Attempting to link SchoolUser of B with Role of A under School B context
        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolB->id,
            'school_user_id' => $this->schoolUserB->id,
            'role_id' => $this->roleA->id, // Role from School A
        ]);
    }

    public function test_duplicate_school_user_role_assignment_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        // Duplicate insert of exact same (school_id, school_user_id, role_id)
        DB::connection('pgsql_system')->table('school_user_roles')->insert([
            'school_id' => $this->schoolA->id,
            'school_user_id' => $this->schoolUserA->id,
            'role_id' => $this->roleA->id,
        ]);
    }

    public function test_queue_workers_maintain_isolation_for_school_user_roles(): void
    {
        $jobA = new SchoolUserRolesJobTestImpl($this->schoolA->id);
        $jobA->handle();

        $this->assertContains($this->schoolUserA->id, $jobA->visibleRoleUserIds);
        $this->assertNotContains($this->schoolUserB->id, $jobA->visibleRoleUserIds);

        $jobB = new SchoolUserRolesJobTestImpl($this->schoolB->id);
        $jobB->handle();

        $this->assertContains($this->schoolUserB->id, $jobB->visibleRoleUserIds);
        $this->assertNotContains($this->schoolUserA->id, $jobB->visibleRoleUserIds);
    }
}
