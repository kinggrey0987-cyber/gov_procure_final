<?php

namespace Tests\Feature\Inventory;

use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\ExecutiveMonitoringService;
use App\Services\Inventory\DistributionRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimPartialReleaseAndReleasableDedupTest extends TestCase
{
    use RefreshDatabase;

    private User $gsoUser;
    private User $officeUser;
    private User $execUser;
    private Office $gsoOffice;
    private Office $requestingOffice;
    private Item $itemA4;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $gsoRole = Role::firstOrCreate(['name' => 'GSO Officer'], ['code' => 'GSO_OFFICER']);
        $officeRole = Role::firstOrCreate(['name' => 'Office User'], ['code' => 'OFFICE_USER']);
        $execRole = Role::firstOrCreate(['name' => 'Executive'], ['code' => 'EXECUTIVE']);

        $this->gsoOffice = Office::firstOrCreate(['name' => 'General Services Office'], ['code' => 'GSO']);
        $this->requestingOffice = Office::firstOrCreate(['name' => 'Engineering Office'], ['code' => 'ENG']);

        $this->gsoUser = User::factory()->create([
            'role_id' => $gsoRole->id,
            'office_id' => $this->gsoOffice->id,
        ]);

        $this->officeUser = User::factory()->create([
            'role_id' => $officeRole->id,
            'office_id' => $this->requestingOffice->id,
        ]);

        $this->execUser = User::factory()->create([
            'role_id' => $execRole->id,
            'office_id' => $this->gsoOffice->id,
        ]);

        $this->supplier = Supplier::create([
            'supplier_name' => 'Crown Paper Supplies',
            'contact_person' => 'Supplier Agent',
            'email' => 'crown@example.com',
            'phone' => '09123456789',
        ]);

        $category = ItemCategory::firstOrCreate(['category_name' => 'Office Supplies'], ['status' => 'ACTIVE']);
        $this->itemA4 = Item::create([
            'item_code' => 'ITEM-A4-BOND',
            'item_name' => 'A4 Bond Paper',
            'item_category_id' => $category->item_category_id,
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        Inventory::create([
            'item_id' => $this->itemA4->item_id,
            'stock_on_hand' => 200,
            'available_stock' => 200,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_gso_can_directly_release_partial_replacement_for_claim(): void
    {
        // 1. Initial supply request and distribution of 300 boxes
        $sr = SupplyRequest::create([
            'request_code' => 'SR-ENG-2026-001',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office Papers',
            'status' => 'RECEIVED',
        ]);

        SupplyRequestItem::create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 300,
        ]);

        $distRecord = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-001',
            'office_id' => $this->requestingOffice->id,
            'supply_request_id' => $sr->supply_request_id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
            'prepared_by' => $this->gsoUser->id,
            'received_by' => $this->officeUser->id,
        ]);

        $distItem = DistributionItem::create([
            'distribution_record_id' => $distRecord->distribution_record_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_released' => 300,
            'quantity_received' => 0,
            'quantity_damaged' => 300,
            'quantity_missing' => 0,
            'unit' => 'box',
        ]);

        // 2. Office filed a claim for 300 damaged boxes
        $claim = Claim::create([
            'claim_code' => 'CLM-2026-00001',
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $this->requestingOffice->id,
            'claimed_by' => $this->officeUser->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_location' => 'Engineering Warehouse',
            'quantity_disputed' => 300,
            'quantity_damaged' => 300,
            'quantity_missing' => 0,
            'status' => 'OPEN',
            'resolution_type' => null,
            'replacement_quantity' => 0,
            'claim_date' => now()->toDateString(),
            'remarks' => '300 boxes water damaged',
        ]);

        // 3. GSO has 200 boxes in warehouse inventory.
        // GSO submits status update to PARTIALLY_RESOLVED with replacement_quantity = 200
        $response = $this->actingAs($this->gsoUser)->put(
            route('inventory.claims.update-status', $claim->claims_id),
            [
                'status' => 'PARTIALLY_RESOLVED',
                'resolution_type' => 'PARTIAL_REPLACEMENT',
                'replacement_quantity' => 200,
                'remarks' => 'Released initial 200 boxes from available stock; remaining 100 will follow.',
            ]
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $claim->refresh();
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->status);
        $this->assertSame('PARTIAL_REPLACEMENT', (string) $claim->resolution_type);
        $this->assertEquals(200.0, (float) $claim->replacement_quantity);

        // Warehouse inventory was decremented by 200
        $inv = Inventory::where('item_id', $this->itemA4->item_id)->first();
        $this->assertEquals(0.0, (float) $inv->available_stock);

        // 4. Office verifies and accepts the partial 200 replacement
        $verifyResponse = $this->actingAs($this->officeUser)->post(
            route('office.claims.verify-replacement', $claim->claims_id),
            ['remarks' => 'Verified 200 boxes in good condition.']
        );

        $verifyResponse->assertSessionHasNoErrors();
        $claim->refresh();
        // Since 200 < 300, claim remains PARTIALLY_RESOLVED
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->status);
        $this->assertEquals(200.0, (float) $claim->replacement_quantity);

        // 5. Next delivery of 100 arrives into warehouse inventory
        $inv->forceFill([
            'stock_on_hand' => 100,
            'available_stock' => 100,
        ])->save();

        // GSO updates status to REPLACEMENT_RELEASED with replacement_quantity = 100
        $finalReleaseResponse = $this->actingAs($this->gsoUser)->put(
            route('inventory.claims.update-status', $claim->claims_id),
            [
                'status' => 'REPLACEMENT_RELEASED',
                'resolution_type' => 'REPLACEMENT_RELEASED',
                'replacement_quantity' => 100,
                'remarks' => 'Final 100 boxes replacement released.',
            ]
        );

        $finalReleaseResponse->assertSessionHasNoErrors();
        $claim->refresh();
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->status);
        $this->assertEquals(300.0, (float) $claim->replacement_quantity);

        // Office verifies the final replacement
        $finalVerify = $this->actingAs($this->officeUser)->post(
            route('office.claims.verify-replacement', $claim->claims_id),
            ['remarks' => 'Remaining 100 boxes verified. All 300 complete.']
        );

        $finalVerify->assertSessionHasNoErrors();
        $claim->refresh();
        $this->assertSame('RESOLVED', (string) $claim->status);
    }

    public function test_follow_up_request_can_release_available_stock_without_zero_quota_error(): void
    {
        $distService = app(DistributionRecordService::class);

        // 1. Root request of 300
        $rootSr = SupplyRequest::create([
            'request_code' => 'SR-ENG-ROOT-0025',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Root Requisition',
            'status' => 'RECEIVED',
        ]);

        SupplyRequestItem::create([
            'supply_request_id' => $rootSr->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 300,
        ]);

        $rootDist = DistributionRecord::create([
            'distribution_code' => 'DIST-2026-09-0025',
            'office_id' => $this->requestingOffice->id,
            'supply_request_id' => $rootSr->supply_request_id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
            'prepared_by' => $this->gsoUser->id,
            'received_by' => $this->officeUser->id,
        ]);

        // Distribution had 300 damaged (discrepancy)
        DistributionItem::create([
            'distribution_record_id' => $rootDist->distribution_record_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_released' => 300,
            'quantity_received' => 0,
            'quantity_damaged' => 300,
            'quantity_missing' => 0,
            'unit' => 'box',
        ]);

        // 2. Follow-up child SR created for 300
        $childSr = SupplyRequest::create([
            'request_code' => 'SR-ENG-FOLLOWUP-0026',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Follow-up for damaged stock',
            'status' => 'APPROVED',
        ]);

        SupplyRequestItem::create([
            'supply_request_id' => $childSr->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 300,
        ]);

        // Link child to parent via follow-up PR
        PurchaseRequest::create([
            'pr_number' => 'PR-FOLLOWUP-0026',
            'supply_request_id' => $childSr->supply_request_id,
            'source_supply_request_id' => $rootSr->supply_request_id,
            'source_type' => 'REQUISITION_FOLLOW_UP',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        // 3. Warehouse has 200 boxes available
        $inv = Inventory::where('item_id', $this->itemA4->item_id)->first();
        $inv->forceFill([
            'stock_on_hand' => 200,
            'available_stock' => 200,
        ])->save();

        // 4. GSO creates distribution record for child SR — MUST NOT FAIL WITH "No requested item currently has a releasable quantity"
        $newDist = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'office_id' => $this->requestingOffice->id,
            'supply_request_id' => $childSr->supply_request_id,
        ]);

        $this->assertInstanceOf(DistributionRecord::class, $newDist);
        $this->assertEquals(200.0, (float) $newDist->items->sum('quantity_released'));
    }

    public function test_stock_ready_releasable_table_excludes_phantom_delivery_rows(): void
    {
        $testSr = SupplyRequest::create([
            'request_code' => 'SR-TEST-PO-001',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'PO Test',
            'status' => 'APPROVED',
        ]);

        $testPr = PurchaseRequest::create([
            'pr_number' => 'PR-TEST-PO-001',
            'supply_request_id' => $testSr->supply_request_id,
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $testSri = SupplyRequestItem::create([
            'supply_request_id' => $testSr->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 50,
        ]);

        // Verified delivery for 50 items
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-DEDUP-001',
            'purchase_request_id' => $testPr->purchase_request_id,
            'supply_request_id' => $testSr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $testSri->supply_request_item_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_ordered' => 50,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 5000,
        ]);

        $delivery = Delivery::create([
            'delivery_code' => 'DEL-TEST-DEDUP-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
        ]);

        // A fully satisfied request
        $srSatisfied = SupplyRequest::create([
            'request_code' => 'SR-SATISFIED-001',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Already Fulfilled',
            'status' => 'RECEIVED',
        ]);

        SupplyRequestItem::create([
            'supply_request_id' => $srSatisfied->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 10,
        ]);

        // Release 10 for the satisfied request
        $dist = DistributionRecord::create([
            'distribution_code' => 'DIST-SATISFIED-001',
            'office_id' => $this->requestingOffice->id,
            'supply_request_id' => $srSatisfied->supply_request_id,
            'status' => 'RECEIVED',
            'prepared_by' => $this->gsoUser->id,
        ]);

        DistributionItem::create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_released' => 10,
            'quantity_received' => 10,
            'unit' => 'box',
        ]);

        // Link the satisfied SR to the PO
        $po->forceFill(['supply_request_id' => $srSatisfied->supply_request_id])->save();

        // GSO visits distribution records create page
        $response = $this->actingAs($this->gsoUser)->get(route('inventory.distribution-records.create'));
        $response->assertOk();

        // Because srSatisfied has 0 remaining needed quantity, it must NOT show up as a releasable row
        $response->assertInertia(function ($page) use ($srSatisfied) {
            $candidateRows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $hasSatisfiedRow = $candidateRows->contains('supply_request_id', (int) $srSatisfied->supply_request_id);
            $this->assertFalse($hasSatisfiedRow, 'Satisfied request must not appear in releasable table.');
        });
    }

    public function test_executive_procurement_analytics_status_filter_harmonization(): void
    {
        $monitoringService = app(ExecutiveMonitoringService::class);

        $analyticsSr = SupplyRequest::create([
            'request_code' => 'SR-ANALYTICS-001',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Analytics Test',
            'status' => 'APPROVED',
        ]);

        // Create an approved PR and a placed PO
        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-ANALYTICS-001',
            'supply_request_id' => $analyticsSr->supply_request_id,
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-ANALYTICS-001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $analyticsSr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'total_amount' => 50000.00,
            'status' => 'PLACED',
        ]);

        // When filtering by PR status "APPROVED":
        // PO spend should NOT be wiped to 0 because the PO belongs to this approved PR!
        $data = $monitoringService->procurementAnalytics(['status' => 'APPROVED']);
        $this->assertEquals(1, $data['totals']['purchase_request_count']);
        $this->assertEquals(1, $data['totals']['purchase_order_count']);
        $this->assertEquals(50000.00, $data['totals']['purchase_order_total_amount']);

        // When record_type is "pr", PO metrics are cleanly scoped
        $prData = $monitoringService->procurementAnalytics(['record_type' => 'pr']);
        $this->assertGreaterThanOrEqual(1, $prData['totals']['purchase_request_count']);
        $this->assertEquals(0, $prData['totals']['purchase_order_count']);
        $this->assertEquals(0.0, $prData['totals']['purchase_order_total_amount']);

        // Monthly trends are continuously padded without missing calendar gaps
        $trendData = $monitoringService->procurementAnalytics(['year' => '2026']);
        $months = collect($trendData['monthly_trends']['purchase_requests'])->pluck('month');
        $this->assertTrue($months->contains('2026-01'));
        $this->assertTrue($months->contains('2026-12'));
    }

    public function test_partially_received_delivery_can_release_received_portion_without_crashing(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(\App\Services\Procurement\PurchaseRequestService::class);

        // 1. Initial supply request of 300 boxes
        $sr = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0026',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Procurement requisition',
            'status' => 'APPROVED',
        ]);

        $sri = SupplyRequestItem::create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 300,
        ]);

        $pr = PurchaseRequest::create([
            'pr_number' => 'PR-2026-09-0026',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-09-0026',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'APPROVED',
            'total_amount' => 30000,
        ]);

        $poi = PurchaseOrderItem::create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);

        // 2. Delivery arrives with 200 accepted and 100 missing -> PARTIALLY_RECEIVED
        $delivery = Delivery::create([
            'delivery_code' => 'DEL-2026-09-0026',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        $di = DeliveryItem::create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        $inv = Inventory::where('item_id', $this->itemA4->item_id)->first();
        $inv->forceFill([
            'stock_on_hand' => 200,
            'available_stock' => 200,
        ])->save();

        // 3. GSO creates a follow-up PR for the 100 discrepancy items
        $followUpPr = $prService->createFromPartialDelivery($delivery, $this->gsoUser);
        $this->assertInstanceOf(PurchaseRequest::class, $followUpPr);
        $childSr = $followUpPr->supplyRequest;
        $this->assertNotNull($childSr);
        $this->assertEquals(100.0, (float) $childSr->items->sum('quantity_requested'));

        // 4. In Supply Distribution, DEL-2026-09-0026 must be releasable for parent $sr,
        // and MUST NOT display $childSr under DEL-2026-09-0026 because $childSr is waiting for future delivery!
        $response = $this->actingAs($this->gsoUser)->get(route('inventory.distribution-records.create'));
        $response->assertOk();
        $response->assertInertia(function ($page) use ($sr, $childSr, $delivery) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $parentRow = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $sr->supply_request_id && $r['delivery_id'] === (int) $delivery->delivery_id);
            $this->assertNotNull($parentRow, 'Parent request SR-26 must be releasable against DEL-2026-09-0026.');

            $childRow = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $childSr->supply_request_id && $r['delivery_id'] === (int) $delivery->delivery_id);
            $this->assertNull($childRow, 'Child follow-up SR must NOT be paired with the delivery from which it was created as a discrepancy.');
        });

        // 5. GSO releases the 200 boxes from DEL-2026-09-0026 for parent $sr
        // MUST NOT FAIL WITH "No requested item currently has a releasable quantity."
        $dist = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $this->requestingOffice->id,
        ]);

        $this->assertInstanceOf(DistributionRecord::class, $dist);
        $this->assertEquals(200.0, (float) $dist->items->sum('quantity_released'));
        $this->assertSame('RELEASED', (string) $dist->status);

        // 6. Office acknowledges receipt of the 200 boxes
        $receiveResponse = $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist->distribution_record_id));
        $receiveResponse->assertSessionHasNoErrors();
        $this->assertSame('RECEIVED', (string) $dist->fresh()->status);

        // Office tracking shows PARTIALLY_RECEIVED for $sr (200 of 300 received)
        $trackingResponse = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(function ($page) use ($sr) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $srData = $requests->firstWhere('supply_request_id', (int) $sr->supply_request_id);
            $this->assertNotNull($srData);
            $this->assertSame('PARTIALLY_RECEIVED', $srData['status']);
        });
    }

    public function test_lineage_fulfillment_prevents_ghost_ready_for_release_and_syncs_orphaned_followup(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(\App\Services\Procurement\PurchaseRequestService::class);
        $poService = app(\App\Services\Procurement\PurchaseOrderService::class);

        // 1. Initial requisition SR-03 for 70 bond paper
        $sr03 = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => '70 items bond paper A4',
            'status' => 'APPROVED',
        ]);

        $sri03 = SupplyRequestItem::create([
            'supply_request_id' => $sr03->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 70,
        ]);

        // Inventory has 4 in stock. First initial release of 4 to SR-03.
        $inv = Inventory::where('item_id', $this->itemA4->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 4, 'available_stock' => 4])->save();

        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr03->supply_request_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->assertEquals(4.0, (float) $dist1->items->sum('quantity_released'));
        // Office receives the initial 4
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id));

        // 2. 66 was procured: PR created for 66, PO placed for 66
        $pr01 = PurchaseRequest::create([
            'pr_number' => 'PR-2026-09-0003',
            'supply_request_id' => $sr03->supply_request_id,
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po01 = PurchaseOrder::create([
            'po_number' => 'PO-2026-09-0003',
            'purchase_request_id' => $pr01->purchase_request_id,
            'supply_request_id' => $sr03->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 6600,
        ]);

        $poi01 = PurchaseOrderItem::create([
            'purchase_order_id' => $po01->purchase_order_id,
            'supply_request_item_id' => $sri03->supply_request_item_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_ordered' => 66,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 6600,
        ]);

        // 3. Delivery 1 arrives: 60 accepted, 6 missing discrepancy
        $del01 = Delivery::create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po01->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poi01->purchase_order_item_id,
            'expected_quantity' => 66,
            'received_quantity' => 60,
            'damaged_quantity' => 0,
            'missing_quantity' => 6,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // 4. Follow-up PR created for the 6 missing items -> creates child SR-04
        $followUpPr = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr04 = $followUpPr->supplyRequest;
        $this->assertNotNull($sr04);
        $this->assertEquals(6.0, (float) $sr04->items->sum('quantity_requested'));

        // 5. GSO releases 60 for SR-03 from Delivery 1
        $inv->forceFill(['stock_on_hand' => 60, 'available_stock' => 60])->save();
        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr03->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->assertEquals(60.0, (float) $dist2->items->sum('quantity_released'));
        // Office receives the 60 items
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        // Now SR-03 has received 4 + 60 = 64 items.
        // 6. PO for the follow-up PR is placed and Delivery 2 arrives with 6 items
        $followUpPr->forceFill(['status' => 'PROCESSING'])->save();
        $bacRole = Role::firstOrCreate(['code' => 'PROC_OFFICER'], ['name' => 'Procurement Officer']);
        $bacOffice = Office::firstOrCreate(['code' => 'BAC'], ['name' => 'Procurement Office']);
        $procurementUser = User::factory()->create([
            'role_id' => $bacRole->id,
            'office_id' => $bacOffice->id,
        ]);
        $po02 = $poService->create($procurementUser, [
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supplier_id' => $this->supplier->supplier_id,
        ]);
        $po02->forceFill(['status' => 'PLACED'])->save();
        $poi02 = $po02->items->first();

        $del02 = Delivery::create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po02->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $poi02->purchase_order_item_id,
            'expected_quantity' => 6,
            'received_quantity' => 6,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // 7. Verify GSO Supply Distribution candidate rows:
        // Delivery 2 MUST ONLY target child SR-04, NEVER duplicate for SR-03!
        $createResponse = $this->actingAs($this->gsoUser)->get(route('inventory.distribution-records.create'));
        $createResponse->assertOk();
        $createResponse->assertInertia(function ($page) use ($sr03, $sr04, $del02) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $sr04Row = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $sr04->supply_request_id && $r['delivery_id'] === (int) $del02->delivery_id);
            $sr03Row = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $sr03->supply_request_id && $r['delivery_id'] === (int) $del02->delivery_id);

            $this->assertNotNull($sr04Row, 'SR-04 must be available for release against Delivery 2.');
            $this->assertNull($sr03Row, 'Parent SR-03 must NOT duplicate or hijack Delivery 2.');
        });

        // 8. GSO releases the 6 items for SR-04
        $inv->forceFill(['stock_on_hand' => 6, 'available_stock' => 6])->save();
        $dist3 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr04->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->assertEquals(6.0, (float) $dist3->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist3->distribution_record_id));

        // 9. Now office tracking MUST show RECEIVED for BOTH SR-03 and SR-04!
        // SR-04 must NOT be stuck in READY_FOR_RELEASE!
        $trackingResponse = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(function ($page) use ($sr03, $sr04) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $sr03Data = $requests->firstWhere('supply_request_id', (int) $sr03->supply_request_id);
            $sr04Data = $requests->firstWhere('supply_request_id', (int) $sr04->supply_request_id);

            $this->assertNotNull($sr03Data);
            $this->assertSame('RECEIVED', $sr03Data['status']);

            $this->assertNotNull($sr04Data);
            $this->assertSame('RECEIVED', $sr04Data['status']);
        });

        $this->assertSame('RECEIVED', $sr03->fresh()->status);
        $this->assertSame('RECEIVED', $sr04->fresh()->status);
    }

    public function test_releasing_delivery_to_parent_automatically_marks_followup_child_as_received(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(\App\Services\Procurement\PurchaseRequestService::class);

        // 1. Initial requisition SR-03 for 70 bond paper
        $sr03 = SupplyRequest::create([
            'request_code' => 'SR-MPDO-2026-09-0003-LEGACY',
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => '70 items bond paper A4',
            'status' => 'APPROVED',
        ]);

        $sri03 = SupplyRequestItem::create([
            'supply_request_id' => $sr03->supply_request_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_requested' => 70,
        ]);

        // Inventory has 4 in stock. First initial release of 4 to SR-03.
        $inv = Inventory::where('item_id', $this->itemA4->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 4, 'available_stock' => 4])->save();

        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr03->supply_request_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id));

        // 2. 66 procured: PR and PO
        $pr01 = PurchaseRequest::create([
            'pr_number' => 'PR-2026-09-0003-LEGACY',
            'supply_request_id' => $sr03->supply_request_id,
            'office_id' => $this->requestingOffice->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);

        $po01 = PurchaseOrder::create([
            'po_number' => 'PO-2026-09-0003-LEGACY',
            'purchase_request_id' => $pr01->purchase_request_id,
            'supply_request_id' => $sr03->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 6600,
        ]);

        $poi01 = PurchaseOrderItem::create([
            'purchase_order_id' => $po01->purchase_order_id,
            'supply_request_item_id' => $sri03->supply_request_item_id,
            'item_id' => $this->itemA4->item_id,
            'item_name_snapshot' => $this->itemA4->item_name,
            'quantity_ordered' => 66,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 6600,
        ]);

        // Delivery 1 arrives: 60 accepted, 6 missing discrepancy
        $del01 = Delivery::create([
            'delivery_code' => 'DEL-2026-09-0001-LEGACY',
            'purchase_order_id' => $po01->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poi01->purchase_order_item_id,
            'expected_quantity' => 66,
            'received_quantity' => 60,
            'damaged_quantity' => 0,
            'missing_quantity' => 6,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Follow-up PR created -> SR-04 for 6 items
        $followUpPr = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr04 = $followUpPr->supplyRequest;

        // Release 60 for SR-03 from Delivery 1
        $inv->forceFill(['stock_on_hand' => 60, 'available_stock' => 60])->save();
        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr03->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        // Delivery 2 arrives for the follow-up with 6 items
        $del02 = Delivery::create([
            'delivery_code' => 'DEL-2026-09-0002-LEGACY',
            'purchase_order_id' => $po01->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);

        DeliveryItem::create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $poi01->purchase_order_item_id,
            'expected_quantity' => 6,
            'received_quantity' => 6,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Release the 6 items to SR-03 directly (the exact user scenario!)
        $inv->forceFill(['stock_on_hand' => 6, 'available_stock' => 6])->save();
        $dist3 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr03->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->requestingOffice->id,
        ]);
        $this->assertEquals(6.0, (float) $dist3->items->sum('quantity_released'));

        // Office receives the 6 items on SR-03
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist3->distribution_record_id));

        // Total received on SR-03 is 4 + 60 + 6 = 70.
        // Parent SR-03 is fully received!
        $this->assertSame('RECEIVED', (string) $sr03->fresh()->status);

        // Child SR-04 was automatically synced to RECEIVED because the parent lineage is fully received!
        $this->assertSame('RECEIVED', (string) $sr04->fresh()->status);

        // Office tracking shows RECEIVED for both, and SR-04 is NEVER stuck on READY FOR RELEASE
        $trackingResponse = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(function ($page) use ($sr03, $sr04) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $sr03Data = $requests->firstWhere('supply_request_id', (int) $sr03->supply_request_id);
            $sr04Data = $requests->firstWhere('supply_request_id', (int) $sr04->supply_request_id);

            $this->assertSame('RECEIVED', $sr03Data['status']);
            $this->assertSame('RECEIVED', $sr04Data['status']);
        });
    }
}
