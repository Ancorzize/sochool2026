<?php

namespace Tests\Feature;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompositeForeignKeysIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected School $schoolA;
    protected School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $this->schoolA = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-FK-A-' . Str::random(3),
            'name' => 'Colegio FK A',
            'legal_name' => 'Colegio FK A S.A.S.',
            'tax_identifier' => '900.999.111-1',
            'slug' => 'colegio-fk-a-' . Str::random(3),
            'email' => 'fka@rls.com',
            'phone' => '111',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-FK-B-' . Str::random(3),
            'name' => 'Colegio FK B',
            'legal_name' => 'Colegio FK B S.A.S.',
            'tax_identifier' => '900.999.222-2',
            'slug' => 'colegio-fk-b-' . Str::random(3),
            'email' => 'fkb@rls.com',
            'phone' => '222',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_postgre_sql_rejects_student_guardian_cross_tenant_association(): void
    {
        $sys = DB::connection('pgsql_system');

        $studentA = Student::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolA->id,
            'student_code' => 'EST-FKA', 'document_type' => 'TI', 'document_number' => '111222',
            'first_name' => 'A', 'last_name' => 'A', 'gender' => 'M', 'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana', 'city' => 'Bogota', 'academic_status' => 'ACTIVE',
        ]);

        $guardianB = Guardian::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolB->id,
            'document_type' => 'CC', 'document_number' => '333444',
            'first_name' => 'B', 'last_name' => 'B', 'email' => 'b@guardian.com',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to link Student of School A with Guardian of School B under School A context
        $sys->table('student_guardians')->insert([
            'school_id' => $this->schoolA->id,
            'student_id' => $studentA->id,
            'guardian_id' => $guardianB->id,
            'relationship' => 'PADRE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_postgre_sql_rejects_academic_period_cross_tenant_association(): void
    {
        $sys = DB::connection('pgsql_system');

        $yearB = AcademicYear::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id, 'name' => '2026-B',
            'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE',
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create Academic Period in School A referencing Academic Year of School B
        $sys->table('academic_periods')->insert([
            'school_id' => $this->schoolA->id,
            'academic_year_id' => $yearB->id,
            'name' => 'Periodo 1 Cross',
            'period_order' => 1,
            'weight_percentage' => 25.00,
            'start_date' => '2026-01-01', 'end_date' => '2026-04-01', 'status' => 'OPEN',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_postgre_sql_rejects_grade_cross_tenant_association(): void
    {
        $sys = DB::connection('pgsql_system');

        $levelB = EducationalLevel::on('pgsql_system')->create([
            'school_id' => $this->schoolB->id, 'name' => 'Secundaria B',
            'code' => 'SEC-B', 'level_order' => 2,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to create Grade in School A referencing Educational Level of School B
        $sys->table('grades')->insert([
            'school_id' => $this->schoolA->id,
            'educational_level_id' => $levelB->id,
            'name' => 'Grado 6° Cross',
            'code' => 'G-6-X',
            'grade_order' => 6,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_postgre_sql_rejects_course_subject_teacher_cross_tenant_association(): void
    {
        $sys = DB::connection('pgsql_system');

        $teacherB = Teacher::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(), 'school_id' => $this->schoolB->id,
            'teacher_code' => 'DOC-B', 'document_type' => 'CC', 'document_number' => '555666',
            'first_name' => 'DocenteB', 'last_name' => 'Cross', 'email' => 'docb@test.com', 'status' => 'ACTIVE',
        ]);

        $yearA = AcademicYear::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id, 'name' => '2026-A',
            'start_date' => '2026-01-01', 'end_date' => '2026-11-30', 'status' => 'ACTIVE',
        ]);

        $levelA = EducationalLevel::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id, 'name' => 'Secundaria A',
            'code' => 'SEC-A', 'level_order' => 2,
        ]);

        $gradeA = Grade::on('pgsql_system')->create([
            'school_id' => $this->schoolA->id, 'educational_level_id' => $levelA->id,
            'name' => 'Grado 6° A', 'code' => 'G-6-A', 'grade_order' => 6,
        ]);

        $courseAId = $sys->table('courses')->insertGetId([
            'school_id' => $this->schoolA->id, 'academic_year_id' => $yearA->id,
            'grade_id' => $gradeA->id, 'name' => '6-A', 'shift' => 'MAÑANA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $areaAId = $sys->table('knowledge_areas')->insertGetId([
            'school_id' => $this->schoolA->id, 'name' => 'Matemáticas A', 'code' => 'MAT-A',
            'area_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $subjectAId = $sys->table('subjects')->insertGetId([
            'school_id' => $this->schoolA->id, 'knowledge_area_id' => $areaAId,
            'name' => 'Matemáticas 6', 'code' => 'MAT-6-A', 'color' => '#000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Attempting to assign Teacher of School B to Course & Subject of School A
        $sys->table('course_subject_teachers')->insert([
            'school_id' => $this->schoolA->id,
            'course_id' => $courseAId,
            'subject_id' => $subjectAId,
            'teacher_id' => $teacherB->id, // Teacher from School B
            'hours_per_week' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
