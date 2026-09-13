<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE TABLE campuses (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                school_id bigint NOT NULL,
                name varchar(150) NOT NULL,
                code varchar(30) NOT NULL,
                address text,
                phone varchar(50),
                email varchar(150),
                status varchar(20) NOT NULL DEFAULT 'ACTIVE',
                is_main boolean NOT NULL DEFAULT false,
                created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_campuses_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
                CONSTRAINT unique_school_campus_code UNIQUE (school_id, code),
                CONSTRAINT unique_school_campus_name UNIQUE (school_id, name),
                CONSTRAINT unique_school_campus_id UNIQUE (school_id, id)
            );
        ");

        $conn->statement("
            CREATE UNIQUE INDEX unique_school_main_campus ON campuses (school_id) WHERE is_main = true;
        ");

        $conn->statement("ALTER TABLE campuses ENABLE ROW LEVEL SECURITY;");
        $conn->statement("ALTER TABLE campuses FORCE ROW LEVEL SECURITY;");

        $conn->statement("
            CREATE POLICY tenant_isolation_policy ON campuses FOR ALL
            USING (school_id = get_current_school_id() OR (current_setting('app.bypass_rls', true) = 'on' AND session_user = 'app_system'));
        ");

        try {
            $conn->statement("GRANT ALL ON TABLE campuses TO app_user;");
            $conn->statement("GRANT ALL ON SEQUENCE campuses_id_seq TO app_user;");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP TABLE IF EXISTS campuses CASCADE;");
    }
};
