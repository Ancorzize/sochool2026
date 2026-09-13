<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('achievement_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('code')->nullable();
            $table->text('description'); // e.g., "Resuelve problemas con fracciones complejas"
            $table->timestamps();

            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('achievement_indicators');
    }
};
