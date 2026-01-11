<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->unsignedInteger('punch_out_after_hours')->default(8)->after('punch_in_end');
            $table->time('auto_punch_out_time')->default('23:55:00')->after('punch_out_after_hours');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['punch_out_after_hours', 'auto_punch_out_time']);
        });
    }
};
