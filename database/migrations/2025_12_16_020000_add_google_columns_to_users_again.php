<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'google_access_token')) {
                $table->text('google_access_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'google_refresh_token')) {
                $table->text('google_refresh_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'google_token_expires_at')) {
                $table->timestamp('google_token_expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'google_access_token')) {
                $table->dropColumn('google_access_token');
            }
            if (Schema::hasColumn('users', 'google_refresh_token')) {
                $table->dropColumn('google_refresh_token');
            }
            if (Schema::hasColumn('users', 'google_token_expires_at')) {
                $table->dropColumn('google_token_expires_at');
            }
        });
    }
};
