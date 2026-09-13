<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('attendance_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('code'); // e.g. PRESENT, ABSENT_UNJUST, ABSENT_JUST, LATE
            $table->string('name'); // e.g., "Presente", "Falta Injustificada", "Falta Justificada", "Retardo"
            $table->boolean('is_absence')->default(false);
            $table->boolean('is_tardiness')->default(false);
            $table->boolean('is_justified')->default(false);
            $table->boolean('requires_justification')->default(false);
            $table->string('color')->default('#10B981');
            $table->integer('status_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('attendance_statuses');
    }
};
