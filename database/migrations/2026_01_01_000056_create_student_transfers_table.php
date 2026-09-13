<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE TABLE student_transfers (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id bigint NOT NULL,
                student_id bigint NOT NULL,
                academic_year_id bigint NOT NULL,
                transfer_type varchar(30) NOT NULL, -- COURSE_TRANSFER, CAMPUS_TRANSFER, SCHOOL_TRANSFER
                from_campus_id bigint,
                to_campus_id bigint,
                from_course_id bigint,
                to_course_id bigint,
                effective_date date NOT NULL,
                reason text,
                notes text,
                created_by_user_id bigint NOT NULL,
                created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_transfers_student FOREIGN KEY (student_id, school_id) REFERENCES students(id, school_id) ON DELETE CASCADE,
                CONSTRAINT fk_transfers_from_campus FOREIGN KEY (from_campus_id, school_id) REFERENCES campuses(id, school_id) ON DELETE RESTRICT,
                CONSTRAINT fk_transfers_to_campus FOREIGN KEY (to_campus_id, school_id) REFERENCES campuses(id, school_id) ON DELETE RESTRICT
            );
        ");

        $conn->statement("ALTER TABLE student_transfers ENABLE ROW LEVEL SECURITY;");
        $conn->statement("ALTER TABLE student_transfers FORCE ROW LEVEL SECURITY;");

        $conn->statement("
            CREATE POLICY tenant_isolation_policy ON student_transfers FOR ALL
            USING (school_id = get_current_school_id() OR (current_setting('app.bypass_rls', true) = 'on' AND session_user = 'app_system'));
        ");

        try {
            $conn->statement("GRANT ALL ON TABLE student_transfers TO app_user;");
            $conn->statement("GRANT ALL ON SEQUENCE student_transfers_id_seq TO app_user;");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP TABLE IF EXISTS student_transfers CASCADE;");
    }
};
