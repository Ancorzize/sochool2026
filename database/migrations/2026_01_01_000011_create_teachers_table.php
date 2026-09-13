<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('teachers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('teacher_code');
            $table->string('document_type')->default('CC');
            $table->string('document_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('specialty')->nullable();
            $table->text('professional_profile')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE, INACTIVE, ON_LEAVE
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['id', 'school_id']);
            $table->unique(['school_id', 'teacher_code']);
            $table->unique(['school_id', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('teachers');
    }
};
