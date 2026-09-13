<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->table('report_card_assignments', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('course_id')->constrained('subjects')->nullOnDelete();
        });

        DB::connection('pgsql_system')->statement("
            CREATE UNIQUE INDEX uq_report_card_assignment_scope ON report_card_assignments (
                school_id,
                COALESCE(subject_id, 0),
                COALESCE(course_id, 0),
                COALESCE(grade_id, 0),
                COALESCE(educational_level_id, 0)
            );
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_system')->statement("DROP INDEX IF EXISTS uq_report_card_assignment_scope;");

        Schema::connection('pgsql_system')->table('report_card_assignments', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });
    }
};
