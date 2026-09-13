<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('grading_scale_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->foreignId('educational_level_id')->nullable()->constrained('educational_levels')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->integer('priority_score')->default(0);
            $table->timestamps();

            $table->unique(['id', 'school_id']);
        });

        // Unique index preventing ambiguous assignments with exact scope combination
        DB::connection('pgsql_system')->statement("
            CREATE UNIQUE INDEX uq_grading_scale_scope ON grading_scale_assignments (
                school_id,
                COALESCE(subject_id, 0),
                COALESCE(academic_period_id, 0),
                COALESCE(course_id, 0),
                COALESCE(grade_id, 0),
                COALESCE(educational_level_id, 0)
            );
        ");
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('grading_scale_assignments');
    }
};
