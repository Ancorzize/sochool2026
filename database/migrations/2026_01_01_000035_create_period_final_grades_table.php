<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('period_final_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->string('final_entered_value')->nullable();
            $table->decimal('numeric_score', 5, 2)->nullable();
            $table->foreignId('grading_scale_item_id')->nullable()->constrained('grading_scale_items')->nullOnDelete();
            $table->boolean('is_recovery')->default(false);
            $table->decimal('recovery_score', 5, 2)->nullable();
            $table->string('status')->default('FINAL'); // DRAFT, FINAL
            $table->timestamps();

            $table->unique(['school_id', 'course_id', 'subject_id', 'student_id', 'academic_period_id'], 'uq_period_final_grade');
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('period_final_grades');
    }
};
