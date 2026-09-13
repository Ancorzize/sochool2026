<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('academic_periods')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('report_card_templates')->nullOnDelete();
            $table->integer('version')->default(1);
            $table->boolean('is_latest')->default(true);
            $table->string('status')->default('GENERATED'); // DRAFT, GENERATED, PUBLISHED, SUPERSEDED, LOCKED
            $table->text('regeneration_reason')->nullable();
            $table->foreignId('parent_report_card_id')->nullable()->constrained('report_cards')->nullOnDelete();
            $table->jsonb('data_snapshot');
            $table->unsignedBigInteger('pdf_media_file_id')->nullable();
            $table->timestamps();

            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('report_cards');
    }
};
