<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_code');
            $table->string('document_type')->default('TI');
            $table->string('document_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality')->default('Colombiana');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->jsonb('medical_info')->nullable();
            $table->jsonb('emergency_contact')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->date('withdrawal_date')->nullable();
            $table->string('academic_status')->default('ACTIVE'); // ACTIVE, GRADUATED, WITHDRAWN, SUSPENDED
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['id', 'school_id']);
            $table->unique(['school_id', 'student_code']);
            $table->unique(['school_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('students');
    }
};
