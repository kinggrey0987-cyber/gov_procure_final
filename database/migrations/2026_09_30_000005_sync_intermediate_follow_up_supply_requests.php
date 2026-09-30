<?php

use App\Services\Procurement\ProcurementStatusSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        try {
            app(ProcurementStatusSyncService::class)->syncAllReceivedSupplyRequests();
        } catch (\Throwable $e) {
            // Silently catch in migration if DB environment is empty/migrating from scratch
        }
    }

    public function down(): void
    {
    }
};
