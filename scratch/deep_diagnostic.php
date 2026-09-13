<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$domainTables = [
    'schools', 'school_branding', 'school_settings', 'users', 'school_users',
    'roles', 'permissions', 'role_permissions', 'school_user_roles', 'students',
    'teachers', 'guardians', 'student_guardians', 'academic_years', 'academic_periods',
    'educational_levels', 'knowledge_areas', 'academic_plans', 'academic_plan_subjects',
    'grades', 'courses', 'subjects', 'course_subject_teachers', 'student_enrollments',
    'course_enrollments', 'competencies', 'achievement_indicators', 'student_indicator_evaluations',
    'grading_scales', 'grading_scale_items', 'grading_scale_assignments', 'assessment_categories',
    'assessments', 'student_grades', 'period_final_grades', 'period_grade_snapshots',
    'attendance_statuses', 'attendances', 'report_card_templates', 'report_card_sections',
    'report_card_fields', 'report_card_assignments', 'report_cards', 'teacher_period_observations',
    'media_files', 'audit_logs'
];

echo "=== 1. POSTGRESQL REAL DOMAIN TABLES RLS AUDIT ===\n";

$tablesWithSchoolIdAndRls = [];
$tablesWithSchoolIdNoRls = [];
$tablesWithoutSchoolId = [];

foreach ($domainTables as $t) {
    $hasSchoolId = DB::connection('pgsql_system')->getSchemaBuilder()->hasColumn($t, 'school_id');
    
    $rlsInfo = DB::connection('pgsql_system')->select("
        SELECT relrowsecurity, relforcerowsecurity 
        FROM pg_class 
        WHERE relname = '{$t}'
    ");
    $rlsEnabled = !empty($rlsInfo) && $rlsInfo[0]->relrowsecurity;

    $policies = DB::connection('pgsql_system')->select("
        SELECT polname 
        FROM pg_policy p 
        JOIN pg_class c ON c.oid = p.polrelid 
        WHERE c.relname = '{$t}'
    ");
    $policyNames = array_map(fn($p) => $p->polname, $policies);

    if ($hasSchoolId && $rlsEnabled) {
        $tablesWithSchoolIdAndRls[] = [
            'table' => $t,
            'policies' => $policyNames,
        ];
    } elseif ($hasSchoolId && !$rlsEnabled) {
        $tablesWithSchoolIdNoRls[] = $t;
    } else {
        $tablesWithoutSchoolId[] = $t;
    }
}

echo "Tables with school_id AND RLS Enabled (" . count($tablesWithSchoolIdAndRls) . "):\n";
foreach ($tablesWithSchoolIdAndRls as $item) {
    echo " - {$item['table']} (Policies: " . implode(', ', $item['policies']) . ")\n";
}

echo "\nTables with school_id BUT NO RLS (" . count($tablesWithSchoolIdNoRls) . "):\n";
foreach ($tablesWithSchoolIdNoRls as $t) {
    echo " - {$t}\n";
}

echo "\nTables WITHOUT school_id (" . count($tablesWithoutSchoolId) . "):\n";
foreach ($tablesWithoutSchoolId as $t) {
    echo " - {$t}\n";
}

echo "\n=== 2. INSPECTION OF SPECIFIC TABLES ===\n";

$inspectTables = ['school_user_roles', 'student_guardians', 'academic_plans', 'academic_plan_subjects'];

foreach ($inspectTables as $t) {
    echo "\n--- TABLE: {$t} ---\n";
    $columns = DB::connection('pgsql_system')->getSchemaBuilder()->getColumnListing($t);
    echo "Columns: " . implode(', ', $columns) . "\n";

    $hasSchoolId = in_array('school_id', $columns, true);
    echo "has school_id: " . ($hasSchoolId ? 'YES' : 'NO') . "\n";

    $rlsInfo = DB::connection('pgsql_system')->select("
        SELECT relrowsecurity, relforcerowsecurity 
        FROM pg_class 
        WHERE relname = '{$t}'
    ");
    $rlsEnabled = !empty($rlsInfo) && $rlsInfo[0]->relrowsecurity;
    echo "RLS Enabled: " . ($rlsEnabled ? 'YES' : 'NO') . "\n";

    $policies = DB::connection('pgsql_system')->select("
        SELECT polname, pg_get_expr(polqual, polrelid) as qual
        FROM pg_policy p 
        JOIN pg_class c ON c.oid = p.polrelid 
        WHERE c.relname = '{$t}'
    ");
    echo "Policies:\n";
    foreach ($policies as $p) {
        echo "  - Name: {$p->polname} | Expression: {$p->qual}\n";
    }

    $fks = DB::connection('pgsql_system')->select("
        SELECT
            tc.constraint_name, 
            kcu.column_name, 
            ccu.table_name AS foreign_table_name,
            ccu.column_name AS foreign_column_name 
        FROM 
            information_schema.table_constraints AS tc 
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
        WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name='{$t}';
    ");
    echo "Foreign Keys:\n";
    foreach ($fks as $fk) {
        echo "  - {$fk->constraint_name}: {$fk->column_name} -> {$fk->foreign_table_name}({$fk->foreign_column_name})\n";
    }
}
