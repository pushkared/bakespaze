<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('status')->default('accepted')->after('role');
            $table->timestamp('accepted_at')->nullable()->after('status');
        });

        DB::table('memberships')->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['status', 'accepted_at']);
        });
    }
};
