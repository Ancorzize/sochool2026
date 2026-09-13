<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('academic_plan_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_plan_id')->constrained('academic_plans')->cascadeOnDelete();
            $table->foreignId('knowledge_area_id')->nullable()->constrained('knowledge_areas')->nullOnDelete();
            $table->string('subject_name');
            $table->string('subject_code')->nullable();
            $table->integer('hours_per_week')->default(2);
            $table->decimal('weight_in_area', 5, 2)->default(100.00);
            $table->timestamps();

            $table->unique(['school_id', 'academic_plan_id', 'subject_name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('academic_plan_subjects');
    }
};
