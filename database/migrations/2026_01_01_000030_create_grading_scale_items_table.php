<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('grading_scale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->string('name'); // e.g., "Sobresaliente", "Alto", "Bajo"
            $table->string('code')->nullable();
            $table->decimal('min_value', 5, 2)->nullable();
            $table->decimal('max_value', 5, 2)->nullable();
            $table->decimal('equivalent_numeric_value', 5, 2)->nullable(); // Nullable for qualitative pure scales
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->integer('item_order')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'grading_scale_id', 'name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('grading_scale_items');
    }
};
