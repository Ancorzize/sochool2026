<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tenantTables = [
        'school_branding',
        'school_settings',
        'school_users',
        'roles',
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

    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        // 1. Enable RLS and Create Policies for all tenant tables
        foreach ($this->tenantTables as $table) {
            $conn->statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
            $conn->statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
            $conn->statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");
            $conn->statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    school_id = get_current_school_id()
                    OR (
                        current_setting('app.bypass_rls', true) = 'on'
                        AND session_user = 'app_system'
                    )
                );
            ");
        }

        try {
            $conn->statement("GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO app_user;");
            $conn->statement("GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO app_user;");
        } catch (\Throwable $e) {
            // Ignore if role does not exist
        }

        // 2. Composite Foreign Keys ensuring strict tenant integrity in PostgreSQL
        $compositeFks = [
            'student_guardians' => [
                'fk_sg_student_tenant' => ['student_id', 'students'],
                'fk_sg_guardian_tenant' => ['guardian_id', 'guardians'],
            ],
            'academic_periods' => [
                'fk_ap_year_tenant' => ['academic_year_id', 'academic_years'],
            ],
            'grades' => [
                'fk_grade_level_tenant' => ['educational_level_id', 'educational_levels'],
            ],
            'courses' => [
                'fk_course_year_tenant' => ['academic_year_id', 'academic_years'],
                'fk_course_grade_tenant' => ['grade_id', 'grades'],
            ],
            'course_subject_teachers' => [
                'fk_cst_course_tenant' => ['course_id', 'courses'],
                'fk_cst_subject_tenant' => ['subject_id', 'subjects'],
                'fk_cst_teacher_tenant' => ['teacher_id', 'teachers'],
            ],
            'student_enrollments' => [
                'fk_se_student_tenant' => ['student_id', 'students'],
                'fk_se_year_tenant' => ['academic_year_id', 'academic_years'],
                'fk_se_grade_tenant' => ['grade_id', 'grades'],
            ],
            'course_enrollments' => [
                'fk_ce_enrollment_tenant' => ['student_enrollment_id', 'student_enrollments'],
                'fk_ce_course_tenant' => ['course_id', 'courses'],
            ],
            'assessments' => [
                'fk_ass_course_tenant' => ['course_id', 'courses'],
                'fk_ass_subject_tenant' => ['subject_id', 'subjects'],
                'fk_ass_period_tenant' => ['academic_period_id', 'academic_periods'],
                'fk_ass_teacher_tenant' => ['teacher_id', 'teachers'],
            ],
            'student_grades' => [
                'fk_sg_assessment_tenant' => ['assessment_id', 'assessments'],
                'fk_sg_student_tenant' => ['student_id', 'students'],
            ],
            'period_final_grades' => [
                'fk_pfg_course_tenant' => ['course_id', 'courses'],
                'fk_pfg_subject_tenant' => ['subject_id', 'subjects'],
                'fk_pfg_student_tenant' => ['student_id', 'students'],
                'fk_pfg_period_tenant' => ['academic_period_id', 'academic_periods'],
            ],
            'attendances' => [
                'fk_att_course_tenant' => ['course_id', 'courses'],
                'fk_att_student_tenant' => ['student_id', 'students'],
                'fk_att_status_tenant' => ['attendance_status_id', 'attendance_statuses'],
            ],
            'report_cards' => [
                'fk_rc_student_tenant' => ['student_id', 'students'],
                'fk_rc_year_tenant' => ['academic_year_id', 'academic_years'],
                'fk_rc_period_tenant' => ['academic_period_id', 'academic_periods'],
                'fk_rc_course_tenant' => ['course_id', 'courses'],
            ],
        ];

        foreach ($compositeFks as $childTable => $constraints) {
            foreach ($constraints as $constraintName => $fkInfo) {
                $childFkCol = $fkInfo[0];
                $parentTable = $fkInfo[1];
                $conn->statement("
                    ALTER TABLE {$childTable}
                    ADD CONSTRAINT {$constraintName}
                    FOREIGN KEY ({$childFkCol}, school_id)
                    REFERENCES {$parentTable} (id, school_id)
                    ON DELETE RESTRICT;
                ");
            }
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');

        foreach ($this->tenantTables as $table) {
            $conn->statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");
            $conn->statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }
    }
};
