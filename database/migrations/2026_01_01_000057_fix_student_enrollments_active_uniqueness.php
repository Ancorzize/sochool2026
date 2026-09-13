<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        // Drop unconditional uniqueness constraint from Phase 1
        $conn->statement("
            ALTER TABLE student_enrollments 
            DROP CONSTRAINT IF EXISTS student_enrollments_school_id_student_id_academic_year_id_unique;
        ");

        // Create partial unique index allowing historical enrollments (TRANSFERRED, WITHDRAWN, etc.)
        // while strictly enforcing at most 1 ACTIVE enrollment per student per academic year per tenant school
        $conn->statement("
            CREATE UNIQUE INDEX IF NOT EXISTS unique_active_student_enrollment 
            ON student_enrollments (school_id, student_id, academic_year_id) 
            WHERE status = 'ACTIVE' AND deleted_at IS NULL;
        ");
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            DROP INDEX IF EXISTS unique_active_student_enrollment;
        ");

        $conn->statement("
            ALTER TABLE student_enrollments 
            ADD CONSTRAINT student_enrollments_school_id_student_id_academic_year_id_unique 
            UNIQUE (school_id, student_id, academic_year_id);
        ");
    }
};
