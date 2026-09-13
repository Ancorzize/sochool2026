<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('entered_value'); // e.g. "4.5", "100", "A", "Sobresaliente"
            $table->string('normalized_value')->nullable();
            $table->decimal('equivalent_numeric_value', 5, 2)->nullable(); // Nullable for qualitative scales
            $table->foreignId('grading_scale_item_id')->nullable()->constrained('grading_scale_items')->nullOnDelete();
            $table->string('display_value')->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->text('comments')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'assessment_id', 'student_id']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('student_grades');
    }
};
