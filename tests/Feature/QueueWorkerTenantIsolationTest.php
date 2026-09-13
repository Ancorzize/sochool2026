<?php

namespace Tests\Feature;

use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\Contracts\TenantAwareJob;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DummyTenantJob extends TenantAwareJob
{
    public array $visibleStudentIds = [];

    protected function executeJob(): void
    {
        // Execute tenant job query under app_user connection
        $this->visibleStudentIds = Student::on('pgsql')->withoutGlobalScopes()->pluck('id')->toArray();
    }
}

class QueueWorkerTenantIsolationTest extends TestCase
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
            'code' => 'SCH-Q-A-' . Str::random(3),
            'name' => 'Colegio Queue A',
            'legal_name' => 'Colegio Queue A S.A.S.',
            'tax_identifier' => '900.555.666-1',
            'slug' => 'colegio-queue-a-' . Str::random(3),
            'email' => 'qa@rls.com',
            'phone' => '111',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-Q-B-' . Str::random(3),
            'name' => 'Colegio Queue B',
            'legal_name' => 'Colegio Queue B S.A.S.',
            'tax_identifier' => '900.777.888-2',
            'slug' => 'colegio-queue-b-' . Str::random(3),
            'email' => 'qb@rls.com',
            'phone' => '222',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->studentA = Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolA->id,
            'student_code' => 'EST-Q-A-' . Str::random(3),
            'document_type' => 'TI',
            'document_number' => '1111' . rand(1000, 9999),
            'first_name' => 'EstudianteA_Queue',
            'last_name' => 'Worker',
            'gender' => 'M',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ]);

        $this->studentB = Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'school_id' => $this->schoolB->id,
            'student_code' => 'EST-Q-B-' . Str::random(3),
            'document_type' => 'TI',
            'document_number' => '2222' . rand(1000, 9999),
            'first_name' => 'EstudianteB_Queue',
            'last_name' => 'Worker',
            'gender' => 'F',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_queue_jobs_execute_with_isolated_tenant_context_on_reused_connection(): void
    {
        // 1. Execute Job A on connection
        $jobA = new DummyTenantJob($this->schoolA->id);
        $jobA->handle();

        $this->assertContains($this->studentA->id, $jobA->visibleStudentIds);
        $this->assertNotContains($this->studentB->id, $jobA->visibleStudentIds);

        // 2. Immediately execute Job B on the SAME reused connection
        $jobB = new DummyTenantJob($this->schoolB->id);
        $jobB->handle();

        $this->assertContains($this->studentB->id, $jobB->visibleStudentIds);
        $this->assertNotContains($this->studentA->id, $jobB->visibleStudentIds);

        // 3. Verify context was completely purged after Job B completed
        $postJobContext = DB::connection('pgsql')->select("SELECT get_current_school_id() as sid")[0]->sid;
        $this->assertNull($postJobContext);
    }
}
