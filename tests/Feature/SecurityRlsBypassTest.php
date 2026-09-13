<?php

namespace Tests\Feature;

use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityRlsBypassTest extends TestCase
{
    use DatabaseTransactions;

    protected School $schoolA;
    protected School $schoolB;
    protected Student $studentA;
    protected Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $this->schoolA = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-RLS-A-' . Str::random(3),
            'name' => 'Colegio RLS A',
            'legal_name' => 'Colegio RLS A S.A.S.',
            'tax_identifier' => '900.111.222-1',
            'slug' => 'colegio-rls-a-' . Str::random(3),
            'email' => 'a@rls.com',
            'phone' => '111',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-RLS-B-' . Str::random(3),
            'name' => 'Colegio RLS B',
            'legal_name' => 'Colegio RLS B S.A.S.',
            'tax_identifier' => '900.333.444-2',
            'slug' => 'colegio-rls-b-' . Str::random(3),
            'email' => 'b@rls.com',
            'phone' => '222',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->studentA = Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA->id,
            'student_code' => 'EST-RLS-A-' . Str::random(3),
            'document_type' => 'TI',
            'document_number' => '1111' . rand(1000, 9999),
            'first_name' => 'EstudianteA_Rls',
            'last_name' => 'Seguridad',
            'gender' => 'M',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ]);

        $this->studentB = Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolB->id,
            'student_code' => 'EST-RLS-B-' . Str::random(3),
            'document_type' => 'TI',
            'document_number' => '2222' . rand(1000, 9999),
            'first_name' => 'EstudianteB_Rls',
            'last_name' => 'Seguridad',
            'gender' => 'F',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_app_user_cannot_bypass_rls_by_setting_bypass_variable(): void
    {
        // 1. Authenticate context as School A under app_user connection
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        // 2. Malicious attempt by app_user to activate app.bypass_rls = 'on'
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // 3. Try to SELECT student of School B without Eloquent global scope
        $queryResult = Student::on('pgsql')->withoutGlobalScopes()->get();

        $this->assertTrue($queryResult->contains('id', $this->studentA->id));
        $this->assertFalse($queryResult->contains('id', $this->studentB->id));
    }

    public function test_app_user_cannot_update_data_of_another_school_even_with_bypass_flag(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // Attempt raw UPDATE on School B student using app_user connection
        $affectedRows = DB::connection('pgsql')->table('students')
            ->where('id', $this->studentB->id)
            ->update(['first_name' => 'Hackeado']);

        $this->assertEquals(0, $affectedRows);

        // Verify Student B name is unchanged using pgsql_system with bypass & withoutGlobalScopes
        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");
        $freshStudentB = Student::on('pgsql_system')->withoutGlobalScopes()->find($this->studentB->id);
        $this->assertEquals('EstudianteB_Rls', $freshStudentB->first_name);
    }

    public function test_app_user_cannot_delete_data_of_another_school_even_with_bypass_flag(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');
        DB::connection('pgsql')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // Attempt raw DELETE on School B student using app_user connection
        $affectedRows = DB::connection('pgsql')->table('students')
            ->where('id', $this->studentB->id)
            ->delete();

        $this->assertEquals(0, $affectedRows);

        // Verify Student B still exists using pgsql_system with bypass & withoutGlobalScopes
        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");
        $freshStudentB = Student::on('pgsql_system')->withoutGlobalScopes()->find($this->studentB->id);
        $this->assertNotNull($freshStudentB);
    }

    public function test_app_system_role_can_bypass_rls_when_explicitly_configured(): void
    {
        // Set tenant context for app_system with bypass_rls = 'on'
        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $allStudents = Student::on('pgsql_system')->withoutGlobalScopes()->get();

        $this->assertTrue($allStudents->contains('id', $this->studentA->id));
        $this->assertTrue($allStudents->contains('id', $this->studentB->id));
    }
}
