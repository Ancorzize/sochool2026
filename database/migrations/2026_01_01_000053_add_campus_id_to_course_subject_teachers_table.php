<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            ALTER TABLE course_subject_teachers 
            ADD COLUMN IF NOT EXISTS campus_id bigint;
        ");

        $conn->statement("
            ALTER TABLE course_subject_teachers
            ADD CONSTRAINT fk_cst_course_tenant_campus FOREIGN KEY (course_id, school_id, campus_id) REFERENCES courses(id, school_id, campus_id) ON DELETE RESTRICT;
        ");
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("
            ALTER TABLE course_subject_teachers 
            DROP CONSTRAINT IF EXISTS fk_cst_course_tenant_campus,
            DROP COLUMN IF EXISTS campus_id;
        ");
    }
};
