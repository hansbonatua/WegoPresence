<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The columns added by this migration.
     *
     * @var array<int, string>
     */
    private array $addedColumns = [];

    /**
     * Whether the password reset tokens table was created by this migration.
     */
    private bool $createdPasswordResetTokens = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable();
                $this->addedColumns[] = 'phone';
            }

            if (! Schema::hasColumn('users', 'password')) {
                $table->string('password');
                $this->addedColumns[] = 'password';
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 20)->default('pending');
                $this->addedColumns[] = 'status';
            }
        });

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });

            $this->createdPasswordResetTokens = true;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ($this->addedColumns as $column) {
                $table->dropColumn($column);
            }
        });

        if ($this->createdPasswordResetTokens) {
            Schema::dropIfExists('password_reset_tokens');
        }
    }
};
