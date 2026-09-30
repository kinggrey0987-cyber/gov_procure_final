<?php

namespace Tests\Feature\Inventory;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpChildPrematureInventoryReleaseBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_follow_up_child_supply_request_cannot_be_released_from_inventory_before_procurement_processes_it(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Gov Supplies Inc', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->first();
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'TEST-PAPER-A4',
            'item_name' => 'Bond Paper A4 Test',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        // Initial warehouse stock is 50
        $inv = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 50,
            'available_stock' => 50,
            'reserved_quantity' => 0,
        ]);

        // SR-16 requesting 200 boxes
        $sr16 = SupplyRequest::query()->create([
            'request_code' => 'SR-TEST-2026-09-0016',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 16 requirement',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri16 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr16->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 200,
        ]);

        // SR-17 requesting 100 boxes
        $sr17 = SupplyRequest::query()->create([
            'request_code' => 'SR-TEST-2026-09-0017',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 17 requirement',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri17 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr17->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 100,
        ]);

        // Consolidated PR for 300 boxes
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-TEST-2026-09-0015',
            'supply_request_id' => $sr16->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for requests %s, %s', $sr16->request_code, $sr17->request_code),
        ]);
        $pri = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 30000,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-TEST-2026-09-0015',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr16->supply_request_id,
            'created_by' => $gsoUser->id,
            'supplier_id' => $supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 30000,
        ]);
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $pri->purchase_request_item_id,
            'supply_request_item_id' => $sri16->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);

        // Delivery 1 arrives with 200 received and 100 missing discrepancy
        $del01 = Delivery::query()->create([
            'delivery_code' => 'DEL-TEST-2026-09-0015',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        $invService = app(\App\Services\Inventory\InventoryService::class);
        $invService->updateFromVerifiedDelivery($del01, $gsoUser);

        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);

        // Distribute 100 from Delivery 1 to SR-17 (fulfilling SR-17)
        $dist17 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr17->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist17->items->sum('quantity_released'));

        // Distribute remaining 100 from Delivery 1 to SR-16 (SR-16 still needs 100)
        $dist16 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr16->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist16->items->sum('quantity_released'));

        // GSO creates follow-up PR for the 100 missing items
        $followUpPr = $prService->createFromPartialDelivery($del01, $gsoUser);
        $sr18 = $followUpPr->supplyRequest;
        $this->assertNotNull($sr18);

        // Warehouse has 50 initial stock available!
        $this->assertGreaterThanOrEqual(50, (float) $inv->fresh()->available_stock);

        // Procurement has NOT processed PR-18 yet (status is OPEN, no PO, no new delivery)
        $this->assertSame('OPEN', (string) $followUpPr->fresh()->status);
        $this->assertCount(0, $followUpPr->purchaseOrders);

        // 1. Visit /inventory/distribution-records/create
        $createResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $createResponse->assertOk();

        $createResponse->assertInertia(function ($page) use ($sr18, $sr16) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);

            // Assert that SR-18 does NOT appear under AVAILABLE_INVENTORY!
            $sr18InventoryRow = $rows->first(fn ($r) => (int) $r['supply_request_id'] === (int) $sr18->supply_request_id
                && ($r['release_basis'] ?? '') === 'AVAILABLE_INVENTORY');
            $this->assertNull($sr18InventoryRow, 'Follow-up child SR-18 must NOT appear under AVAILABLE_INVENTORY before procurement processes it!');

            // Assert that SR-16 does NOT appear under AVAILABLE_INVENTORY!
            $sr16InventoryRow = $rows->first(fn ($r) => (int) $r['supply_request_id'] === (int) $sr16->supply_request_id
                && ($r['release_basis'] ?? '') === 'AVAILABLE_INVENTORY');
            $this->assertNull($sr16InventoryRow, 'SR-16 shortage is queued for procurement and must NOT leak into AVAILABLE_INVENTORY!');
        });

        // 2. Focused reason for SR-18 confirms it awaits verified delivery
        $focusResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create', [
            'supply_request_id' => $sr18->supply_request_id,
        ]));
        $focusResponse->assertOk();
        $focusResponse->assertInertia(function ($page) {
            $reason = (string) ($page->toArray()['props']['focusReleasableReason'] ?? '');
            $this->assertStringContainsString('procurement', strtolower($reason));
        });

        // 3. Attempting to directly create an available inventory distribution for SR-18 must fail validation
        $directReleaseResponse = $this->actingAs($gsoUser)->post(route('inventory.distribution-records.store'), [
            'supply_request_id' => $sr18->supply_request_id,
            'delivery_id' => null,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $directReleaseResponse->assertSessionHasErrors(['supply_request_id']);

        // 4. Now procurement processes the follow-up PR, places PO, and Delivery 2 arrives
        $followUpPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-TEST-2026-09-0018',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr18->supply_request_id,
            'created_by' => $gsoUser->id,
            'supplier_id' => $supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 10000,
        ]);
        $followUpPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $sr18->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_ordered' => 100,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 10000,
        ]);

        $del02 = Delivery::query()->create([
            'delivery_code' => 'DEL-TEST-2026-09-0018',
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $followUpPoi->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $invService->updateFromVerifiedDelivery($del02, $gsoUser);

        // Now viewing /inventory/distribution-records/create shows Delivery 2 under VERIFIED_DELIVERY!
        $createResponseAfterDelivery = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $createResponseAfterDelivery->assertOk();
        $createResponseAfterDelivery->assertInertia(function ($page) use ($del02) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $deliveryRow = $rows->first(fn ($r) => (int) ($r['delivery_id'] ?? 0) === (int) $del02->delivery_id);
            $this->assertNotNull($deliveryRow, 'Verified follow-up Delivery 2 must appear in eligible distribution rows!');
            $this->assertSame('VERIFIED_DELIVERY', $deliveryRow['release_basis']);
        });

        // Direct release of follow-up Delivery 2 to parent SR-16 is blocked
        try {
            $distService->createRecordWithAutomaticRelease($gsoUser, [
                'supply_request_id' => $sr16->supply_request_id,
                'delivery_id' => $del02->delivery_id,
                'office_id' => $office->id,
            ]);
            $this->fail('Direct release of follow-up delivery to parent SR-16 must be blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // Release the verified Delivery 2 to child SR-18
        $distFollowUp = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr18->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(100.0, (float) $distFollowUp->items->sum('quantity_released'));
    }

    public function test_claim_follow_up_pr_cannot_be_released_from_inventory_and_office_tracking_does_not_show_ready_for_release(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Gov Supplies Inc', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->first();
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'TEST-PEN-BLUE',
            'item_name' => 'Ballpen Blue Test',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $inv = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);

        // Original SR-16 for 40 boxes
        $sr16 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0016',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Claim origin request',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri16 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr16->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_requested' => 40,
        ]);

        $pr16 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0016',
            'supply_request_id' => $sr16->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
        ]);
        $pri16 = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr16->purchase_request_id,
            'item_id' => $item->item_id,
            'quantity_requested' => 40,
            'estimated_unit_cost' => 50,
            'estimated_total_cost' => 2000,
        ]);

        $po16 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0016',
            'purchase_request_id' => $pr16->purchase_request_id,
            'supply_request_id' => $sr16->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 2000,
        ]);
        $poi16 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po16->purchase_order_id,
            'purchase_request_item_id' => $pri16->purchase_request_item_id,
            'supply_request_item_id' => $sri16->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'unit' => 'box',
            'quantity_ordered' => 40,
            'unit_price' => 50,
            'total_price' => 2000,
        ]);

        $del12 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0012',
            'purchase_order_id' => $po16->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del12->delivery_id,
            'purchase_order_item_id' => $poi16->purchase_order_item_id,
            'expected_quantity' => 40,
            'received_quantity' => 40,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $invService = app(\App\Services\Inventory\InventoryService::class);
        $invService->updateFromVerifiedDelivery($del12, $gsoUser);

        $distService = app(DistributionRecordService::class);
        $distRecord = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr16->supply_request_id,
            'delivery_id' => $del12->delivery_id,
            'office_id' => $office->id,
        ]);
        $distItem = $distRecord->items->first();

        // Office receives items, but 10 are damaged/missing -> files Claim
        $claim = \App\Models\Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0001',
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $office->id,
            'claimed_by' => $officeUser->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_location' => 'Storage',
            'quantity_disputed' => 10,
            'status' => 'OPEN',
            'resolution_type' => null,
            'replacement_quantity' => 0,
            'claim_date' => now()->toDateString(),
            'remarks' => '10 boxes damaged upon opening package',
        ]);

        // GSO has 0 stock, so GSO updates claim status to PENDING_REPLACEMENT, creating follow-up PR
        $prService = app(PurchaseRequestService::class);
        $claimPr19 = $prService->createFromPendingClaimReplacement($claim, $gsoUser);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $claimPr19->source_type);
        $this->assertSame((int) $claim->claims_id, (int) $claimPr19->source_claim_id);

        $sr19 = $claimPr19->supplyRequest;
        $this->assertNotNull($sr19);

        // Warehouse receives 50 stock from an unrelated source or replenishment
        $inv->update(['stock_on_hand' => 50, 'available_stock' => 50]);

        // 1. Check Office Tracking page for SR-19:
        // Status must be IN_REVIEW, NEVER READY_FOR_RELEASE while PR is in procurement!
        $officeTrackingResponse = $this->actingAs($officeUser)->get(route('office.supply-requests.index'));
        $officeTrackingResponse->assertOk();
        $officeTrackingResponse->assertInertia(function ($page) use ($sr19) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $sr19Row = $requests->first(fn ($r) => (int) $r['supply_request_id'] === (int) $sr19->supply_request_id);
            $this->assertNotNull($sr19Row);
            $this->assertNotSame('READY_FOR_RELEASE', $sr19Row['status'], 'Claim follow-up child SR-19 must not show READY_FOR_RELEASE before delivery!');
            $this->assertSame('IN_REVIEW', $sr19Row['status']);
        });

        // 2. Check /inventory/distribution-records/create
        // SR-19 must NOT appear under AVAILABLE_INVENTORY!
        $createResponse = $this->actingAs($gsoUser)->get(route('inventory.distribution-records.create'));
        $createResponse->assertOk();
        $createResponse->assertInertia(function ($page) use ($sr19) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $sr19InventoryRow = $rows->first(fn ($r) => (int) $r['supply_request_id'] === (int) $sr19->supply_request_id
                && ($r['release_basis'] ?? '') === 'AVAILABLE_INVENTORY');
            $this->assertNull($sr19InventoryRow, 'Claim follow-up child SR-19 must NOT appear under AVAILABLE_INVENTORY!');
        });

        // 3. Attempting direct release from available inventory without delivery context fails validation
        $directReleaseResponse = $this->actingAs($gsoUser)->post(route('inventory.distribution-records.store'), [
            'supply_request_id' => $sr19->supply_request_id,
            'delivery_id' => null,
            'office_id' => $office->id,
            'release_date' => now()->toDateString(),
        ]);
        $directReleaseResponse->assertSessionHasErrors(['supply_request_id']);

        // 4. Attempting processDistribution from office supply requests fails with error redirect
        $processDistResponse = $this->actingAs($gsoUser)->post(route('inventory.office-supply-requests.process-distribution', $sr19->supply_request_id));
        $processDistResponse->assertSessionHas('error');

        // 5. Procurement creates PO-19, and replacement Delivery DEL-19 arrives
        $po19 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0019',
            'purchase_request_id' => $claimPr19->purchase_request_id,
            'supply_request_id' => $sr19->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'procurement_method' => 'SHOPPING',
            'status' => 'APPROVED',
            'total_amount' => 500,
        ]);
        $poi19 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po19->purchase_order_id,
            'purchase_request_item_id' => $claimPr19->items->first()->purchase_request_item_id,
            'supply_request_item_id' => $sr19->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'unit' => 'box',
            'quantity_ordered' => 10,
            'unit_price' => 50,
            'total_price' => 500,
        ]);

        $del19 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0019',
            'purchase_order_id' => $po19->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del19->delivery_id,
            'purchase_order_item_id' => $poi19->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 10,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);
        $invService->updateFromVerifiedDelivery($del19, $gsoUser);

        // 6. Now Office Tracking shows READY_FOR_RELEASE!
        $officeTrackingAfterDelivery = $this->actingAs($officeUser)->get(route('office.supply-requests.index'));
        $officeTrackingAfterDelivery->assertOk();
        $officeTrackingAfterDelivery->assertInertia(function ($page) use ($sr19) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $sr19Row = $requests->first(fn ($r) => (int) $r['supply_request_id'] === (int) $sr19->supply_request_id);
            $this->assertNotNull($sr19Row);
            $this->assertSame('READY_FOR_RELEASE', $sr19Row['status']);
        });

        // 7. GSO releases verified replacement delivery DEL-19 to SR-19
        $distClaimReplacement = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => $sr19->supply_request_id,
            'delivery_id' => $del19->delivery_id,
            'office_id' => $office->id,
        ]);
        $this->assertEquals(10.0, (float) $distClaimReplacement->items->sum('quantity_released'));

        // Claim status is now REPLACEMENT_RELEASED
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->fresh()->status);

        // Office receives replacement distribution record
        $receiveResponse = $this->actingAs($officeUser)->post(route('office.distribution-records.receive', $distClaimReplacement->distribution_record_id));
        $receiveResponse->assertSessionHasNoErrors();

        // Claim is now RESOLVED!
        $this->assertSame('RESOLVED', (string) $claim->fresh()->status);
    }
}
