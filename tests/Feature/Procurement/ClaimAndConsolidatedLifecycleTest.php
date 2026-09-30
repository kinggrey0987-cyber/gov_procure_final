<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
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
use App\Services\Inventory\DistributionRecordService;
use App\Services\Inventory\SupplyRequestFollowUpService;
use App\Services\Procurement\ProcurementStatusSyncService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ClaimAndConsolidatedLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $gsoUser;
    protected User $procurementUser;
    protected User $officeUser;
    protected Office $office;
    protected Supplier $supplier;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $this->office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $this->gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $this->office->id]);
        $this->procurementUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $this->office->id]);
        $this->officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $this->office->id]);

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Gov Supplies Inc', 'status' => 'ACTIVE']);
        $category = ItemCategory::query()->create(['category_name' => 'Stationery', 'code' => 'STAT', 'status' => 'ACTIVE']);
        $this->item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'PAPER-01',
            'item_name' => 'Bond Paper A4',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $this->item->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_claim_pr_returned_for_revision_displays_returned_status_and_blocks_delete(): void
    {
        // 1. Initial supply request and received distribution
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0001',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
        ]);

        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'released_by' => $this->gsoUser->id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
        ]);
        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_released' => 10,
            'quantity_received' => 5,
            'quantity_damaged' => 5,
            'unit' => 'box',
        ]);

        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0001',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'claimed_by' => $this->officeUser->id,
            'office_id' => $this->office->id,
            'claim_date' => now()->toDateString(),
            'claim_type' => 'REPLACEMENT',
            'claim_location' => 'Office receiving desk',
            'issue_type' => 'DAMAGED_ITEMS',
            'quantity_disputed' => 5,
            'quantity_damaged' => 5,
            'status' => 'APPROVED',
        ]);

        // Follow-up replacement PR created by GSO
        $prService = app(PurchaseRequestService::class);
        $replacementPr = $prService->createFromPendingClaimReplacement($claim, $this->gsoUser, 'Procure replacement');
        $childSr = $replacementPr->supplyRequest;

        $this->assertTrue($replacementPr->is_claim_replacement);

        // 1. Attempting to return the replacement PR for revision MUST FAIL
        $returnResponse = $this->actingAs($this->procurementUser)->post(
            route('procurement.purchase-requests.process', $replacementPr->purchase_request_id),
            [
                'action' => 'return',
                'remarks' => 'Please revise specifications',
            ]
        );
        $returnResponse->assertSessionHasErrors(['action']);

        // Directly calling service also throws ValidationException
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $prService->returnForRevision($replacementPr, $this->procurementUser, 'Please revise specifications');
    }

    public function test_claim_pr_rejection_by_procurement_closes_and_rejects_linked_claim_and_blocks_item_edit(): void
    {
        // 1. Initial supply request and received distribution
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0002',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
        ]);

        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0002',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'released_by' => $this->gsoUser->id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
        ]);
        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_released' => 10,
            'quantity_received' => 5,
            'quantity_damaged' => 5,
            'unit' => 'box',
        ]);

        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0002',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'claimed_by' => $this->officeUser->id,
            'office_id' => $this->office->id,
            'claim_date' => now()->toDateString(),
            'claim_type' => 'REPLACEMENT',
            'claim_location' => 'Office receiving desk',
            'issue_type' => 'DAMAGED_ITEMS',
            'quantity_disputed' => 5,
            'quantity_damaged' => 5,
            'status' => 'APPROVED',
        ]);

        $prService = app(PurchaseRequestService::class);
        $replacementPr = $prService->createFromPendingClaimReplacement($claim, $this->gsoUser, 'Procure replacement');
        $childSr = $replacementPr->supplyRequest;

        // 2. Modifying items on the follow-up SR or PR is strictly blocked even in draft
        $childSr->forceFill(['status' => 'DRAFT'])->save();
        $srService = app(\App\Services\Office\SupplyRequestService::class);
        try {
            $srService->addItem($childSr, $this->officeUser, ['ppmp_item_id' => 999, 'quantity_requested' => 10]);
            $this->fail('Expected ValidationException when adding item to follow-up SR.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        try {
            $prService->addItem($replacementPr, $this->procurementUser, ['item_id' => $this->item->item_id, 'quantity_requested' => 10]);
            $this->fail('Expected ValidationException when adding item to follow-up PR.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('purchase_request', $e->errors());
        }

        // 3. Procurement rejects the claim replacement PR
        $rejectResponse = $this->actingAs($this->procurementUser)->post(
            route('procurement.purchase-requests.process', $replacementPr->purchase_request_id),
            [
                'action' => 'reject',
                'remarks' => 'Item is obsolete and cannot be procured by BAC.',
            ]
        );
        $rejectResponse->assertRedirect(route('procurement.purchase-requests.show', ['purchase_request' => $replacementPr->purchase_request_id]));

        // 4. Verify all statuses: PR is REJECTED, SR is REJECTED, Claim is REJECTED (closed)
        $this->assertSame('REJECTED', (string) $replacementPr->fresh()->status);
        $this->assertSame('REJECTED', (string) $childSr->fresh()->status);
        $this->assertSame('REJECTED', (string) $claim->fresh()->status);
        $this->assertSame('REJECTED', (string) $claim->fresh()->resolution_type);
        $this->assertStringContainsString('Item is obsolete', (string) $claim->fresh()->remarks);
    }

    public function test_partial_replacement_delivery_maintains_claim_and_supply_request_partially_received_status(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);

        // 1. Initial supply request for 100 items
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0002',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Annual stock',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        $dist1 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0002',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'released_by' => $this->gsoUser->id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
        ]);
        $dist1Item = DistributionItem::query()->create([
            'distribution_record_id' => $dist1->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_released' => 100,
            'quantity_received' => 50,
            'quantity_damaged' => 50,
            'unit' => 'box',
        ]);

        // File claim for 50 damaged items
        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0002',
            'distribution_record_id' => $dist1->distribution_record_id,
            'distribution_item_id' => $dist1Item->distribution_item_id,
            'claimed_by' => $this->officeUser->id,
            'office_id' => $this->office->id,
            'claim_date' => now()->toDateString(),
            'claim_type' => 'REPLACEMENT',
            'claim_location' => 'Office receiving desk',
            'issue_type' => 'DAMAGED_ITEMS',
            'quantity_disputed' => 50,
            'quantity_damaged' => 50,
            'status' => 'APPROVED',
        ]);

        // GSO creates follow-up replacement PR for 50
        $replacementPr = $prService->createFromPendingClaimReplacement($claim, $this->gsoUser, 'Procure 50 replacement');
        $childSr = $replacementPr->supplyRequest;

        // Procurement processes PR into PO
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0002',
            'purchase_request_id' => $replacementPr->purchase_request_id,
            'supply_request_id' => $childSr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 5000,
        ]);
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $childSr->items->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 50,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 5000,
        ]);

        // Supplier delivers only 30 replacement items (partial replacement delivery)
        $del = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 30,
            'damaged_quantity' => 0,
            'missing_quantity' => 20,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // GSO releases the 30 replacement items to the office
        $inv = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 30, 'available_stock' => 30])->save();

        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $childSr->supply_request_id,
            'delivery_id' => $del->delivery_id,
            'office_id' => $this->office->id,
        ]);

        // Office receives the 30 replacement items
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        // Claim must be PARTIALLY_RESOLVED (received 30 out of 50)
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->fresh()->status);
        $this->assertEquals(30.0, (float) $claim->fresh()->replacement_quantity);

        // Child SR and Follow-up PR must NOT be marked RECEIVED / COMPLETED
        $this->assertNotSame('RECEIVED', (string) $childSr->fresh()->status);
        $this->assertNotSame('COMPLETED', (string) $replacementPr->fresh()->status);

        // Office tracking must reflect PARTIALLY_RECEIVED
        $trackingResponse = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $trackingResponse->assertOk();
        $trackingResponse->assertInertia(function ($page) use ($childSr) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $childData = $requests->firstWhere('supply_request_id', (int) $childSr->supply_request_id);
            $this->assertSame('PARTIALLY_RECEIVED', $childData['status']);
        });
    }

    public function test_claim_follow_up_delivery_verification_resolves_parent_partial_delivery_to_verified(): void
    {
        $prService = app(PurchaseRequestService::class);
        $verificationService = app(\App\Services\Delivery\DeliveryVerificationService::class);

        // 1. Initial supply request and PO for 200 items
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0017',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office supplies batch 17',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
        ]);

        $parentPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0017',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'APPROVED',
        ]);
        $parentPrItem = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $parentPr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'quantity_requested' => 200,
        ]);

        $parentPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0018',
            'purchase_request_id' => $parentPr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 20000,
        ]);
        $parentPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $parentPo->purchase_order_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 20000,
        ]);

        // 2. Delivery 17 arrives with only 150 items (PARTIALLY_RECEIVED)
        $del17 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0017',
            'purchase_order_id' => $parentPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del17->delivery_id,
            'purchase_order_item_id' => $parentPoi->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 150,
            'damaged_quantity' => 0,
            'missing_quantity' => 50,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // 3. Distribution of 150 items to office, with 50 missing reported in claim
        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0017',
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $del17->delivery_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'released_by' => $this->gsoUser->id,
            'status' => 'RECEIVED',
            'release_date' => now()->toDateString(),
        ]);
        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_released' => 150,
            'quantity_received' => 150,
            'quantity_missing' => 50,
            'unit' => 'box',
        ]);

        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0017',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'claimed_by' => $this->officeUser->id,
            'office_id' => $this->office->id,
            'claim_date' => now()->toDateString(),
            'claim_type' => 'REPLACEMENT',
            'claim_location' => 'Office receiving desk',
            'issue_type' => 'MISSING_ITEMS',
            'quantity_disputed' => 50,
            'quantity_missing' => 50,
            'status' => 'PENDING_REPLACEMENT',
        ]);

        // 4. Follow-up PR is generated from Claim -> check source_delivery_id is set
        $replacementPr = $prService->createFromPendingClaimReplacement($claim, $this->gsoUser, 'Procure 50 replacement');
        $this->assertEquals((int) $del17->delivery_id, (int) $replacementPr->source_delivery_id);
        $this->assertEquals('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $replacementPr->source_type);

        // 5. Follow-up PO is created for 50 items
        $followUpPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0019',
            'purchase_request_id' => $replacementPr->purchase_request_id,
            'supply_request_id' => $replacementPr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 5000,
        ]);
        $followUpPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $replacementPr->supplyRequest->items->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 50,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 5000,
        ]);

        // 6. Delivery 18 arrives with 50 replacement items
        $del18 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0018',
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'receipt_status' => 'RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del18->delivery_id,
            'purchase_order_item_id' => $followUpPoi->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'ACCEPTED',
        ]);

        // 7. Verify Delivery 18 with APPROVED status
        $this->actingAs($this->gsoUser)->post(
            route('inventory.delivery-verifications.store', $del18->delivery_id),
            [
                'verification_status' => 'APPROVED',
                'verification_date' => now()->toDateString(),
                'remarks' => 'All 50 replacement units verified and accepted.',
            ]
        );

        // 8. Assertions:
        // - Delivery 18 is VERIFIED
        $this->assertSame('VERIFIED', (string) $del18->fresh()->receipt_status);
        // - Delivery 17 (Parent) is automatically updated from PARTIALLY_RECEIVED to VERIFIED!
        $this->assertSame('VERIFIED', (string) $del17->fresh()->receipt_status);

        // - Parent PO is marked COMPLETED
        $this->assertSame('COMPLETED', (string) $parentPo->fresh()->status);
        // - Follow-up PO is marked COMPLETED
        $this->assertSame('COMPLETED', (string) $followUpPo->fresh()->status);
    }

    public function test_consolidated_pr_fulfillment_order_and_dynamic_rebinding(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        // SR-16: 200 items
        $sr16 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0016',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 16',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri16 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr16->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
        ]);

        // SR-17: 100 items
        $sr17 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0017',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 17',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri17 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr17->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        // Consolidated PR for 300 items total
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0015',
            'supply_request_id' => $sr16->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for requests %s, %s', $sr16->request_code, $sr17->request_code),
        ]);
        $pri = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 30000,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0015',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr16->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 30000,
        ]);
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $pri->purchase_request_item_id,
            'supply_request_item_id' => $sri16->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);

        // Delivery 1 arrives with 200 items (100 missing discrepancy)
        $del01 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0015',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        $del01Item = DeliveryItem::query()->create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // User scenario: GSO releases to SR-17 first!
        // Stock of 200 is available
        $inv = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 200, 'available_stock' => 200])->save();

        // Release 100 to SR-17 (fulfilling SR-17 100/100)
        $dist17 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr17->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist17->items->sum('quantity_released'));

        // Office receives SR-17
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist17->distribution_record_id))->assertRedirect();
        $this->assertSame('RECEIVED', (string) $sr17->fresh()->status);

        // Release remaining 100 from Delivery 1 to SR-16 (SR-16 received 100/200, still has 100 deficit)
        $dist16 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr16->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist16->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist16->distribution_record_id));
        $this->assertNotSame('RECEIVED', (string) $sr16->fresh()->status);

        // Office tracking index resolves SR-16 to PARTIALLY_RECEIVED
        $sr16TrackingRes = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $sr16TrackingRes->assertOk();
        $sr16TrackingRes->assertInertia(function ($page) use ($sr16) {
            $requests = collect($page->toArray()['props']['supplyRequests']['data'] ?? []);
            $sr16Data = $requests->firstWhere('supply_request_id', (int) $sr16->supply_request_id);
            $this->assertSame('PARTIALLY_RECEIVED', $sr16Data['status']);
        });

        // GSO creates follow-up PR for the 100 item deficit
        $followUpPr = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr18 = $followUpPr->supplyRequest;

        // Dynamic rebinding check:
        // Even if initial attribution touched SR-17 or consolidated root,
        // resolveParentSupplyRequestForDeliveryFollowUp rebinds to SR-16 because SR-17 is already 100% fulfilled!
        $resolvedParent = $followUpService->resolveParentSupplyRequestForDeliveryFollowUp($followUpPr, $sr18);
        $this->assertSame((int) $sr16->supply_request_id, (int) $resolvedParent->supply_request_id, 'Follow-up must bind to SR-16 which has the active shortage!');

        // Follow-up PR must NOT be completed
        $this->assertNotSame('COMPLETED', (string) $followUpPr->fresh()->status);
        $this->assertNotSame('RECEIVED', (string) $sr18->fresh()->status);

        // Process PO and Delivery 2 for the follow-up 100 items
        $followUpPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0018',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr18->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 10000,
        ]);
        $followUpPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $sr18->items->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 100,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 10000,
        ]);

        $del02 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0018',
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
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

        // Verify that child SR-18 is offered for release against follow-up Delivery 2 (parent SR-16 is blocked)
        $createResponse = $this->actingAs($this->gsoUser)->get(route('inventory.distribution-records.create'));
        $createResponse->assertOk();
        $createResponse->assertInertia(function ($page) use ($sr16, $sr18, $del02) {
            $rows = collect($page->toArray()['props']['eligibleRows']['data'] ?? []);
            $sr16Row = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $sr16->supply_request_id && $r['delivery_id'] === (int) $del02->delivery_id);
            $sr18Row = $rows->first(fn ($r) => $r['supply_request_id'] === (int) $sr18->supply_request_id && $r['delivery_id'] === (int) $del02->delivery_id);
            $this->assertNull($sr16Row, 'Parent SR-16 must NOT be offered for release against follow-up Delivery 2!');
            $this->assertNotNull($sr18Row, 'Child SR-18 must be eligible for release against follow-up Delivery 2!');
        });

        // Release the 100 items to child SR-18
        $inv->forceFill(['stock_on_hand' => 100, 'available_stock' => 100])->save();
        $distFollowUp = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr18->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(100.0, (float) $distFollowUp->items->sum('quantity_released'));

        // Office receives the final 100 items on SR-18
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $distFollowUp->distribution_record_id));

        // Now both parents SR-16 and SR-17 as well as child SR-18 are fully received!
        $this->assertSame('RECEIVED', (string) $sr16->fresh()->status);
        $this->assertSame('RECEIVED', (string) $sr17->fresh()->status);
        $this->assertSame('RECEIVED', (string) $sr18->fresh()->status);
    }

    public function test_consolidated_split_delivery_with_sr16_and_sr17_and_sr18_dynamic_rebinding(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        $category = ItemCategory::query()->first();
        $itemLegal = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'PAPER-LEGAL',
            'item_name' => 'Bond Paper Legal',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);
        Inventory::query()->create([
            'item_id' => $itemLegal->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);

        // SR-16: 200 A4, 200 Legal
        $sr16 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0016-B',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 16 batch',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri16A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr16->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
        ]);
        $sri16Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr16->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => $itemLegal->item_name,
            'quantity_requested' => 200,
        ]);

        // SR-17: 100 A4, 100 Legal
        $sr17 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0017-B',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'GENERAL',
            'purpose' => 'Office 17 batch',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sri17A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr17->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);
        $sri17Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr17->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => $itemLegal->item_name,
            'quantity_requested' => 100,
        ]);

        // Consolidated PR for 300 A4, 300 Legal (total 600)
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0015-B',
            'supply_request_id' => $sr16->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for requests %s, %s', $sr16->request_code, $sr17->request_code),
        ]);
        $priA4 = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 30000,
        ]);
        $priLegal = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => $itemLegal->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 120,
            'estimated_total_cost' => 36000,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0015-B',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr16->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 66000,
        ]);
        $poiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $priA4->purchase_request_item_id,
            'supply_request_item_id' => $sri16A4->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);
        $poiLegal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $priLegal->purchase_request_item_id,
            'supply_request_item_id' => $sri16Legal->supply_request_item_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => $itemLegal->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 120,
            'total_price' => 36000,
        ]);

        // Delivery 1 arrives with ONLY 50 A4 and 50 Legal (deficit 250 A4, 250 Legal = 500 total)
        $del01 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0015-B1',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poiA4->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 250,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poiLegal->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 250,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Follow-up PR (SR-18) created for the 500 missing items
        $followUpPr = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr18 = $followUpPr->supplyRequest;

        // Delivery 1 (50 A4, 50 Legal) released to SR-17
        $invA4 = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $invA4->forceFill(['stock_on_hand' => 50, 'available_stock' => 50])->save();
        $invLegal = Inventory::query()->where('item_id', $itemLegal->item_id)->first();
        $invLegal->forceFill(['stock_on_hand' => 50, 'available_stock' => 50])->save();

        $dist17 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr17->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist17->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist17->distribution_record_id));

        // SR-17 is PARTIALLY_RECEIVED (has 50/100 A4, 50/100 Legal)
        $this->assertNotSame('RECEIVED', (string) $sr17->fresh()->status);

        // Follow-up PO and Delivery 2 for the 500 items (250 A4, 250 Legal)
        $followUpPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0018-B',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr18->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 55000,
        ]);
        $followUpPoiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $sr18->items->firstWhere('item_id', $this->item->item_id)->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 25000,
        ]);
        $followUpPoiLegal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $sr18->items->firstWhere('item_id', $itemLegal->item_id)->supply_request_item_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => $itemLegal->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 120,
            'total_price' => 30000,
        ]);

        $del02 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0018-B2',
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $followUpPoiA4->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 250,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $followUpPoiLegal->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 250,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Direct release of follow-up delivery to parent SR-16 or SR-17 is blocked
        try {
            $distService->createRecordWithAutomaticRelease($this->gsoUser, [
                'supply_request_id' => $sr16->supply_request_id,
                'delivery_id' => $del02->delivery_id,
                'office_id' => $this->office->id,
            ]);
            $this->fail('Direct release of follow-up delivery to parent SR-16 must be blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // Child SR-18 is released (250 A4, 250 Legal)
        $invA4->forceFill(['stock_on_hand' => 250, 'available_stock' => 250])->save();
        $invLegal->forceFill(['stock_on_hand' => 250, 'available_stock' => 250])->save();

        $dist18 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr18->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(500.0, (float) $dist18->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist18->distribution_record_id));

        // Receiving the follow-up child SR-18 fulfills both parent SR-16 and parent SR-17, and completes the follow-up PR!
        $this->assertSame('RECEIVED', (string) $sr16->fresh()->status);
        $this->assertSame('RECEIVED', (string) $sr17->fresh()->status);
        $this->assertSame('RECEIVED', (string) $sr18->fresh()->status);
        $this->assertSame('COMPLETED', (string) $followUpPr->fresh()->status);
    }
}

