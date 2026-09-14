<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the transaction timezone to every attendance record.
     *
     * The column is created nullable, existing rows are backfilled with
     * the application timezone (the only timezone the system knew about
     * before timezone awareness), and only then is the column made
     * non-nullable so every record has a timezone.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('attendance_timezone', 50)
                ->nullable()
                ->after('attendance_status');
        });

        DB::table('attendances')
            ->whereNull('attendance_timezone')
            ->update(['attendance_timezone' => config('app.timezone')]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('attendance_timezone', 50)
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('attendance_timezone');
        });
    }
};
