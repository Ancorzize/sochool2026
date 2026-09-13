<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name'); // e.g., "Escala Numérica 1.0 - 5.0", "Escala Nacional Cualitativa"
            $table->string('scale_type')->default('NUMERIC'); // NUMERIC, QUALITATIVE, LETTER
            $table->decimal('min_score', 5, 2)->default(1.00);
            $table->decimal('max_score', 5, 2)->default(5.00);
            $table->decimal('passing_score', 5, 2)->default(3.00);
            $table->integer('decimal_places')->default(1);
            $table->string('rounding_rule')->default('HALF_UP'); // HALF_UP, FLOOR, CEIL
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('grading_scales');
    }
};
