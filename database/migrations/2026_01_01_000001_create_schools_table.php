<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('schools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Colombia');
            $table->string('postal_code')->nullable();
            $table->string('timezone')->default('America/Bogota');
            $table->string('language')->default('es');
            $table->string('currency')->default('COP');
            $table->string('status')->default('ACTIVE'); // ACTIVE, INACTIVE, SUSPENDED
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('schools');
    }
};
