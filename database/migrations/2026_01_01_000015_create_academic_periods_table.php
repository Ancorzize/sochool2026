<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name'); // e.g., "Periodo 1"
            $table->integer('period_order')->default(1);
            $table->decimal('weight_percentage', 5, 2)->default(25.00);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('OPEN'); // DRAFT, OPEN, CLOSED, LOCKED
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'period_order']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('academic_periods');
    }
};
