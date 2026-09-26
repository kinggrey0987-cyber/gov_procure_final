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
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionAvailableStockGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_delivery_backed_distribution_allows_releasing_warehouse_stock(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Planning Office']);
        $officeRole = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $officeUser = User::factory()->create(['role_id' => $officeRole->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->firstOrCreate(
            ['category_name' => 'Office Supplies'],
            ['description' => 'Supplies']
        );
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper A4',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        // Inventory has 1 box on hand
        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 1,
            'available_stock' => 1,
            'reserved_quantity' => 0,
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test',
            'priority_level' => 'NORMAL',
            'status' => 'PARTIALLY_RECEIVED',
        ]);

        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 20,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier Inc',
            'contact_person' => 'Contact',
            'email' => 'supplier@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 1600,
        ]);

        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_ordered' => 16,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1600,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 16,
            'received_quantity' => 15,
            'damaged_quantity' => 2,
            'missing_quantity' => 1,
            'item_status' => 'VERIFIED',
        ]);

        // Distribution 1: released 16 items (all 12 accepted delivery items + 4 or so)
        $dist1 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'RELEASED',
        ]);

        $dist1->items()->create([
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_released' => 16,
            'unit' => 'box',
        ]);

        // When viewing Distribution 1, max_releasable should not be 0; it should allow the remaining 1 box in warehouse stock!
        $response = $this->actingAs($gsoUser)
            ->get(route('inventory.distribution-records.show', $dist1->distribution_record_id));

        $response->assertOk();
        $unreleased = $response->viewData('page')['props']['unreleased_stock_items'];
        $this->assertNotEmpty($unreleased);
        $this->assertEquals(1.0, $unreleased[0]['releasable_quantity']);

        // Test releaseAvailableStock endpoint
        $releaseActionResponse = $this->actingAs($gsoUser)
            ->post(route('inventory.distribution-records.release-available-stock', $dist1->distribution_record_id));

        $releaseActionResponse->assertRedirect(route('inventory.distribution-records.show', $dist1->distribution_record_id));
        $this->assertEquals(17, (float) $dist1->fresh()->items->sum('quantity_released'));
    }

    public function test_inventory_backed_release_rows_includes_partially_received_requests(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Planning Office']);
        $officeRole = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $officeUser = User::factory()->create(['role_id' => $officeRole->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->firstOrCreate(
            ['category_name' => 'Office Supplies'],
            ['description' => 'Supplies']
        );
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper A4',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 1,
            'available_stock' => 1,
            'reserved_quantity' => 0,
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test',
            'priority_level' => 'NORMAL',
            'status' => 'PARTIALLY_RECEIVED',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 20,
        ]);

        $dist1 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'RECEIVED',
        ]);

        $dist1->items()->create([
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_released' => 19,
            'unit' => 'box',
        ]);

        // Now test GET /inventory/distribution-records/create
        $response = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response->assertOk();

        $rows = $response->viewData('page')['props']['eligibleRows']['data'];
        $srRow = collect($rows)->firstWhere('supply_request_id', $sr->supply_request_id);
        $this->assertNotNull($srRow, 'Partially received supply request with remaining stock should be eligible for inventory release.');
    }

    public function test_office_supply_request_show_aggregates_follow_up_distributions(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Planning Office']);
        $officeRole = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $officeUser = User::factory()->create(['role_id' => $officeRole->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->firstOrCreate(
            ['category_name' => 'Office Supplies'],
            ['description' => 'Supplies']
        );
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper A4',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 1,
            'available_stock' => 1,
            'reserved_quantity' => 0,
        ]);

        $parentSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Parent Request 20 boxes',
            'priority_level' => 'NORMAL',
            'status' => 'PARTIALLY_RECEIVED',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $parentSr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 20,
        ]);

        // Distribution 1: 16 boxes released on parent SR
        $dist1 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $parentSr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'RECEIVED',
        ]);

        $dist1->items()->create([
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_released' => 16,
            'unit' => 'box',
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $parentSr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier Inc',
            'contact_person' => 'Contact',
            'email' => 'supplier2@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0002',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $parentSr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 1600,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        // Follow-up child SR for 3 boxes replacement
        $childSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0004',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Replacement follow-up for SR-MPDO-2026-09-0003',
            'priority_level' => 'NORMAL',
            'status' => 'COMPLETED',
        ]);

        $followUpPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0002',
            'supply_request_id' => $childSr->supply_request_id,
            'source_delivery_id' => $delivery->delivery_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $childSr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 3,
        ]);

        // Distribution 2: 3 boxes released on child follow-up SR
        $dist2 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0002',
            'supply_request_id' => $childSr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'RECEIVED',
        ]);

        $dist2->items()->create([
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_released' => 3,
            'unit' => 'box',
        ]);

        // Viewing Parent SR Show page
        $response = $this->actingAs($gsoUser)
            ->get(route('inventory.office-supply-requests.show', $parentSr->supply_request_id));

        $response->assertOk();
        $props = $response->viewData('page')['props']['request'];
        $itemProp = $props['items'][0];

        $this->assertEquals(20.0, (float) $itemProp['quantity_requested']);
        $this->assertEquals(19.0, (float) $itemProp['quantity_released'], 'Follow-up distribution of 3 boxes should be aggregated into parent SR released total (16 + 3 = 19).');
        $this->assertEquals(1.0, (float) $itemProp['quantity_remaining'], 'Remaining should be 20 - 19 = 1 box.');
        $this->assertEquals(1.0, (float) $itemProp['available_stock']);
        $this->assertEquals(0.0, (float) $itemProp['shortage']);
        $this->assertTrue($props['has_unreleased_available_stock'], 'Banner should show because 1 box remaining has 1 box available in warehouse.');
    }

    public function test_multi_delivery_discrepancy_chain_releases_all_and_prevents_stock_stealing(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office']);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_code' => 'ITEM-BOND-A4',
            'item_name' => 'Bond Paper, A4 (80 GSM)',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        // Initially 4 boxes in stock
        $inventory = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 4,
            'available_stock' => 4,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 5,
        ]);

        // Root request: 50 boxes requested
        $rootSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Annual Office Supplies',
            'priority_level' => 'NORMAL',
            'status' => 'APPROVED',
        ]);

        $rootSri = SupplyRequestItem::query()->create([
            'supply_request_id' => $rootSr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 50,
        ]);

        // 46 sent for procurement
        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $rootSr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplier Inc',
            'contact_person' => 'Supplier Agent',
            'email' => 'paper@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supply_request_id' => $rootSr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'APPROVED',
            'total_amount' => 4600,
        ]);

        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $rootSri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 46,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 4600,
        ]);

        // Delivery 1: 36 received, 5 damaged, 5 missing -> 31 accepted
        $delivery1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        $di1 = DeliveryItem::query()->create([
            'delivery_id' => $delivery1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 46,
            'received_quantity' => 36,
            'damaged_quantity' => 5,
            'missing_quantity' => 0,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Inventory is updated with 31 accepted units (now 4 + 31 = 35)
        $inventory->forceFill([
            'stock_on_hand' => 35,
            'available_stock' => 35,
        ])->save();

        // Follow-up SR 2 created for 10 discrepancy units
        $childSr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0004',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Discrepancy replacement for DEL-2026-09-0001',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
        ]);

        $childSri1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $childSr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 10,
        ]);

        $pr2 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0002',
            'supply_request_id' => $childSr1->supply_request_id,
            'source_delivery_id' => $delivery1->delivery_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0002',
            'purchase_request_id' => $pr2->purchase_request_id,
            'supply_request_id' => $childSr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 1000,
        ]);

        $poi2 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $childSri1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        // When creating Distribution 1 for Delivery 1:
        // It must automatically release 35 (31 from delivery + 4 from initial warehouse stock)!
        $distService = app(\App\Services\Inventory\DistributionRecordService::class);
        $dist1 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $rootSr->supply_request_id,
            'delivery_id' => $delivery1->delivery_id,
            'office_id' => $office->id,
        ]);

        $this->assertEquals(35.0, (float) $dist1->items->sum('quantity_released'), 'Distribution 1 should automatically include both 31 from delivery and 4 from initial inventory stock.');

        // After releasing 35, remaining warehouse stock is 0
        $inventory->refresh();
        $this->assertEquals(0.0, (float) $inventory->available_stock);

        // Viewing Distribution 1: unreleased_stock_items should be EMPTY because the 4 inventory units were already released!
        $showResponse1 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.show', $dist1->distribution_record_id));
        $showResponse1->assertOk();
        $unreleased1 = $showResponse1->viewData('page')['props']['unreleased_stock_items'];
        $this->assertEmpty($unreleased1, 'There should be NO unreleased stock prompt on Distribution 1 because the 4 units were already released.');

        // Calling releaseAvailableStock should NOT add anything or steal future stock
        $this->actingAs($gsoUser)->post(route('inventory.distribution-records.release-available-stock', $dist1->distribution_record_id));
        $this->assertEquals(35.0, (float) $dist1->fresh()->items->sum('quantity_released'));

        // Delivery 2 arrives for SR 2: 9 received, 1 damaged -> 9 accepted
        $delivery2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        $di2 = DeliveryItem::query()->create([
            'delivery_id' => $delivery2->delivery_id,
            'purchase_order_item_id' => $poi2->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 10,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        $inventory->forceFill([
            'stock_on_hand' => 9,
            'available_stock' => 9,
        ])->save();

        // Follow-up SR 3 created for 1 discrepancy unit
        $childSr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0005',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Discrepancy replacement for DEL-2026-09-0002',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
        ]);

        $childSri2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $childSr2->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 1,
        ]);

        $pr3 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0003',
            'supply_request_id' => $childSr2->supply_request_id,
            'source_delivery_id' => $delivery2->delivery_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po3 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0003',
            'purchase_request_id' => $pr3->purchase_request_id,
            'supply_request_id' => $childSr2->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'APPROVED',
            'total_amount' => 100,
        ]);

        $poi3 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po3->purchase_order_id,
            'supply_request_item_id' => $childSri2->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 1,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        // Create Distribution 2 for Delivery 2 -> releases 9
        $dist2 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $childSr1->supply_request_id,
            'delivery_id' => $delivery2->delivery_id,
            'office_id' => $office->id,
        ]);

        $this->assertEquals(9.0, (float) $dist2->items->sum('quantity_released'));

        // Delivery 3 arrives for SR 3: 1 received, 1 accepted
        $delivery3 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0003',
            'purchase_order_id' => $po3->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery3->delivery_id,
            'purchase_order_item_id' => $poi3->purchase_order_item_id,
            'expected_quantity' => 1,
            'received_quantity' => 1,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $inventory->forceFill([
            'stock_on_hand' => 1,
            'available_stock' => 1,
        ])->save();

        // Create Distribution 3 for Delivery 3 -> MUST NOT CRASH with "No requested item currently has a releasable quantity"!
        $dist3 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $childSr2->supply_request_id,
            'delivery_id' => $delivery3->delivery_id,
            'office_id' => $office->id,
        ]);

        $this->assertEquals(1.0, (float) $dist3->items->sum('quantity_released'), 'Distribution 3 must release the 1 delivered unit without crashing.');

        // Total released across all 3 distributions: 35 + 9 + 1 = 45 units (all delivered + warehouse units)
        $totalReleased = (float) $dist1->items->sum('quantity_released')
            + (float) $dist2->items->sum('quantity_released')
            + (float) $dist3->items->sum('quantity_released');
        $this->assertEquals(45.0, $totalReleased);

        $officeRole = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $officeUser = User::factory()->create(['role_id' => $officeRole->id, 'office_id' => $office->id]);

        // Acknowledge receipt of all distributions
        $this->actingAs($officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id))->assertRedirect();
        $this->actingAs($officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id))->assertRedirect();
        $this->actingAs($officeUser)->post(route('office.distribution-records.receive', $dist3->distribution_record_id))->assertRedirect();

        $this->assertEquals('RECEIVED', (string) $dist1->fresh()->status);
        $this->assertEquals('RECEIVED', (string) $dist2->fresh()->status);
        $this->assertEquals('RECEIVED', (string) $dist3->fresh()->status);

        // Child SR 2 (SR-0005) received 1 of 1 -> RECEIVED
        $this->assertEquals('RECEIVED', (string) $childSr2->fresh()->status);

        // Child SR 1 (SR-0004) received 9 of 10 directly + 1 from follow-up child SR 2 -> RECEIVED
        $this->assertEquals('RECEIVED', (string) $childSr1->fresh()->status);

        // Root SR (SR-0003) received 35 directly + 9 from follow-up 1 + 1 from follow-up 2 = 45 of 50 (or 50 if all 50 delivered)
        $trackingResponse = $this->actingAs($officeUser)->get(route('office.supply-requests.index'));
        $trackingResponse->assertOk();
    }
}
