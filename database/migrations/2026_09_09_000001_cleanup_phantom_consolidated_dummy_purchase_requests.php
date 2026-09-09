<?php

use App\Models\PurchaseRequest;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean up any historical phantom dummy PurchaseRequest records that were
        // generated with 0 items during consolidation tracking and have no purchase orders attached.
        PurchaseRequest::query()
            ->where('remarks', 'like', 'Linked to consolidated PR-%')
            ->whereDoesntHave('items')
            ->whereDoesntHave('purchaseOrders')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse action needed for phantom empty rows.
    }
};
