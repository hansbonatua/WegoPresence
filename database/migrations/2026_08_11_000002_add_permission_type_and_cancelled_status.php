<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the permission type column and extend the status enum with
     * the cancelled workflow state.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('type')->default('personal');
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->extendPostgresStatusConstraint();
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('permissions', function (Blueprint $table) {
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
            "SELECT 1 FROM pg_type WHERE typname = 'permissions_status'",
        ) !== null;

        if ($enumTypeExists) {
            DB::statement("ALTER TYPE permissions_status ADD VALUE IF NOT EXISTS 'cancelled'");
        }

        $constraint = DB::selectOne(
            "SELECT conname FROM pg_constraint WHERE conrelid = CAST('permissions' AS regclass) AND contype = 'c' AND pg_get_constraintdef(oid) ILIKE '%status%'",
        );

        if ($constraint === null) {
            return;
        }

        DB::statement("ALTER TABLE permissions DROP CONSTRAINT {$constraint->conname}");
        DB::statement(
            "ALTER TABLE permissions ADD CONSTRAINT {$constraint->conname} CHECK (status::text = ANY (ARRAY['pending'::text, 'approved'::text, 'rejected'::text, 'cancelled'::text]))",
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
