<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');
            $table->string('number_format')->default('2,.,');
            $table->string('locale')->default('es');
            $table->text('encrypted_mail_config')->nullable();
            $table->jsonb('academic_config')->nullable();
            $table->jsonb('attendance_config')->nullable();
            $table->jsonb('notification_config')->nullable();
            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('school_settings');
    }
};
