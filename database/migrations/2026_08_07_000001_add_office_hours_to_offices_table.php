<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('status');
            $table->time('end_time')->nullable()->after('start_time');
        });

        DB::table('offices')
            ->whereNull('start_time')
            ->update([
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
