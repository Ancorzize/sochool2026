<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('pgsql_system');

        // 1. Add nullable school_id column
        Schema::connection('pgsql_system')->table('school_user_roles', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('school_user_id');
        });

        // 2. Populate school_id from school_users table
        $conn->statement("
            UPDATE school_user_roles sur
            SET school_id = su.school_id
            FROM school_users su
            WHERE sur.school_user_id = su.id
              AND sur.school_id IS NULL;
        ");

        // 3. Alter school_id to NOT NULL
        $conn->statement("ALTER TABLE school_user_roles ALTER COLUMN school_id SET NOT NULL;");

        // 4. Create foreign key to schools table
        Schema::connection('pgsql_system')->table('school_user_roles', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        // 5. Create Composite Foreign Keys & Unique Constraint
        $conn->statement("
            ALTER TABLE school_user_roles
            ADD CONSTRAINT fk_sur_school_user_tenant
            FOREIGN KEY (school_user_id, school_id)
            REFERENCES school_users(id, school_id)
            ON DELETE CASCADE;
        ");

        $conn->statement("
            ALTER TABLE school_user_roles
            ADD CONSTRAINT fk_sur_role_tenant
            FOREIGN KEY (role_id, school_id)
            REFERENCES roles(id, school_id)
            ON DELETE CASCADE;
        ");

        $conn->statement("
            ALTER TABLE school_user_roles
            ADD CONSTRAINT unique_school_user_role_tenant
            UNIQUE (school_id, school_user_id, role_id);
        ");

        // 6. Enable RLS and Create Tenant Isolation Policy
        $conn->statement("ALTER TABLE school_user_roles ENABLE ROW LEVEL SECURITY;");
        $conn->statement("ALTER TABLE school_user_roles FORCE ROW LEVEL SECURITY;");
        $conn->statement("DROP POLICY IF EXISTS tenant_isolation_policy ON school_user_roles;");
        $conn->statement("
            CREATE POLICY tenant_isolation_policy ON school_user_roles
            FOR ALL
            USING (
                school_id = get_current_school_id()
                OR (
                    current_setting('app.bypass_rls', true) = 'on'
                    AND session_user = 'app_system'
                )
            );
        ");

        // 7. Grant privileges to app_user
        try {
            $conn->statement("GRANT ALL PRIVILEGES ON TABLE school_user_roles TO app_user;");
        } catch (\Throwable $e) {
            // Ignore if role does not exist
        }
    }

    public function down(): void
    {
        $conn = DB::connection('pgsql_system');

        $conn->statement("DROP POLICY IF EXISTS tenant_isolation_policy ON school_user_roles;");
        $conn->statement("ALTER TABLE school_user_roles DISABLE ROW LEVEL SECURITY;");

        $conn->statement("ALTER TABLE school_user_roles DROP CONSTRAINT IF EXISTS unique_school_user_role_tenant;");
        $conn->statement("ALTER TABLE school_user_roles DROP CONSTRAINT IF EXISTS fk_sur_role_tenant;");
        $conn->statement("ALTER TABLE school_user_roles DROP CONSTRAINT IF EXISTS fk_sur_school_user_tenant;");

        Schema::connection('pgsql_system')->table('school_user_roles', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
