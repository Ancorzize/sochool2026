<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('educational_level_id')->constrained('educational_levels')->cascadeOnDelete();
            $table->string('name'); // e.g., "Grado 6°"
            $table->string('code')->nullable();
            $table->integer('grade_order')->default(1);
            $table->timestamps();

            $table->unique(['school_id', 'educational_level_id', 'name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('grades');
    }
};
