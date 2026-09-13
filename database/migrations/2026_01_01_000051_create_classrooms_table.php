<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE TABLE classrooms (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id bigint NOT NULL,
                campus_id bigint NOT NULL,
                code varchar(30) NOT NULL,
                name varchar(100) NOT NULL,
                type varchar(30) NOT NULL DEFAULT 'CLASSROOM',
                capacity integer NOT NULL DEFAULT 40,
                status varchar(20) NOT NULL DEFAULT 'ACTIVE',
                created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_classrooms_school_campus FOREIGN KEY (campus_id, school_id) REFERENCES campuses(id, school_id) ON DELETE CASCADE,
                CONSTRAINT unique_campus_classroom_code UNIQUE (school_id, campus_id, code),
                CONSTRAINT unique_classroom_tenant_campus_id UNIQUE (id, school_id, campus_id)
            );
        ");

        $conn->statement("ALTER TABLE classrooms ENABLE ROW LEVEL SECURITY;");
        $conn->statement("ALTER TABLE classrooms FORCE ROW LEVEL SECURITY;");

        $conn->statement("
            CREATE POLICY tenant_isolation_policy ON classrooms FOR ALL
            USING (school_id = get_current_school_id() OR (current_setting('app.bypass_rls', true) = 'on' AND session_user = 'app_system'));
        ");

        try {
            $conn->statement("GRANT ALL ON TABLE classrooms TO app_user;");
            $conn->statement("GRANT ALL ON SEQUENCE classrooms_id_seq TO app_user;");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP TABLE IF EXISTS classrooms CASCADE;");
    }
};
