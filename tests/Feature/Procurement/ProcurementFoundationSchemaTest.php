<?php

namespace Tests\Feature\Procurement;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_procurement_tables_match_phase_three_erd_field_names(): void
    {
        $this->assertTrue(Schema::hasColumns('purchase_requests', [
            'purchase_request_id',
            'supply_request_id',
            'office_id',
            'requested_by',
            'status',
            'remarks',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_request_items', [
            'purchase_request_item_id',
            'purchase_request_id',
            'item_id',
            'quantity_requested',
            'remarks',
        ]));

        $this->assertFalse(Schema::hasColumn('purchase_request_items', 'items_id'));

        $this->assertTrue(Schema::hasColumns('suppliers', [
            'supplier_id',
            'supplier_name',
            'contact_person',
            'email',
            'phone',
            'address',
            'status',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_orders', [
            'purchase_order_id',
            'purchase_request_id',
            'supply_request_id',
            'supplier_id',
            'created_by',
            'po_number',
            'procurement_method',
            'status',
            'expected_delivery_date',
            'total_amount',
            'terms_notes',
            'remarks',
        ]));

        $this->assertTrue(Schema::hasColumns('purchase_order_items', [
            'purchase_order_item_id',
            'purchase_order_id',
            'supply_request_item_id',
            'item_id',
            'item_name_snapshot',
            'specification',
            'quantity_ordered',
            'unit',
            'unit_price',
            'total_price',
        ]));

        $this->assertFalse(Schema::hasColumn('purchase_order_items', 'items_id'));
    }
}
