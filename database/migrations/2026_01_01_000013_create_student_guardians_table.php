<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->string('relationship')->default('PADRE'); // PADRE, MADRE, ACUDIENTE_LEGAL, OTRO
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_authorized_pickup')->default(true);
            $table->boolean('receives_communications')->default(true);
            $table->boolean('receives_report_cards')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'guardian_id']);
            $table->unique(['id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('student_guardians');
    }
};
