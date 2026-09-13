<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE TABLE schedules (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id bigint NOT NULL,
                campus_id bigint NOT NULL,
                academic_year_id bigint NOT NULL,
                course_id bigint NOT NULL,
                subject_id bigint NOT NULL,
                teacher_id bigint NOT NULL,
                classroom_id bigint,
                day_of_week integer NOT NULL,
                start_time time NOT NULL,
                end_time time NOT NULL,
                valid_from date NOT NULL,
                valid_until date,
                status varchar(20) NOT NULL DEFAULT 'ACTIVE',
                created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_schedules_course FOREIGN KEY (course_id, school_id, campus_id) REFERENCES courses(id, school_id, campus_id) ON DELETE RESTRICT,
                CONSTRAINT fk_schedules_classroom FOREIGN KEY (classroom_id, school_id, campus_id) REFERENCES classrooms(id, school_id, campus_id) ON DELETE RESTRICT,
                CONSTRAINT fk_schedules_teacher FOREIGN KEY (teacher_id, school_id) REFERENCES teachers(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT fk_schedules_subject FOREIGN KEY (subject_id, school_id) REFERENCES subjects(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT check_schedule_times CHECK (start_time < end_time),
                CONSTRAINT check_schedule_dates CHECK (valid_until IS NULL OR valid_from <= valid_until)
            );
        ");

        $conn->statement("ALTER TABLE schedules ENABLE ROW LEVEL SECURITY;");
        $conn->statement("ALTER TABLE schedules FORCE ROW LEVEL SECURITY;");

        $conn->statement("
            CREATE POLICY tenant_isolation_policy ON schedules FOR ALL
            USING (school_id = get_current_school_id() OR (current_setting('app.bypass_rls', true) = 'on' AND session_user = 'app_system'));
        ");

        try {
            $conn->statement("GRANT ALL ON TABLE schedules TO app_user;");
            $conn->statement("GRANT ALL ON SEQUENCE schedules_id_seq TO app_user;");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP TABLE IF EXISTS schedules CASCADE;");
    }
};
