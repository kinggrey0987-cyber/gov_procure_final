<?php

use App\Services\Procurement\ProcurementStatusSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Re-evaluate and sync all supply requests and follow-ups across consolidated families
        app(ProcurementStatusSyncService::class)->syncAllReceivedSupplyRequests();
    }

    public function down(): void
    {
        // No rollback needed for data normalization
    }
};
