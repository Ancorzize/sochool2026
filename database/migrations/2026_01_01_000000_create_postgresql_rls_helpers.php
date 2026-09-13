<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE OR REPLACE FUNCTION get_current_school_id() RETURNS bigint AS $$
            BEGIN
                RETURN NULLIF(current_setting('app.current_school_id', true), '')::bigint;
            EXCEPTION WHEN OTHERS THEN
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql STABLE SECURITY DEFINER;
        ");

        try {
            $conn->statement("GRANT EXECUTE ON FUNCTION get_current_school_id() TO app_user;");
            $conn->statement("ALTER ROLE app_user NOSUPERUSER NOBYPASSRLS;");
            $conn->statement("GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO app_user;");
            $conn->statement("GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO app_user;");
            $conn->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO app_user;");
            $conn->statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO app_user;");
        } catch (\Throwable $e) {
            // Ignore if role does not exist
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP FUNCTION IF EXISTS get_current_school_id();");
    }
};
