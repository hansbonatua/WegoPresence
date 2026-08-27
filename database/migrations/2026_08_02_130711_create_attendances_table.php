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
        Schema::create('attendances', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Attendance Date
            $table->date('attendance_date');

            /*
            |--------------------------------------------------------------------------
            | Check In
            |--------------------------------------------------------------------------
            */

            $table->time('check_in_time')->nullable();
            $table->string('check_in_photo')->nullable();

            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();

            $table->text('check_in_address')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Check Out
            |--------------------------------------------------------------------------
            */

            $table->time('check_out_time')->nullable();
            $table->string('check_out_photo')->nullable();

            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();

            $table->text('check_out_address')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Attendance Result
            |--------------------------------------------------------------------------
            */

            $table->enum('attendance_status', [
                'on_time',
                'late',
                'absent',
            ])->default('absent');

            $table->enum('branch_area', [
                'inside_branch_area',
                'outside_branch_area',
            ])->nullable();

            $table->text('notes')->nullable();

            $table->unique(['user_id', 'attendance_date']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
