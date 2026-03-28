<?php

namespace Tests\Feature\Office;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficeTransactionalSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_ppmp_items_and_supply_request_items_use_item_id_and_item_category_id_column_names(): void
    {
        $this->assertTrue(Schema::hasColumn('supply_requests', 'ppmp_id'));
        $this->assertFalse(Schema::hasColumn('supply_requests', 'ppmp_item_id'));

        $this->assertTrue(Schema::hasColumns('ppmp_items', [
            'item_id',
            'item_category_id',
            'estimated_budget',
        ]));

        $this->assertFalse(Schema::hasColumn('ppmp_items', 'items_id'));

        $this->assertTrue(Schema::hasColumns('supply_request_items', [
            'item_id',
            'ppmp_item_id',
            'estimated_unit_cost',
            'estimated_total_cost',
        ]));

        $this->assertFalse(Schema::hasColumn('supply_request_items', 'items_id'));
    }
}
