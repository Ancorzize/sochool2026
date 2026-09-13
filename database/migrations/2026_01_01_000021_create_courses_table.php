<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->foreignId('director_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('name'); // e.g., "6-A"
            $table->string('shift')->default('MAÑANA'); // MAÑANA, TARDE, NOCTURNA, COMPLETA
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('courses');
    }
};
