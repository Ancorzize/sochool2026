<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        // Check if campus_id column already exists or needs to be populated
        $conn->statement("
            ALTER TABLE courses 
            ADD COLUMN IF NOT EXISTS campus_id bigint;
        ");

        // Set default campus for any existing courses if needed
        $conn->statement("
            DO $$
            DECLARE
                default_campus_id bigint;
            BEGIN
                SELECT id INTO default_campus_id FROM campuses LIMIT 1;
                IF default_campus_id IS NOT NULL THEN
                    UPDATE courses SET campus_id = default_campus_id WHERE campus_id IS NULL;
                END IF;
            END $$;
        ");

        // Drop old course name uniqueness constraint if present and add campus-scoped uniqueness
        $conn->statement("
            ALTER TABLE courses
            DROP CONSTRAINT IF EXISTS courses_school_id_academic_year_id_name_unique;
        ");

        $conn->statement("
            ALTER TABLE courses
            ADD CONSTRAINT fk_courses_campus FOREIGN KEY (campus_id, school_id) REFERENCES campuses(id, school_id) ON DELETE RESTRICT,
            ADD CONSTRAINT unique_course_tenant_campus_id UNIQUE (id, school_id, campus_id),
            ADD CONSTRAINT unique_course_campus_name UNIQUE (school_id, campus_id, academic_year_id, name);
        ");
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("
            ALTER TABLE courses 
            DROP CONSTRAINT IF EXISTS fk_courses_campus,
            DROP CONSTRAINT IF EXISTS unique_course_tenant_campus_id,
            DROP CONSTRAINT IF EXISTS unique_course_campus_name,
            DROP COLUMN IF EXISTS campus_id;
        ");
    }
};
