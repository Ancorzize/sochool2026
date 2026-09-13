<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            ALTER TABLE student_enrollments 
            ADD COLUMN IF NOT EXISTS campus_id bigint;
        ");

        $conn->statement("
            ALTER TABLE student_enrollments
            ADD CONSTRAINT fk_se_campus FOREIGN KEY (campus_id, school_id) REFERENCES campuses(id, school_id) ON DELETE RESTRICT,
            ADD CONSTRAINT unique_se_tenant_campus_id UNIQUE (id, school_id, campus_id);
        ");

        // Add campus_id and composite FK to course_enrollments if missing
        $conn->statement("
            ALTER TABLE course_enrollments
            ADD COLUMN IF NOT EXISTS campus_id bigint;
        ");

        $conn->statement("
            ALTER TABLE course_enrollments
            ADD CONSTRAINT fk_ce_se_tenant_campus FOREIGN KEY (student_enrollment_id, school_id, campus_id) REFERENCES student_enrollments(id, school_id, campus_id) ON DELETE RESTRICT,
            ADD CONSTRAINT fk_ce_course_tenant_campus FOREIGN KEY (course_id, school_id, campus_id) REFERENCES courses(id, school_id, campus_id) ON DELETE RESTRICT;
        ");
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            ALTER TABLE course_enrollments
            DROP CONSTRAINT IF EXISTS fk_ce_se_tenant_campus,
            DROP CONSTRAINT IF EXISTS fk_ce_course_tenant_campus,
            DROP COLUMN IF EXISTS campus_id;
        ");

        $conn->statement("
            ALTER TABLE student_enrollments
            DROP CONSTRAINT IF EXISTS fk_se_campus,
            DROP CONSTRAINT IF EXISTS unique_se_tenant_campus_id,
            DROP COLUMN IF EXISTS campus_id;
        ");
    }
};
