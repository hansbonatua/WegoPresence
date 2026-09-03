<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename the attendance status "on_time" to "present" and narrow the
     * allowed values to present, late and absent.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->migratePostgres();
        } else {
            $this->migrateGeneric();
        }
    }

    /**
     * PostgreSQL stores the enum as a named CHECK constraint, so the
     * constraint must be dropped, the data converted, and the constraint
     * recreated with the new allowed values.
     */
    private function migratePostgres(): void
    {
        DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_attendance_status_check');

        DB::table('attendances')
            ->where('attendance_status', 'on_time')
            ->update(['attendance_status' => 'present']);

        DB::statement(
            'ALTER TABLE attendances ADD CONSTRAINT attendances_attendance_status_check '
            ."CHECK (attendance_status IN ('present', 'late', 'absent'))",
        );
    }

    /**
     * SQLite and other drivers recreate the column via the schema builder:
     * widen, convert the data, then narrow.
     */
    private function migrateGeneric(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('attendance_status', [
                'present',
                'on_time',
                'late',
                'absent',
            ])->default('absent')->change();
        });

        DB::table('attendances')
            ->where('attendance_status', 'on_time')
            ->update(['attendance_status' => 'present']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('attendance_status', [
                'present',
                'late',
                'absent',
            ])->default('absent')->change();
        });
    }

    /**
     * Reverse the attendance status enum migration.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS attendances_attendance_status_check');

            DB::table('attendances')
                ->where('attendance_status', 'present')
                ->update(['attendance_status' => 'on_time']);

            DB::statement(
                'ALTER TABLE attendances ADD CONSTRAINT attendances_attendance_status_check '
                ."CHECK (attendance_status IN ('on_time', 'late', 'absent'))",
            );

            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('attendance_status', [
                'present',
                'on_time',
                'late',
                'absent',
            ])->default('absent')->change();
        });

        DB::table('attendances')
            ->where('attendance_status', 'present')
            ->update(['attendance_status' => 'on_time']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('attendance_status', [
                'on_time',
                'late',
                'absent',
            ])->default('absent')->change();
        });
    }
};
