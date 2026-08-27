<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the sick leave status enum with the cancelled workflow
     * state and allow sick leaves without a medical certificate.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->extendPostgresStatusConstraint();

            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->string('medical_certificate')->nullable()->change();
            });
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('sick_leaves', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
                $table->string('medical_certificate')->nullable()->change();
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
            "SELECT 1 FROM pg_type WHERE typname = 'sick_leaves_status'",
        ) !== null;

        if ($enumTypeExists) {
            DB::statement("ALTER TYPE sick_leaves_status ADD VALUE IF NOT EXISTS 'cancelled'");
        }

        $constraint = DB::selectOne(
            "SELECT conname FROM pg_constraint WHERE conrelid = CAST('sick_leaves' AS regclass) AND contype = 'c' AND pg_get_constraintdef(oid) ILIKE '%status%'",
        );

        if ($constraint === null) {
            return;
        }

        DB::statement("ALTER TABLE sick_leaves DROP CONSTRAINT {$constraint->conname}");
        DB::statement(
            "ALTER TABLE sick_leaves ADD CONSTRAINT {$constraint->conname} CHECK (status::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'cancelled'::text]))",
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
