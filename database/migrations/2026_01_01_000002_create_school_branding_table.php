<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('school_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('short_name')->nullable();
            $table->string('primary_color')->default('#1E40AF');
            $table->string('secondary_color')->default('#0D9488');
            $table->string('accent_color')->default('#F59E0B');
            $table->string('background_color')->default('#F8FAFC');
            $table->string('menu_color')->default('#0F172A');
            $table->text('login_message')->nullable();
            $table->text('welcome_text')->nullable();
            $table->string('theme')->default('light');
            $table->jsonb('custom_css_tokens')->nullable();
            $table->timestamps();

            $table->unique('school_id');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('school_branding');
    }
};
