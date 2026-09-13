<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('knowledge_area_id')->nullable()->constrained('knowledge_areas')->nullOnDelete();
            $table->string('name'); // e.g., "Matemáticas"
            $table->string('code')->nullable();
            $table->string('color')->default('#3B82F6');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('subjects');
    }
};
