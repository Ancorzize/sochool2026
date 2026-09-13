<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pgsql_system')->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        // Grant access to app_user
        try {
            \Illuminate\Support\Facades\DB::connection('pgsql_system')->statement("GRANT ALL ON TABLE personal_access_tokens TO app_user;");
            \Illuminate\Support\Facades\DB::connection('pgsql_system')->statement("GRANT ALL ON SEQUENCE personal_access_tokens_id_seq TO app_user;");
        } catch (\Throwable $e) {
            // Ignore if app_user does not exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_system')->dropIfExists('personal_access_tokens');
    }
};
