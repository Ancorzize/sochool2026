<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\Course;
use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use App\Domain\User\Models\User;
use App\Infrastructure\Tenant\RlsManager;
use App\Infrastructure\Tenant\TenantContext;
use App\Policies\StudentPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
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

        $this->schoolA = School::factory()->create(['name' => 'Colegio Test A']);
        $this->schoolB = School::factory()->create(['name' => 'Colegio Test B']);

        RlsManager::setTenantContext($this->schoolA->id);
        $this->studentA = Student::factory()->create([
            'school_id' => $this->schoolA->id,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);

        RlsManager::setTenantContext($this->schoolB->id);
        $this->studentB = Student::factory()->create([
            'school_id' => $this->schoolB->id,
            'first_name' => 'Maria',
            'last_name' => 'Gómez',
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_school_a_cannot_view_students_of_school_b_via_eloquent(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $students = Student::on('pgsql')->get();

        $this->assertTrue($students->contains('id', $this->studentA->id));
        $this->assertFalse($students->contains('id', $this->studentB->id));
        $this->assertEquals(1, $students->count());
    }

    public function test_postgre_sql_rls_prevents_reading_school_b_even_without_global_scope(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        // Explicitly bypass Eloquent global scope to test pure DB PostgreSQL RLS policy under app_user connection
        $students = Student::on('pgsql')->withoutGlobalScopes()->get();

        $this->assertTrue($students->contains('id', $this->studentA->id));
        $this->assertFalse($students->contains('id', $this->studentB->id));
    }

    public function test_student_policy_denies_cross_tenant_access(): void
    {
        $policy = new StudentPolicy();
        $user = User::factory()->create();

        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        $this->assertTrue($policy->view($user, $this->studentA));
        $this->assertFalse($policy->view($user, $this->studentB));
        $this->assertFalse($policy->update($user, $this->studentB));
        $this->assertFalse($policy->delete($user, $this->studentB));
    }

    public function test_composite_foreign_keys_prevent_cross_tenant_enrollment_at_db_level(): void
    {
        RlsManager::setTenantContext($this->schoolA->id);
        $courseA = Course::factory()->create(['school_id' => $this->schoolA->id]);

        // Attempting to enroll student from School B into Course of School A must throw QueryException
        $this->expectException(QueryException::class);

        DB::connection('pgsql_system')->table('student_enrollments')->insert([
            'school_id' => $this->schoolB->id, // School B
            'student_id' => $this->studentB->id, // Student of School B
            'academic_year_id' => $courseA->academic_year_id,
            'grade_id' => $courseA->grade_id,
            'enrollment_date' => now(),
            'status' => 'ENROLLED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reused_connection_purges_tenant_context_completely(): void
    {
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');
        $this->assertEquals($this->schoolA->id, TenantContext::id());

        RlsManager::purgeTenantContext('pgsql');
        $this->assertNull(TenantContext::id());
        $this->assertFalse(TenantContext::hasTenant());

        $result = DB::connection('pgsql')->select("SELECT get_current_school_id() as school_id")[0];
        $this->assertNull($result->school_id);
    }
}
