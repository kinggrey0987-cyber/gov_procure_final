<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessDistributionDeliveryLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_process_distribution_automatically_links_verified_delivery(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);

        $office = Office::query()->create(['name' => 'Planning Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Officer']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'status' => 'active']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Legal Bond Paper (80 GSM)',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $inventory = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 5,
            'available_stock' => 5,
            'low_stock_threshold' => 2,
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 2500.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 250.00,
            'estimated_budget' => 2500.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office bond paper supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        $srItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper (80 GSM)',
            'quantity_requested' => 5,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'status' => 'APPROVED',
            'requested_by' => $gsoUser->id,
            'source_type' => 'SHORTAGE',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier Co',
            'contact_person' => 'Contact',
            'email' => 'supplier@example.com',
            'phone_number' => '1234567890',
            'address' => 'Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'office_id' => $office->id,
            'procurement_method' => 'SHOPPING',
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 1250.00,
            'status' => 'APPROVED',
            'approved_by' => $gsoUser->id,
            'created_by' => $gsoUser->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper (80 GSM)',
            'quantity_ordered' => 5,
            'unit' => 'box',
            'unit_price' => 250.00,
            'total_price' => 1250.00,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'VERIFIED',
            'delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'created_by' => $gsoUser->id,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'item_id' => $item->item_id,
            'expected_quantity' => 5,
            'received_quantity' => 5,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => 'All items received in good condition',
        ]);

        // GSO clicks Process Distribution from Office Supply Requests index
        $response = $this->actingAs($gsoUser)->post(
            route('inventory.office-supply-requests.process-distribution', $sr->supply_request_id)
        );

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Distribution record created and releasable items were released automatically.');

        // Distribution record must have been created and automatically linked to verified delivery
        $distRecord = DistributionRecord::query()
            ->where('supply_request_id', $sr->supply_request_id)
            ->first();

        $this->assertNotNull($distRecord);
        $this->assertSame((int) $delivery->delivery_id, (int) $distRecord->delivery_id);
        $this->assertSame('RELEASED', (string) $distRecord->status);
    }
}
