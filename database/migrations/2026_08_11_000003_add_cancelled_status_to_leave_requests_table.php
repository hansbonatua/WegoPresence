<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the leave status enum with the cancelled workflow state.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->extendPostgresStatusConstraint();
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    /**
     * Extend the status column on PostgreSQL. Older Laravel versions
     * create a native enum type, newer versions use a CHECK constraint.
     */
    private function extendPostgresStatusConstraint(): void
    {
        $enumTypeExists = DB::selectOne(
            "SELECT 1 FROM pg_type WHERE typname = 'leave_requests_status'",
        ) !== null;

        if ($enumTypeExists) {
            DB::statement("ALTER TYPE leave_requests_status ADD VALUE IF NOT EXISTS 'cancelled'");
        }

        $constraint = DB::selectOne(
            "SELECT conname FROM pg_constraint WHERE conrelid = CAST('leave_requests' AS regclass) AND contype = 'c' AND pg_get_constraintdef(oid) ILIKE '%status%'",
        );

        if ($constraint === null) {
            return;
        }

        DB::statement("ALTER TABLE leave_requests DROP CONSTRAINT {$constraint->conname}");
        DB::statement(
            "ALTER TABLE leave_requests ADD CONSTRAINT {$constraint->conname} CHECK (status::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'cancelled'::text]))",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
