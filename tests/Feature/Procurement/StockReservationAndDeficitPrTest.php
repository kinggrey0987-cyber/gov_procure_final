<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReservationAndDeficitPrTest extends TestCase
{
    use RefreshDatabase;

    private Office $office;
    private User $officeUser;
    private User $gsoOfficer;
    private User $procurementOfficer;
    private ItemCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = Office::query()->create([
            'name' => 'Accounting Office',
            'code' => 'ACCT',
            'description' => 'Test Office',
            'is_active' => true,
        ]);

        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);

        $this->category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'standard_delivery_days' => 7,
            'description' => 'Standard supplies',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_pr_includes_only_deficit_items_with_shortage_quantity_and_reserves_available_stock(): void
    {
        // Item 1: Deficit item (Requested 20, Available 5, Shortage 15)
        $item1 = $this->createItemWithInventory('Ballpen Blue', 5);
        // Item 2: Fully in-stock item (Requested 4, Available 10, Shortage 0)
        $item2 = $this->createItemWithInventory('Bond Paper A4', 10);

        $supplyRequest = $this->createSupplyRequest([
            ['item' => $item1, 'quantity' => 20],
            ['item' => $item2, 'quantity' => 4],
        ]);

        // GSO queues the request to procurement
        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id))
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->firstOrFail();

        // PR should ONLY contain Item 1 (deficit) with quantity 15 (net shortage)
        $this->assertSame(1, $purchaseRequest->items()->count());
        $prItem = $purchaseRequest->items()->firstOrFail();
        $this->assertSame($item1->item_id, $prItem->item_id);
        $this->assertEquals(15, (float) $prItem->quantity_requested);

        // Available warehouse stock should be reserved (FIFO)
        // Item 1: 5 reserved, 0 available
        $inv1 = Inventory::query()->where('item_id', $item1->item_id)->firstOrFail();
        $this->assertEquals(0, (float) $inv1->available_stock);
        $this->assertEquals(5, (float) $inv1->reserved_quantity);
        $this->assertEquals(5, (float) $inv1->stock_on_hand);

        // Item 2: 4 reserved, 6 available
        $inv2 = Inventory::query()->where('item_id', $item2->item_id)->firstOrFail();
        $this->assertEquals(6, (float) $inv2->available_stock);
        $this->assertEquals(4, (float) $inv2->reserved_quantity);
        $this->assertEquals(10, (float) $inv2->stock_on_hand);
    }

    public function test_rejecting_pr_restores_reserved_stock(): void
    {
        $item = $this->createItemWithInventory('Marker Pen', 8);

        $supplyRequest = $this->createSupplyRequest([
            ['item' => $item, 'quantity' => 15], // Available 8, Shortage 7, Reserve 8
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id));

        $invBefore = Inventory::query()->where('item_id', $item->item_id)->firstOrFail();
        $this->assertEquals(0, (float) $invBefore->available_stock);
        $this->assertEquals(8, (float) $invBefore->reserved_quantity);

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->firstOrFail();

        // Procurement rejects the PR
        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
                'action' => 'reject',
                'remarks' => 'Budget cap exceeded, rejected.',
            ])
            ->assertRedirect();

        $invAfter = Inventory::query()->where('item_id', $item->item_id)->firstOrFail();
        $this->assertEquals(8, (float) $invAfter->available_stock);
        $this->assertEquals(0, (float) $invAfter->reserved_quantity);
    }

    public function test_return_for_revision_restores_stock_and_requeue_reserves_again(): void
    {
        $item = $this->createItemWithInventory('Stapler Heavy', 5);

        $supplyRequest = $this->createSupplyRequest([
            ['item' => $item, 'quantity' => 12], // Available 5, Shortage 7, Reserve 5
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id));

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->firstOrFail();

        // Procurement returns for revision
        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
                'action' => 'return',
                'remarks' => 'Please revise specification.',
            ])
            ->assertRedirect();

        // Stock restored
        $invAfterReturn = Inventory::query()->where('item_id', $item->item_id)->firstOrFail();
        $this->assertEquals(5, (float) $invAfterReturn->available_stock);
        $this->assertEquals(0, (float) $invAfterReturn->reserved_quantity);

        // Office resubmits
        SupplyRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->update([
                'status' => 'SUBMITTED',
                'review_notes' => 'Revised by office and submitted again.',
            ]);

        // GSO re-queues
        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Stock re-reserved
        $invAfterRequeue = Inventory::query()->where('item_id', $item->item_id)->firstOrFail();
        $this->assertEquals(0, (float) $invAfterRequeue->available_stock);
        $this->assertEquals(5, (float) $invAfterRequeue->reserved_quantity);
    }

    public function test_po_encoding_only_requires_deficit_items_from_pr(): void
    {
        $itemDeficit = $this->createItemWithInventory('Paper Clips Box', 2);
        $itemInStock = $this->createItemWithInventory('Correction Tape', 20);

        $supplyRequest = $this->createSupplyRequest([
            ['item' => $itemDeficit, 'quantity' => 10], // Deficit: 8
            ['item' => $itemInStock, 'quantity' => 5],  // In-stock: 0
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.queue', $supplyRequest->supply_request_id));

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->firstOrFail();

        // Move to processing
        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
                'action' => 'process',
                'remarks' => 'Approved for PO creation',
            ]);

        // Create PO
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Test Supplier Inc',
            'contact_person' => 'Supplier Rep',
            'email' => 'supplier@test.gov',
            'phone' => '09123456789',
            'address' => 'Supplier City',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supplier_id' => $supplier->supplier_id,
                'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
            ]);

        $purchaseOrder = PurchaseOrder::query()
            ->where('purchase_request_id', $purchaseRequest->purchase_request_id)
            ->firstOrFail();

        // Encode ONLY the deficit item (itemDeficit)
        $srItemDeficit = $supplyRequest->items()->where('item_id', $itemDeficit->item_id)->firstOrFail();

        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-orders.items.store', $purchaseOrder->purchase_order_id), [
                'supply_request_item_id' => $srItemDeficit->supply_request_item_id,
                'item_id' => $itemDeficit->item_id,
                'item_name_snapshot' => $itemDeficit->item_name,
                'quantity_ordered' => 8,
                'unit' => 'BOX',
                'unit_price' => 25.00,
            ])
            ->assertRedirect();

        // Place PO: Should succeed because the in-stock item was NOT in PR and is not required
        $this->actingAs($this->procurementOfficer)
            ->post(route('procurement.purchase-orders.place', $purchaseOrder->purchase_order_id), [
                'remarks' => 'Placing PO for deficit items only',
            ])
            ->assertRedirect();

        $purchaseOrder->refresh();
        $this->assertSame('PLACED', (string) $purchaseOrder->status);
    }

    public function test_consolidated_pr_includes_only_net_shortage_items_and_reserves_available_stock(): void
    {
        // Item 1: Demand across 2 SRs is 10 + 10 = 20. Available: 5. Shortage: 15. Reserve: 5.
        $item1 = $this->createItemWithInventory('Heavy Duty Folders', 5);
        // Item 2: Demand across 2 SRs is 2 + 3 = 5. Available: 10. Shortage: 0. Reserve: 5.
        $item2 = $this->createItemWithInventory('Whiteboard Markers', 10);

        $sr1 = $this->createSupplyRequest([
            ['item' => $item1, 'quantity' => 10],
            ['item' => $item2, 'quantity' => 2],
        ]);

        $sr2 = $this->createSupplyRequest([
            ['item' => $item1, 'quantity' => 10],
            ['item' => $item2, 'quantity' => 3],
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.office-supply-requests.consolidated-queue'), [
                'office_id' => $this->office->id,
                'supply_request_ids' => [
                    $sr1->supply_request_id,
                    $sr2->supply_request_id,
                ],
            ])
            ->assertRedirect();

        $pr = PurchaseRequest::query()
            ->where('remarks', 'like', '%Consolidated PR for%')
            ->latest('purchase_request_id')
            ->firstOrFail();

        // Consolidated PR should contain ONLY Item 1 (deficit) with quantity 15
        $this->assertSame(1, $pr->items()->count());
        $prItem = $pr->items()->firstOrFail();
        $this->assertSame($item1->item_id, $prItem->item_id);
        $this->assertEquals(15, (float) $prItem->quantity_requested);

        // Check reservations:
        // Item 1: 5 reserved, 0 available
        $inv1 = Inventory::query()->where('item_id', $item1->item_id)->firstOrFail();
        $this->assertEquals(0, (float) $inv1->available_stock);
        $this->assertEquals(5, (float) $inv1->reserved_quantity);

        // Item 2: 5 reserved (2 + 3), 5 available (10 - 5)
        $inv2 = Inventory::query()->where('item_id', $item2->item_id)->firstOrFail();
        $this->assertEquals(5, (float) $inv2->available_stock);
        $this->assertEquals(5, (float) $inv2->reserved_quantity);
    }

    public function test_proactive_demand_forecast_replenishment_delivery_is_counted_as_available_stock_for_pending_office_supply_requests(): void
    {
        // 0 initial stock for A4 Bond Paper
        $item = $this->createItemWithInventory('A4 Bond Paper', 0);
        $inv = Inventory::query()->where('item_id', $item->item_id)->firstOrFail();

        // 1. Accounting Office submits Supply Request for 50 units
        $sr = $this->createSupplyRequest([
            ['item' => $item, 'quantity' => 50],
        ]);

        $invService = app(\App\Services\Inventory\InventoryService::class);
        $this->assertEquals(0.0, $invService->getAvailableStockForSupplyRequest($item->item_id, $sr));

        // 2. GSO creates proactive low stock forecast PR for 50 units
        $gsoOffice = Office::query()->firstOrCreate(
            ['code' => 'GSO'],
            ['name' => 'General Services Office', 'is_active' => true]
        );
        $gsoUser = $this->createUser(UserRole::GSO_OFFICER, $gsoOffice);

        $prService = app(PurchaseRequestService::class);
        $forecastPr = $prService->createFromLowStockSignal($gsoUser, $inv, 50);

        // 3. Purchase Order placed and Supplier delivers 30 units
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplier Co.',
            'contact_person' => 'John Doe',
            'contact_number' => '09123456789',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-TEST-PROACTIVE-001',
            'purchase_request_id' => $forecastPr->purchase_request_id,
            'supply_request_id' => $forecastPr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'order_date' => now()->toDateString(),
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'APPROVED',
            'total_amount' => 1500,
            'created_by' => $gsoUser->id,
        ]);

        $forecastSri = $forecastPr->supplyRequest->items()->firstOrFail();
        $poi = \App\Models\PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $forecastSri->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_ordered' => 50,
            'unit' => 'ream',
            'unit_price' => 30,
            'total_price' => 1500,
        ]);

        $delivery = \App\Models\Delivery::query()->create([
            'delivery_code' => 'DEL-TEST-PROACTIVE-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'received_by' => $gsoUser->id,
            'receipt_status' => 'RECEIVED',
        ]);

        $di = \App\Models\DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 30,
            'damaged_quantity' => 0,
        ]);

        // 4. GSO verifies the delivery into warehouse inventory
        $delVerService = app(\App\Services\Delivery\DeliveryVerificationService::class);
        $delVerService->create(
            delivery: $delivery,
            actor: $gsoUser,
            payload: [
                'verification_status' => 'APPROVED',
                'remarks' => 'Verified 30 reams into warehouse inventory',
            ]
        );

        $inv->refresh();
        $this->assertEquals(30.0, (float) $inv->stock_on_hand);
        $this->assertEquals(30.0, (float) $inv->available_stock);

        // Undistributed delivery stock must be 0 because proactive delivery is for general inventory
        $this->assertEquals(0.0, $invService->getUndistributedDeliveryStock($item->item_id));

        // Available stock for Office SR must now reflect the 30 delivered reams
        $availableForSr = $invService->getAvailableStockForSupplyRequest($item->item_id, $sr);
        $this->assertEquals(30.0, $availableForSr);

        // 5. When GSO queues this office SR for procurement, it should queue ONLY the remaining 20 shortage
        $officePr = $prService->queueFromSupplyRequest($this->gsoOfficer, [
            'supply_request_id' => $sr->supply_request_id,
            'remarks' => 'Queued by GSO due to stock shortage.',
        ]);

        $this->assertSame(1, $officePr->items()->count());
        $officePrItem = $officePr->items()->firstOrFail();
        $this->assertEquals(20.0, (float) $officePrItem->quantity_requested);
        $this->assertStringContainsString('50.00 requested, 30.00 reserved from warehouse', (string) $officePrItem->remarks);

        // Warehouse inventory reservations
        $inv->refresh();
        $this->assertEquals(30.0, (float) $inv->stock_on_hand);
        $this->assertEquals(0.0, (float) $inv->available_stock);
        $this->assertEquals(30.0, (float) $inv->reserved_quantity);
    }

    private function createItemWithInventory(string $name, float $stock): Item
    {
        $item = Item::query()->create([
            'item_category_id' => $this->category->item_category_id,
            'item_name' => $name,
            'description' => $name.' description',
            'default_unit' => 'piece',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $item->item_id,
            'available_stock' => $stock,
            'stock_on_hand' => $stock,
            'reserved_quantity' => 0,
            'released_quantity' => 0,
            'low_stock_threshold' => 2,
            'last_updated' => now(),
        ]);

        return $item;
    }

    /**
     * @param array<int, array{item: Item, quantity: float}> $items
     */
    private function createSupplyRequest(array $items): SupplyRequest
    {
        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'prepared_by' => $this->officeUser->id,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 5000,
            'date_prepared' => now()->toDateString(),
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-ACCT-'.uniqid(),
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Test supply request',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
        ]);

        foreach ($items as $entry) {
            $item = $entry['item'];
            $qty = $entry['quantity'];

            $ppmpItem = PpmpItem::query()->create([
                'ppmp_id' => $ppmp->ppmp_id,
                'item_id' => $item->item_id,
                'item_category_id' => $this->category->item_category_id,
                'quantity' => $qty,
                'unit_cost' => 50,
                'estimated_budget' => $qty * 50,
                'procurement_schedule' => 'Q1',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $supplyRequest->supply_request_id,
                'item_id' => $item->item_id,
                'ppmp_item_id' => $ppmpItem->ppmp_item_id,
                'item_name_snapshot' => $item->item_name,
                'quantity_requested' => $qty,
            ]);
        }

        return $supplyRequest;
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for testing',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
