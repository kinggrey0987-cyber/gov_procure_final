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
            'received_quantity' => 16,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Distribution 1: released 16 items (all 16 accepted delivery items)
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
        $this->assertFalse((bool) $props['has_unreleased_available_stock'], 'Banner should not show for procurement-backed request.');
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

    public function test_exact_three_delivery_three_distribution_scenario_prevents_drain_and_succeeds(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office']);
        $officeRole = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $officeUser = User::factory()->create(['role_id' => $officeRole->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_code' => 'ITEM-BOND-A4-EXACT',
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
            'requested_by' => $officeUser->id,
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
            'supplier_name' => 'Supplier Agent Inc',
            'contact_person' => 'Supplier Agent',
            'email' => 'supplier@agent.local',
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

        // Delivery 1 arrives: 36 received, 5 damaged, 5 missing -> 36 accepted (or 36 received, 0 damaged/missing)
        // User scenario: 1st delivery 36 accepted, 5 damaged, 5 missing (total received = 46)
        $delivery1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 46,
            'received_quantity' => 41,
            'damaged_quantity' => 5,
            'missing_quantity' => 5,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Inventory is updated with 36 accepted units (4 initial + 36 = 40)
        $inventory->forceFill([
            'stock_on_hand' => 40,
            'available_stock' => 40,
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

        // Delivery 2 arrives: 10 received, 9 accepted, 1 damaged
        $delivery2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery2->delivery_id,
            'purchase_order_item_id' => $poi2->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 10,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Inventory is updated with 9 accepted units from Delivery 2
        // Warehouse has 40 + 9 = 49 units
        $inventory->forceFill([
            'stock_on_hand' => 49,
            'available_stock' => 49,
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

        // Delivery 3 arrives: 1 received, 1 accepted
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

        // Warehouse total: 49 + 1 = 50 units (4 warehouse + 36 del1 + 9 del2 + 1 del3)
        $inventory->forceFill([
            'stock_on_hand' => 50,
            'available_stock' => 50,
        ])->save();

        // NOW: Suppose Distribution 1 is created for Delivery 1 releasing only the 36 delivery portion
        $dist1 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $rootSr->supply_request_id,
            'delivery_id' => $delivery1->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'status' => 'RELEASED',
        ]);

        // Release the 36 delivery portion
        $distService = app(\App\Services\Inventory\DistributionRecordService::class);
        $distService->addDistributionItem($dist1, $gsoUser, [
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 36,
            'unit' => 'box',
            'remarks' => 'Automatically released against Delivery 1',
        ]);

        // Warehouse available_stock deducted 36: now 14 units left (4 initial + 9 del2 + 1 del3)
        $inventory->refresh();
        $this->assertEquals(14.0, (float) $inventory->available_stock);

        // When viewing Distribution 1 show page:
        // Free warehouse stock should be 4 (not 14! Delivery 2's 9 units and Delivery 3's 1 unit must NOT be shown as free stock)
        $showResponse1 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.show', $dist1->distribution_record_id));
        $showResponse1->assertOk();
        $unreleased = $showResponse1->viewData('page')['props']['unreleased_stock_items'];
        $this->assertNotEmpty($unreleased);
        $this->assertEquals(4.0, (float) $unreleased[0]['releasable_quantity'], 'Only the 4 initial warehouse units should be eligible for release on Distribution 1.');

        // User releases the 4 warehouse units into Distribution 1
        $this->actingAs($gsoUser)->post(route('inventory.distribution-records.release-available-stock', $dist1->distribution_record_id));
        $this->assertEquals(40.0, (float) $dist1->fresh()->items->sum('quantity_released'));

        // After releasing the 4 warehouse units:
        // 1) Warehouse available stock should now be 10 (9 from Del 2 + 1 from Del 3)
        $inventory->refresh();
        $this->assertEquals(10.0, (float) $inventory->available_stock);

        // 2) Viewing Distribution 1: unreleased_stock_items MUST BE EMPTY!
        $showResponse2 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.show', $dist1->distribution_record_id));
        $showResponse2->assertOk();
        $unreleasedAfter = $showResponse2->viewData('page')['props']['unreleased_stock_items'];
        $this->assertEmpty($unreleasedAfter, 'No more warehouse stock prompts should appear for Distribution 1.');

        // 3) Items table in Distribution 1 show page: max_releasable must be 0
        $itemsProp = $showResponse2->viewData('page')['props']['record']['supply_request']['items'];
        $this->assertEquals(0.0, (float) $itemsProp[0]['max_releasable']);

        // 4) Calling releaseAvailableStock again must NOT release any more units
        $this->actingAs($gsoUser)->post(route('inventory.distribution-records.release-available-stock', $dist1->distribution_record_id));
        $this->assertEquals(40.0, (float) $dist1->fresh()->items->sum('quantity_released'));

        // 5) Trying to manually add any item to Distribution 1 MUST fail validation
        $threwValidation = false;
        try {
            $distService->addDistributionItem($dist1, $gsoUser, [
                'item_id' => $item->item_id,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'quantity_released' => 4,
                'unit' => 'box',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $threwValidation = true;
        }
        $this->assertTrue($threwValidation, 'Adding more items to Distribution 1 must be rejected by validation.');

        // 6) Now user releases Distribution 2 for Delivery 2 (SR-MPDO-2026-09-0004)
        // Must NOT fail with "Released quantity cannot exceed available stock."!
        $dist2 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $childSr1->supply_request_id,
            'delivery_id' => $delivery2->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(9.0, (float) $dist2->items->sum('quantity_released'));

        // Warehouse available stock should now be 1 (for Delivery 3)
        $inventory->refresh();
        $this->assertEquals(1.0, (float) $inventory->available_stock);

        // 7) Now user releases Distribution 3 for Delivery 3 (SR-MPDO-2026-09-0005)
        $dist3 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $childSr2->supply_request_id,
            'delivery_id' => $delivery3->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(1.0, (float) $dist3->items->sum('quantity_released'));

        // Warehouse available stock is now 0 (all 50 units fully released)
        $inventory->refresh();
        $this->assertEquals(0.0, (float) $inventory->available_stock);

        // Total distributed across all 3 distributions: 40 + 9 + 1 = 50!
        $totalReleased = (float) $dist1->fresh()->items->sum('quantity_released')
            + (float) $dist2->fresh()->items->sum('quantity_released')
            + (float) $dist3->fresh()->items->sum('quantity_released');
        $this->assertEquals(50.0, $totalReleased);
    }

    public function test_process_release_endpoint_handles_empty_string_payload_and_verified_delivery(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office']);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_code' => 'ITEM-BOND-A4-PROCESS',
            'item_name' => 'Bond Paper, A4 (80 GSM)',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 10,
            'available_stock' => 10,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 5,
        ]);

        $rootSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0099',
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
            'quantity_requested' => 10,
        ]);

        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0099',
            'supply_request_id' => $rootSr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier Agent Inc',
            'contact_person' => 'Supplier Agent',
            'email' => 'supplier@agent.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0099',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supply_request_id' => $rootSr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'APPROVED',
            'total_amount' => 1000,
        ]);

        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $rootSri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        $delivery1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0099',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 10,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Post exactly what the frontend submits when clicking Process Release
        $response = $this->actingAs($gsoUser)->post(route('inventory.distribution-records.store'), [
            'supply_request_id' => $rootSr->supply_request_id,
            'delivery_id' => $delivery1->delivery_id,
            'office_id' => $office->id,
            'release_date' => '',
            'storage_location' => '',
            'notes' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $record = DistributionRecord::query()->where('supply_request_id', $rootSr->supply_request_id)->first();
        $this->assertNotNull($record);
        $response->assertRedirect(route('inventory.distribution-records.show', $record->distribution_record_id));
        $this->assertEquals('RELEASED', $record->status);
        $this->assertEquals(10.0, (float) $record->items()->sum('quantity_released'));

        // View the show page
        $showResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.show', $record->distribution_record_id));
        $showResponse->assertOk();
    }

    public function test_unrelated_new_supply_request_does_not_steal_undistributed_delivery_stock_and_correctly_reports_shortage(): void
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

        $supplier = Supplier::factory()->create();

        // 1. Initial inventory: 0 stock
        $inv = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);

        // 2. Supply Request 1 for 50 boxes with PO and Verified Delivery of 50 boxes
        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'First Request',
            'priority_level' => 'NORMAL',
            'status' => 'DELIVERED',
        ]);

        $sri1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 50,
        ]);

        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'OPEN',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'supply_request_id' => $sr1->supply_request_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'DELIVERED',
            'total_amount' => 10000,
        ]);

        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $sri1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_ordered' => 50,
            'unit' => 'box',
            'unit_price' => 200,
            'total_price' => 10000,
        ]);

        $delivery1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'VERIFIED',
            'actual_delivery_date' => now()->toDateString(),
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Accepted delivery added 50 to warehouse stock
        $inv->update([
            'stock_on_hand' => 50,
            'available_stock' => 50,
        ]);

        // 3. User creates a NEW, UNRELATED Supply Request 2 (e.g. SR-MPDO-2026-09-0013)
        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0013',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'Office Supplies',
            'purpose' => 'New Requisition',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 50,
        ]);

        // 4. Inspect SR2 show page: Must NOT see delivery stock as available for SR2
        $responseSr2 = $this->actingAs($gsoUser)->get(route('inventory.office-supply-requests.show', $sr2->supply_request_id));
        $responseSr2->assertOk();
        $propsSr2 = $responseSr2->viewData('page')['props']['request'];

        $this->assertEquals(0.0, (float) $propsSr2['items'][0]['available_stock']);
        $this->assertEquals(50.0, (float) $propsSr2['items'][0]['shortage']);
        $this->assertEquals(50.0, (float) $propsSr2['shortage_total']);
        $this->assertFalse((bool) $propsSr2['has_unreleased_available_stock'], 'Should not show warehouse stock available banner when stock belongs to another delivery');

        // 5. Inspect SR2 in office supply requests list: Stock assessment must be SHORTAGE_REQUIRES_PROCUREMENT
        $indexResponse = $this->actingAs($gsoUser)->get(route('inventory.office-supply-requests.index'));
        $indexResponse->assertOk();
        $allRequests = collect($indexResponse->viewData('page')['props']['requests']['data']);
        $sr2Row = $allRequests->firstWhere('supply_request_id', $sr2->supply_request_id);
        $this->assertNotNull($sr2Row);
        $this->assertEquals('SHORTAGE_REQUIRES_PROCUREMENT', $sr2Row['stock_assessment']);

        // 6. Attempting to process distribution release from inventory for SR2 must be blocked
        $distResponse = $this->actingAs($gsoUser)->post(route('inventory.office-supply-requests.process-distribution', $sr2->supply_request_id));
        $distResponse->assertRedirect(route('inventory.office-supply-requests.index'));
        $distResponse->assertSessionHas('error');

        // 7. GSO can queue SR2 for procurement because the shortage of 50 is recognized
        $prService = app(\App\Services\Procurement\PurchaseRequestService::class);
        $pr = $prService->queueFromSupplyRequest($gsoUser, [
            'supply_request_id' => $sr2->supply_request_id,
            'remarks' => 'Shortage queued for procurement',
        ]);

        $this->assertNotNull($pr);
        $this->assertEquals('OPEN', $pr->status);
        $this->assertCount(1, $pr->items);
        $this->assertEquals(50.0, (float) $pr->items->first()->quantity_requested);

        // Warehouse available stock is still 50 (Delivery 1 stock was not stolen/reserved by SR2)
        $inv->refresh();
        $this->assertEquals(50.0, (float) $inv->available_stock);
        $this->assertEquals(0.0, (float) $inv->reserved_quantity);

        // 8. Now distribute Delivery 1 to SR1: All 50 units are safely distributed to SR1
        $dist1Response = $this->actingAs($gsoUser)->post(route('inventory.distribution-records.store'), [
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $delivery1->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $dist1Response->assertSessionHasNoErrors();

        $dr1 = DistributionRecord::query()->where('supply_request_id', $sr1->supply_request_id)->first();
        $this->assertNotNull($dr1);
        $this->assertEquals('RELEASED', $dr1->status);
        $this->assertEquals(50.0, (float) $dr1->items()->sum('quantity_released'));

        // After release, warehouse stock is 0
        $inv->refresh();
        $this->assertEquals(0.0, (float) $inv->available_stock);
        $this->assertEquals(0.0, (float) $inv->stock_on_hand);
    }

    public function test_releasing_delivery_with_existing_stock_releases_both_delivery_and_initial_inventory(): void
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

        // Initial inventory: 5 boxes in warehouse
        $inv = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 5,
            'available_stock' => 5,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 10,
        ]);

        // 1. MPDO requests 50 boxes
        $sr = SupplyRequest::query()->create([
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_code' => 'SR-MPDO-TEST-0001',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
            'purpose' => 'Office supplies',
            'submitted_at' => now(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 50,
        ]);

        // 2. Queued for procurement by GSO: Net shortage of 45 queued, 5 reserved
        $prService = app(\App\Services\Procurement\PurchaseRequestService::class);
        $pr = $prService->queueFromSupplyRequest($gsoUser, [
            'supply_request_id' => $sr->supply_request_id,
            'remarks' => 'Shortage queued for procurement',
        ]);

        $inv->refresh();
        $this->assertEquals(0.0, (float) $inv->available_stock);
        $this->assertEquals(5.0, (float) $inv->reserved_quantity);

        // 3. Purchase Order placed for 45 boxes
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Supplier Test',
            'contact_person' => 'Supplier',
        ]);
        $po = PurchaseOrder::query()->create([
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'po_number' => 'PO-TEST-0001',
            'procurement_method' => 'SHOPPING',
            'status' => 'ORDERED',
            'total_amount' => 4500,
        ]);
        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'unit' => 'box',
            'quantity_ordered' => 50,
            'unit_price' => 100,
            'total_price' => 5000,
        ]);

        // 4. Delivery arrives partially: 35 received, 10 missing
        $delivery = Delivery::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'delivery_code' => 'DEL-TEST-0001',
            'receipt_status' => 'PARTIALLY_RECEIVED',
            'delivery_date' => now()->toDateString(),
        ]);
        $deliveryItem = DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 45,
            'received_quantity' => 35,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ]);

        $invService = app(\App\Services\Inventory\InventoryService::class);
        $invService->updateFromVerifiedDelivery($delivery, $gsoUser);

        $inv->refresh();
        // SOH should be 5 + 35 = 40. Available = 35. Reserved = 5.
        $this->assertEquals(40.0, (float) $inv->stock_on_hand);
        $this->assertEquals(35.0, (float) $inv->available_stock);
        $this->assertEquals(5.0, (float) $inv->reserved_quantity);

        // 5. GSO clicks Process Release for this delivery:
        // Releasable stock should be 35 (from delivery) + 5 (from initial warehouse stock) = 40!
        $response = $this->actingAs($gsoUser)->post(route('inventory.distribution-records.store'), [
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $response->assertSessionHasNoErrors();

        $dr = DistributionRecord::query()->where('supply_request_id', $sr->supply_request_id)->first();
        $this->assertNotNull($dr);
        $releasedQty = (float) $dr->items()->sum('quantity_released');
        $this->assertEquals(40.0, $releasedQty, 'Releasing delivery should include both 35 from delivery and 5 from warehouse inventory');

        $distItem = $dr->items()->first();
        $this->assertNotNull($distItem);
        $this->assertStringContainsString('35.00 box from delivery DEL-TEST-0001, 5.00 box from warehouse inventory', (string) $distItem->remarks);
        $this->assertStringContainsString('Includes 5.00 box from warehouse stock released alongside delivery.', (string) $dr->notes);

        // 6. View supply request show: banner should NOT show for procurement-backed request
        $srResponse = $this->actingAs($gsoUser)->get(route('inventory.office-supply-requests.show', $sr->supply_request_id));
        $srResponse->assertOk();
        $srProps = $srResponse->viewData('page')['props']['request'];
        $this->assertFalse((bool) $srProps['has_unreleased_available_stock'], 'Banner should not show for procurement-backed request.');
    }

    public function test_partially_received_delivery_and_unreleased_warehouse_stock_appear_in_supply_distribution(): void
    {
        $gsoRole = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $gsoUser = User::factory()->create(['role_id' => $gsoRole->id]);

        $office = Office::factory()->create(['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office']);
        $officeUser = User::factory()->create(['role_id' => Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User'])->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'ACTIVE',
        ]);
        $item = Item::query()->create([
            'item_code' => 'ITEM-BOND-A4-TEST-PARTIAL',
            'item_name' => 'Bond Paper, A4 (80 GSM)',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $inv = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 5,
            'available_stock' => 0,
            'reserved_quantity' => 5,
        ]);

        // Requisition for 15 boxes (5 reserved, 10 shortage)
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Test scenario',
        ]);

        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 15,
        ]);

        // PO 1 for 10 boxes shortage
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplier',
            'contact_person' => 'Contact',
            'email' => 'paper@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 1000,
        ]);

        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        // Delivery 1 arrives: 10 expected, 5 received, 5 missing. receipt_status = PARTIALLY_RECEIVED
        $del1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 5,
            'damaged_quantity' => 0,
            'missing_quantity' => 5,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        $invService = app(\App\Services\Inventory\InventoryService::class);
        $invService->updateFromVerifiedDelivery($del1, $gsoUser);

        // Follow-up child SR for 5 missing boxes
        $childSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Follow up for DEL-2026-09-0001',
        ]);

        $childSri = SupplyRequestItem::query()->create([
            'supply_request_id' => $childSr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 5,
        ]);

        $pr2 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0002',
            'supply_request_id' => $childSr->supply_request_id,
            'source_delivery_id' => $del1->delivery_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0002',
            'purchase_request_id' => $pr2->purchase_request_id,
            'supply_request_id' => $childSr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 500,
        ]);

        $poi2 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $childSri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 5,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 500,
        ]);

        // Delivery 2 arrives: 5 expected, 5 received, 0 missing. receipt_status = VERIFIED
        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $poi2->purchase_order_item_id,
            'expected_quantity' => 5,
            'received_quantity' => 5,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $invService->updateFromVerifiedDelivery($del2, $gsoUser);

        // Process release for Delivery 2 only
        $distService = app(\App\Services\Inventory\DistributionRecordService::class);
        $dist2 = $distService->createRecord($gsoUser, [
            'supply_request_id' => $childSr->supply_request_id,
            'delivery_id' => $del2->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $distService->addDistributionItem($dist2, $gsoUser, [
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 5,
            'unit' => 'box',
        ]);

        // Warehouse now has: 5 (initial) + 5 (from Del 1) = 10 units!
        // Now viewing Supply Distribution (/inventory/distribution-records/create):
        $distCreateResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $distCreateResponse->assertOk();
        $eligible = $distCreateResponse->viewData('page')['props']['eligibleRows']['data'];

        // Parent SR has Delivery 1 awaiting release; it must appear under VERIFIED_DELIVERY and NOT duplicated under AVAILABLE_INVENTORY
        $this->assertNotEmpty($eligible, 'Releasable requests should not be empty.');

        $deliveryRow = collect($eligible)->firstWhere('release_basis', 'VERIFIED_DELIVERY');
        $this->assertNotNull($deliveryRow, 'Delivery 1 (PARTIALLY_RECEIVED) must be visible in releasable requests.');
        $this->assertEquals((int) $del1->delivery_id, (int) $deliveryRow['delivery_id']);
        $this->assertEquals((int) $sr->supply_request_id, (int) $deliveryRow['supply_request_id']);

        $srRows = collect($eligible)->filter(fn ($row) => (int) $row['supply_request_id'] === (int) $sr->supply_request_id);
        $this->assertCount(1, $srRows, 'Parent SR must only appear once in releasable requests without duplicate available inventory copy.');

        // Releasing Delivery 1 bundles both the delivery stock (5) and initial warehouse stock (5)
        $dist1 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $del1->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);

        $this->assertEquals(10.0, (float) $dist1->items->sum('quantity_released'));

        // Once released, SR has reached its full 15 requested units and must no longer appear in releasable requests
        $distCreateResponseAfter = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $distCreateResponseAfter->assertOk();
        $eligibleAfter = $distCreateResponseAfter->viewData('page')['props']['eligibleRows']['data'];
        $srRowsAfter = collect($eligibleAfter)->filter(fn ($row) => (int) $row['supply_request_id'] === (int) $sr->supply_request_id);
        $this->assertCount(0, $srRowsAfter, 'Parent SR must not remain as a phantom available inventory row after full release.');
    }

    public function test_multiple_verified_delivery_requests_do_not_produce_duplicate_inventory_rows_or_phantom_error(): void
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
            'item_name' => 'Ballpen Black',
            'default_unit' => 'piece',
            'status' => 'ACTIVE',
        ]);

        // Warehouse has 50 units available from general stock
        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 50,
            'available_stock' => 50,
            'reserved_quantity' => 0,
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Office Mart',
            'contact_person' => 'Contact',
            'email' => 'officemart@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        // Request 1: 10 requested, PO for 10, Delivery 1 for 5 units (partially received)
        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Test 1',
        ]);

        $sri1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black',
            'quantity_requested' => 10,
        ]);

        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0010',
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0010',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 500,
        ]);

        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $sri1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black',
            'quantity_ordered' => 10,
            'unit' => 'piece',
            'unit_price' => 50,
            'total_price' => 500,
        ]);

        $del1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 5,
            'damaged_quantity' => 0,
            'missing_quantity' => 5,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Request 2: 10 requested, PO for 10, Delivery 2 for 10 units (verified)
        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0004',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Test 2',
        ]);

        $sri2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black',
            'quantity_requested' => 10,
        ]);

        $pr2 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0011',
            'supply_request_id' => $sr2->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0011',
            'purchase_request_id' => $pr2->purchase_request_id,
            'supply_request_id' => $sr2->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 500,
        ]);

        $poi2 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $sri2->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black',
            'quantity_ordered' => 10,
            'unit' => 'piece',
            'unit_price' => 50,
            'total_price' => 500,
        ]);

        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $poi2->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 10,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // When viewing Supply Distribution:
        $distCreateResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $distCreateResponse->assertOk();
        $eligible = $distCreateResponse->viewData('page')['props']['eligibleRows']['data'];

        // Each request MUST appear exactly once (under VERIFIED_DELIVERY), zero duplicate "From Available Inventory" copies!
        $this->assertCount(2, $eligible, 'There must be exactly 2 rows for the 2 requests, no duplicates.');
        $this->assertTrue(collect($eligible)->every(fn ($row) => $row['release_basis'] === 'VERIFIED_DELIVERY'));

        // Process release for SR-1's Delivery 1
        $distService = app(DistributionRecordService::class);
        $dist1 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $del1->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $this->assertEquals(5.0, (float) $dist1->items->sum('quantity_released'));

        // Viewing create page again: SR-1 must NOT leave behind a phantom "From Available Inventory" copy!
        $distCreateResponse2 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $distCreateResponse2->assertOk();
        $eligible2 = $distCreateResponse2->viewData('page')['props']['eligibleRows']['data'];

        $sr1Rows = collect($eligible2)->filter(fn ($row) => (int) $row['supply_request_id'] === (int) $sr1->supply_request_id);
        $this->assertCount(0, $sr1Rows, 'SR-1 must not have a phantom available inventory row while awaiting next delivery.');

        $sr2Rows = collect($eligible2)->filter(fn ($row) => (int) $row['supply_request_id'] === (int) $sr2->supply_request_id);
        $this->assertCount(1, $sr2Rows, 'SR-2 remains visible with its verified delivery.');
    }

    public function test_consolidated_purchase_request_delivery_shows_both_requests_and_releases_sequentially(): void
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
            'item_name' => 'Legal Bond Paper',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 200,
            'available_stock' => 200,
            'reserved_quantity' => 0,
        ]);

        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0006',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Consolidated Test 1',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
        ]);
        $sri1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_requested' => 100,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Consolidated Test 2',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
        ]);
        $sri2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_requested' => 100,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0015',
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Consolidated PR for Planning Office [General Supplies] from 2 supply request(s): SR-MPDO-2026-09-0006, SR-MPDO-2026-09-0008.',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplier',
            'contact_person' => 'Supplier',
            'email' => 'supplier@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0015',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 50000,
        ]);

        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 250,
            'total_price' => 50000,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0003',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'delivery_date' => now()->toDateString(),
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
        ]);

        // 1. Visit Supply Distribution Create page:
        // BOTH SR-1 and SR-2 must be visible under VERIFIED_DELIVERY!
        $response = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response->assertOk();
        $eligible = $response->viewData('page')['props']['eligibleRows']['data'];

        $this->assertCount(2, $eligible, 'Both SR-1 and SR-2 must be listed on the distribution create page.');
        $srIds = collect($eligible)->pluck('supply_request_id')->all();
        $this->assertContains((int) $sr1->supply_request_id, $srIds);
        $this->assertContains((int) $sr2->supply_request_id, $srIds);
        $this->assertTrue(collect($eligible)->every(fn ($r) => $r['release_basis'] === 'VERIFIED_DELIVERY'));

        // 2. Release SR-1:
        $distService = app(DistributionRecordService::class);
        $dist1 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $this->assertEquals(100.0, (float) $dist1->items->sum('quantity_released'));

        // 3. Re-visit create page:
        // SR-1 must NOT appear as a ghost row! SR-2 must still appear with the delivery!
        $response2 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response2->assertOk();
        $eligible2 = $response2->viewData('page')['props']['eligibleRows']['data'];

        $this->assertCount(1, $eligible2, 'Only SR-2 should remain; SR-1 must not be a ghost row.');
        $this->assertEquals((int) $sr2->supply_request_id, (int) $eligible2[0]['supply_request_id']);

        // 4. Release SR-2:
        $dist2 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr2->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        // MUST release all remaining 100 boxes (not 2 boxes!)
        $this->assertEquals(100.0, (float) $dist2->items->sum('quantity_released'));

        // 5. Re-visit create page:
        // Everything is fully distributed, 0 rows should remain!
        $response3 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response3->assertOk();
        $eligible3 = $response3->viewData('page')['props']['eligibleRows']['data'];
        $this->assertCount(0, $eligible3, 'Both consolidated requests are fully distributed; no rows remain.');
    }

    public function test_consolidated_pr_partial_delivery_discrepancy_and_replacement_release(): void
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
            'item_name' => 'Legal Bond Paper',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 50,
            'available_stock' => 50,
            'reserved_quantity' => 0,
        ]);

        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0006',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Consolidated Test 1',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
        ]);
        $sri1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_requested' => 100,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Consolidated Test 2',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
        ]);
        $sri2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_requested' => 100,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0015',
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Consolidated PR for Planning Office [General Supplies] from 2 supply request(s): SR-MPDO-2026-09-0006, SR-MPDO-2026-09-0008.',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplier',
            'contact_person' => 'Supplier',
            'email' => 'supplier@test.local',
            'phone' => '09123456789',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0015',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 50000,
        ]);

        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Legal Bond Paper',
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 250,
            'total_price' => 50000,
        ]);

        // Partial delivery: only 50 received out of 200
        $delivery1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0003',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'delivery_date' => now()->toDateString(),
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery1->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
        ]);

        // Release the 50 to SR-1 first
        $distService = app(DistributionRecordService::class);
        $dist1 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $delivery1->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $this->assertEquals(50.0, (float) $dist1->items->sum('quantity_released'));
        $dist1->forceFill(['status' => 'RELEASED'])->save();

        // Since delivery1 is now exhausted (50 received, 50 released) and warehouse has 0 unallocated stock,
        // create page MUST NOT show any releasable rows!
        $response = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response->assertOk();
        $eligible = $response->viewData('page')['props']['eligibleRows']['data'];
        $this->assertCount(0, $eligible, 'When delivery is exhausted and warehouse has 0 stock, no rows should appear.');

        // Now supplier delivers the replacement/remaining 150 items
        $delivery2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0004',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'delivery_date' => now()->toDateString(),
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery2->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 150,
            'received_quantity' => 150,
            'damaged_quantity' => 0,
        ]);

        Inventory::query()->where('item_id', $item->item_id)->update(['available_stock' => 150]);

        // Create page should now show BOTH requests under DEL-2:
        // SR-1 (needs 50) and SR-2 (needs 100)
        $response2 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response2->assertOk();
        $eligible2 = $response2->viewData('page')['props']['eligibleRows']['data'];
        $this->assertCount(2, $eligible2, 'Both SR-1 (remaining 50) and SR-2 (remaining 100) appear for DEL-2.');

        // Release remaining 50 to SR-1
        $dist2 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $delivery2->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $this->assertEquals(50.0, (float) $dist2->items->sum('quantity_released'));

        // Release remaining 100 to SR-2
        $dist3 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr2->supply_request_id,
            'delivery_id' => $delivery2->delivery_id,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $this->assertEquals(100.0, (float) $dist3->items->sum('quantity_released'));

        // All fulfilled
        $response3 = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $response3->assertOk();
        $this->assertCount(0, $response3->viewData('page')['props']['eligibleRows']['data']);
    }
}



