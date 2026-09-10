<?php

use App\Services\OfficeResetService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Reset the office master data to the five official offices.
     *
     * Every user pointing to any other office is moved to Jakarta
     * (JKT001). Users, attendance and every transaction are preserved;
     * only the removed offices are purged.
     */
    public function up(): void
    {
        app(OfficeResetService::class)->reset();
    }

    /**
     * Reverse the migrations. The reset is destructive by design.
     */
    public function down(): void
    {
        //
    }
};
