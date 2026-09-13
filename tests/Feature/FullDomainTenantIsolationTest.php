<?php

namespace Tests\Feature;

use App\Domain\Tenant\Models\School;
use App\Infrastructure\Tenant\RlsManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FullDomainTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected School $schoolA;
    protected School $schoolB;

    protected array $allTenantTables = [
        'school_branding',
        'school_settings',
        'school_users',
        'roles',
        'school_user_roles',
        'students',
        'teachers',
        'guardians',
        'student_guardians',
        'academic_years',
        'academic_periods',
        'educational_levels',
        'knowledge_areas',
        'academic_plans',
        'academic_plan_subjects',
        'grades',
        'courses',
        'subjects',
        'course_subject_teachers',
        'student_enrollments',
        'course_enrollments',
        'competencies',
        'achievement_indicators',
        'student_indicator_evaluations',
        'grading_scales',
        'grading_scale_items',
        'grading_scale_assignments',
        'assessment_categories',
        'assessments',
        'student_grades',
        'period_final_grades',
        'period_grade_snapshots',
        'attendance_statuses',
        'attendances',
        'report_card_templates',
        'report_card_sections',
        'report_card_fields',
        'report_card_assignments',
        'report_cards',
        'teacher_period_observations',
        'media_files',
        'audit_logs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        $this->schoolA = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-ALL-A-' . Str::random(3),
            'name' => 'Colegio Full A',
            'legal_name' => 'Colegio Full A S.A.S.',
            'tax_identifier' => '900.888.111-1',
            'slug' => 'colegio-full-a-' . Str::random(3),
            'email' => 'fa@rls.com',
            'phone' => '111',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        $this->schoolB = School::on('pgsql_system')->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-ALL-B-' . Str::random(3),
            'name' => 'Colegio Full B',
            'legal_name' => 'Colegio Full B S.A.S.',
            'tax_identifier' => '900.888.222-2',
            'slug' => 'colegio-full-b-' . Str::random(3),
            'email' => 'fb@rls.com',
            'phone' => '222',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ]);

        RlsManager::purgeTenantContext();
    }

    public function test_all_tenant_tables_enforce_strict_postgresql_rls_isolation(): void
    {
        DB::connection('pgsql_system')->statement("SELECT set_config('app.bypass_rls', 'on', false);");

        // Populate valid domain trees for School A and School B
        $chainA = $this->createSchoolDomainTree($this->schoolA->id);
        $chainB = $this->createSchoolDomainTree($this->schoolB->id);

        // VERIFY ISOLATION UNDER APP_USER CONNECTION (pgsql) WITH SCHOOL A CONTEXT
        RlsManager::setTenantContext($this->schoolA->id, false, 'pgsql');

        foreach ($this->allTenantTables as $table) {
            $columns = DB::connection('pgsql')->getSchemaBuilder()->getColumnListing($table);
            if (!in_array('school_id', $columns, true)) {
                continue;
            }

            $schoolIdsInQuery = DB::connection('pgsql')->table($table)
                ->pluck('school_id')
                ->unique()
                ->toArray();

            // Assert that results ONLY contain schoolA->id and NEVER contain schoolB->id
            $this->assertNotContains($this->schoolB->id, $schoolIdsInQuery, "Table '{$table}' leaked School B data!");
        }
    }

    private function createSchoolDomainTree(int $schoolId): array
    {
        $sys = DB::connection('pgsql_system');

        $studentId = $sys->table('students')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'student_code' => 'EST-ALL-' . Str::random(4),
            'document_type' => 'TI',
            'document_number' => (string) rand(1000000, 9999999),
            'first_name' => 'Student',
            'last_name' => 'All',
            'gender' => 'M',
            'birth_date' => '2015-01-01',
            'nationality' => 'Colombiana',
            'city' => 'Bogota',
            'academic_status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $teacherId = $sys->table('teachers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'teacher_code' => 'DOC-ALL-' . Str::random(4),
            'document_type' => 'CC',
            'document_number' => (string) rand(1000000, 9999999),
            'first_name' => 'Profesor',
            'last_name' => 'All',
            'email' => 'doc-' . Str::random(4) . '@test.com',
            'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $guardianId = $sys->table('guardians')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'document_type' => 'CC',
            'document_number' => (string) rand(1000000, 9999999),
            'first_name' => 'Acudiente',
            'last_name' => 'All',
            'email' => 'acu-' . Str::random(4) . '@test.com',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('student_guardians')->insert([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'relationship' => 'PADRE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $yearId = $sys->table('academic_years')->insertGetId([
            'school_id' => $schoolId,
            'name' => '2026-' . Str::random(3),
            'start_date' => '2026-01-01',
            'end_date' => '2026-11-30',
            'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $periodId = $sys->table('academic_periods')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'name' => 'Periodo 1',
            'period_order' => 1,
            'weight_percentage' => 25.00,
            'start_date' => '2026-01-01',
            'end_date' => '2026-04-01',
            'status' => 'OPEN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $levelId = $sys->table('educational_levels')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Level ' . Str::random(3),
            'code' => 'L-' . Str::random(2),
            'level_order' => rand(1, 10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $gradeId = $sys->table('grades')->insertGetId([
            'school_id' => $schoolId,
            'educational_level_id' => $levelId,
            'name' => 'Grade ' . Str::random(3),
            'code' => 'G-' . Str::random(2),
            'grade_order' => rand(1, 10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $areaId = $sys->table('knowledge_areas')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Area ' . Str::random(3),
            'code' => 'A-' . Str::random(2),
            'area_order' => rand(1, 10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $subjectId = $sys->table('subjects')->insertGetId([
            'school_id' => $schoolId,
            'knowledge_area_id' => $areaId,
            'name' => 'Subject ' . Str::random(3),
            'code' => 'S-' . Str::random(2),
            'color' => '#123456',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $courseId = $sys->table('courses')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_id' => $gradeId,
            'name' => 'Course ' . Str::random(3),
            'shift' => 'MAÑANA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $scaleId = $sys->table('grading_scales')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Scale ' . Str::random(3),
            'scale_type' => 'NUMERIC',
            'min_score' => 1.0,
            'max_score' => 5.0,
            'passing_score' => 3.0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $scaleItemId = $sys->table('grading_scale_items')->insertGetId([
            'school_id' => $schoolId,
            'grading_scale_id' => $scaleId,
            'name' => 'Alto',
            'min_value' => 4.0,
            'max_value' => 4.5,
            'equivalent_numeric_value' => 4.2,
            'item_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('grading_scale_assignments')->insert([
            'school_id' => $schoolId,
            'grading_scale_id' => $scaleId,
            'course_id' => $courseId,
            'priority_score' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $catId = $sys->table('assessment_categories')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Cat ' . Str::random(3),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $assId = $sys->table('assessments')->insertGetId([
            'school_id' => $schoolId,
            'course_id' => $courseId,
            'subject_id' => $subjectId,
            'academic_period_id' => $periodId,
            'assessment_category_id' => $catId,
            'teacher_id' => $teacherId,
            'title' => 'Ass ' . Str::random(3),
            'weight_percentage' => 100.0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('student_grades')->insert([
            'school_id' => $schoolId,
            'assessment_id' => $assId,
            'student_id' => $studentId,
            'entered_value' => '4.5',
            'equivalent_numeric_value' => 4.5,
            'grading_scale_item_id' => $scaleItemId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('period_final_grades')->insert([
            'school_id' => $schoolId,
            'course_id' => $courseId,
            'subject_id' => $subjectId,
            'student_id' => $studentId,
            'academic_period_id' => $periodId,
            'final_entered_value' => '4.5',
            'numeric_score' => 4.5,
            'status' => 'FINAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('period_grade_snapshots')->insert([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'academic_period_id' => $periodId,
            'calculation_version' => '1.0',
            'snapshot_data' => json_encode(['status' => 'ok']),
            'calculated_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $attStatusId = $sys->table('attendance_statuses')->insertGetId([
            'school_id' => $schoolId,
            'code' => 'PRES-' . Str::random(3),
            'name' => 'Presente',
            'color' => '#10B981',
            'status_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('attendances')->insert([
            'school_id' => $schoolId,
            'course_id' => $courseId,
            'subject_id' => $subjectId,
            'student_id' => $studentId,
            'attendance_status_id' => $attStatusId,
            'date' => '2026-02-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $tplId = $sys->table('report_card_templates')->insertGetId([
            'school_id' => $schoolId,
            'name' => 'Tpl ' . Str::random(3),
            'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $secId = $sys->table('report_card_sections')->insertGetId([
            'school_id' => $schoolId,
            'template_id' => $tplId,
            'section_type' => 'GRADES',
            'title' => 'Calificaciones',
            'section_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('report_card_fields')->insert([
            'school_id' => $schoolId,
            'section_id' => $secId,
            'field_key' => 'subject_name',
            'label' => 'Asignatura',
            'field_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('report_card_assignments')->insert([
            'school_id' => $schoolId,
            'template_id' => $tplId,
            'course_id' => $courseId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('report_cards')->insert([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'academic_period_id' => $periodId,
            'course_id' => $courseId,
            'template_id' => $tplId,
            'version' => 1,
            'is_latest' => true,
            'status' => 'GENERATED',
            'data_snapshot' => json_encode(['ok' => true]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('teacher_period_observations')->insert([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'subject_id' => $subjectId,
            'academic_period_id' => $periodId,
            'teacher_id' => $teacherId,
            'observation' => 'Excelente estudiante',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('media_files')->insert([
            'uuid' => (string) Str::uuid(),
            'school_id' => $schoolId,
            'entity_type' => 'App\Domain\Student\Models\Student',
            'entity_id' => $studentId,
            'file_category' => 'STUDENT_AVATAR',
            'file_name' => 'avatar.jpg',
            'original_name' => 'avatar.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'disk' => 'local',
            'path' => "tenants/{$schoolId}/avatar.jpg",
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sys->table('audit_logs')->insert([
            'school_id' => $schoolId,
            'action' => 'TEST_LOG',
            'entity_type' => 'Student',
            'entity_id' => $studentId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('studentId', 'teacherId', 'courseId');
    }
}
