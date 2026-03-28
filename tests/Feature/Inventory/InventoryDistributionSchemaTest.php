<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryDistributionSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_phase_five_tables_match_required_field_names(): void
    {
        $this->assertTrue(Schema::hasColumns('inventory', [
            'inventory_id',
            'item_id',
            'available_stock',
            'stock_on_hand',
            'reserved_quantity',
            'released_quantity',
            'low_stock_threshold',
            'last_updated',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('inventory_movements', [
            'movement_id',
            'inventory_id',
            'movement_type',
            'reference_type',
            'reference_id',
            'quantity',
            'performed_by',
            'last_updated',
            'movement_date',
            'remarks',
        ]));

        $this->assertTrue(Schema::hasColumns('distribution_records', [
            'distribution_record_id',
            'distribution_code',
            'supply_request_id',
            'delivery_id',
            'office_id',
            'prepared_by',
            'received_by',
            'release_date',
            'storage_location',
            'status',
            'notes',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('distribution_items', [
            'distribution_item_id',
            'distribution_record_id',
            'item_id',
            'received_by',
            'item_name_snapshot',
            'quantity_released',
            'unit',
            'remarks',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('distribution_items', 'items_id'));

        $this->assertTrue(Schema::hasColumns('claims', [
            'claims_id',
            'claim_code',
            'distribution_record_id',
            'claimed_by',
            'office_id',
            'claim_type',
            'claim_date',
            'claim_location',
            'remarks',
            'status',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('claims', 'claim_id'));
    }
}
