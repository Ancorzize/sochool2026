<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("
            CREATE OR REPLACE FUNCTION get_user_active_memberships(p_user_id bigint)
            RETURNS TABLE (
                school_id bigint,
                school_name text,
                school_code text,
                school_slug text,
                status text
            ) 
            LANGUAGE plpgsql
            STABLE
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    su.school_id,
                    s.name::text,
                    s.code::text,
                    s.slug::text,
                    su.status::text
                FROM school_users su
                JOIN schools s ON s.id = su.school_id
                WHERE su.user_id = p_user_id
                  AND su.status = 'ACTIVE'
                  AND s.status = 'ACTIVE';
            END;
            $$;
        ");

        try {
            $conn->statement("REVOKE EXECUTE ON FUNCTION get_user_active_memberships(bigint) FROM PUBLIC;");
            $conn->statement("GRANT EXECUTE ON FUNCTION get_user_active_memberships(bigint) TO app_user;");
        } catch (\Throwable $e) {
            // Ignore if app_user role does not exist
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');
        $conn->statement("DROP FUNCTION IF EXISTS get_user_active_memberships(bigint);");
    }
};
