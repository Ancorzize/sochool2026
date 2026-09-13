<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('report_card_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('report_card_templates')->cascadeOnDelete();
            $table->string('section_type'); // HEADER, STUDENT_INFO, GRADES_SUMMARY, ATTENDANCE_SUMMARY, OBSERVATIONS, SIGNATURES
            $table->string('title');
            $table->integer('section_order')->default(1);
            $table->jsonb('config')->nullable();
            $table->timestamps();

            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('report_card_sections');
    }
};
