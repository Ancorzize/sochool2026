<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('report_card_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('report_card_sections')->cascadeOnDelete();
            $table->string('field_key'); // e.g. show_weighted_average, show_ranking, show_teacher_comments
            $table->string('label');
            $table->boolean('is_visible')->default(true);
            $table->integer('field_order')->default(1);
            $table->timestamps();

            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('report_card_fields');
    }
};
