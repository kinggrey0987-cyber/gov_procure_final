<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
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
use App\Services\Inventory\SupplyRequestFollowUpService;
use App\Services\Procurement\ProcurementStatusSyncService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedFollowUpDeficitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_consolidated_delivery_discrepancy_links_follow_up_to_actual_deficit_requisition_and_prevents_premature_completion(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Government Paper Depot', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'code' => 'OFF-SUP', 'status' => 'ACTIVE']);

        $itemA4 = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'A4-BOND',
            'item_name' => 'Bond Paper, A4 (80 GSM)',
            'default_unit' => 'ream',
            'status' => 'ACTIVE',
        ]);

        $itemLegal = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'LEGAL-BOND',
            'item_name' => 'Bond Paper, Legal (80 GSM)',
            'default_unit' => 'ream',
            'status' => 'ACTIVE',
        ]);

        $prService = app(PurchaseRequestService::class);
        $syncService = app(ProcurementStatusSyncService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        // SR-6: 200 A4, 200 Legal
        $sr6 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0006',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office paper batch 1',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sr6A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr6->supply_request_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 200,
        ]);
        $sr6Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr6->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 200,
        ]);

        // SR-7: 100 A4, 100 Legal
        $sr7 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office paper batch 2',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sr7A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr7->supply_request_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 100,
        ]);
        $sr7Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr7->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 100,
        ]);

        // Consolidated PR-6 consolidating SR-6 and SR-7
        $pr6 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0006',
            'supply_request_id' => $sr6->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated PR combining %s and %s.', $sr6->request_code, $sr7->request_code),
        ]);
        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr6->purchase_request_id,
            'item_id' => $itemA4->item_id,
            'quantity_requested' => 300,
        ]);
        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr6->purchase_request_id,
            'item_id' => $itemLegal->item_id,
            'quantity_requested' => 300,
        ]);

        // PO for PR-6
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0004',
            'purchase_request_id' => $pr6->purchase_request_id,
            'supply_request_id' => $sr6->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 60000,
            'procurement_method' => 'PUBLIC_BIDDING',
        ]);
        $poiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr6A4->supply_request_item_id,
            'source_request_item_ids' => [$sr6A4->supply_request_item_id, $sr7A4->supply_request_item_id],
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 300,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);
        $poiLegal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr6Legal->supply_request_item_id,
            'source_request_item_ids' => [$sr6Legal->supply_request_item_id, $sr7Legal->supply_request_item_id],
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_ordered' => 300,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);

        // Delivery 1: DEL-2026-09-0004 with 200 accepted for both, 100 missing for both
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0004',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poiA4->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poiLegal->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
        ]);

        // GSO creates follow-up PR from partial delivery
        $followUpPr = $prService->createFromPartialDelivery($delivery, $gsoUser, 'Discrepancy for remaining items');
        $sr8 = $followUpPr->supplyRequest;

        // VERIFY: Follow-up PR must be linked to SR-7 (the deficit request), NOT SR-6!
        $this->assertEquals(
            (int) $sr7->supply_request_id,
            (int) $followUpPr->source_supply_request_id,
            'Follow-up PR must link to the candidate request with the active deficit (SR-7), not the satisfied request (SR-6).'
        );

        // VERIFY: SR-8 context must show parent is SR-7
        $sr8Context = $followUpService->resolveContext($sr8);
        $this->assertTrue($sr8Context['is_follow_up']);
        $this->assertEquals((int) $sr7->supply_request_id, (int) $sr8Context['parent']['supply_request_id']);

        // VERIFY: SR-7 context must show it has SR-8 as a child
        $sr7Context = $followUpService->resolveContext($sr7);
        $this->assertFalse($sr7Context['is_follow_up']);
        $this->assertEquals('PARENT_REQUISITION', $sr7Context['type']);
        $this->assertCount(1, $sr7Context['children']);
        $this->assertEquals((int) $sr8->supply_request_id, (int) $sr7Context['children'][0]['supply_request_id']);

        // VERIFY: SR-6 context must NOT have children (its items arrived)
        $sr6Context = $followUpService->resolveContext($sr6);
        $this->assertEquals('STANDARD', $sr6Context['type']);
        $this->assertEmpty($sr6Context['children']);

        // Now, GSO releases the 200 A4 and 200 Legal to SR-6
        $dr6 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-09-0006',
            'supply_request_id' => $sr6->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr6->distribution_record_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'ream',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr6->distribution_record_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'ream',
        ]);

        // Trigger sync when SR-6 is received by the office
        $syncService->syncCompletedStatusesForReceivedSupplyRequest($sr6, $officeUser);

        // ASSERTIONS after SR-6 is fulfilled:
        // 1. SR-6 should be RECEIVED
        $this->assertEquals('RECEIVED', (string) $sr6->fresh()->status, 'SR-6 should be marked RECEIVED after office receipt.');

        // 2. SR-8 MUST NOT be marked RECEIVED! (It has received 0 items and no delivery has arrived)
        $this->assertEquals('SUBMITTED', (string) $sr8->fresh()->status, 'SR-8 must NOT be marked RECEIVED when parent distribution for SR-6 is received.');

        // 3. Follow-up PR (PR-7) MUST NOT be marked COMPLETED!
        $this->assertEquals('OPEN', (string) $followUpPr->fresh()->status, 'Follow-up PR must remain OPEN until replacement delivery arrives.');

        // 4. Consolidated PR (PR-6) MUST NOT be marked COMPLETED! (SR-7 is still unfulfilled, PO has 200 missing)
        $this->assertNotEquals('COMPLETED', (string) $pr6->fresh()->status, 'Consolidated PR must not be COMPLETED while SR-7 is still pending.');

        // 5. SR-7 MUST NOT be stuck; it is waiting for follow-up PR
        $this->assertNotEquals('RECEIVED', (string) $sr7->fresh()->status, 'SR-7 must not be RECEIVED until follow-up batch arrives.');

        // Now, complete the replacement procurement flow:
        // PO created for follow-up PR
        $po2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0005',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr8->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 20000,
            'procurement_method' => 'DIRECT_CONTRACTING',
        ]);
        $poi2A4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $sr8->items->firstWhere('item_id', $itemA4->item_id)?->supply_request_item_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 100,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 10000,
        ]);
        $poi2Legal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $sr8->items->firstWhere('item_id', $itemLegal->item_id)?->supply_request_item_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_ordered' => 100,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 10000,
        ]);

        // Replacement delivery: DEL-2026-09-0005 fully delivers 100 A4, 100 Legal
        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0005',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $poi2A4->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $poi2Legal->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ]);

        // Distribute to SR-8
        $dr8 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-09-0008',
            'supply_request_id' => $sr8->supply_request_id,
            'delivery_id' => $del2->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr8->distribution_record_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 100,
            'quantity_received' => 100,
            'unit' => 'ream',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr8->distribution_record_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_released' => 100,
            'quantity_received' => 100,
            'unit' => 'ream',
        ]);

        // Office receives SR-8 distribution
        $syncService->syncCompletedStatusesForReceivedSupplyRequest($sr8, $officeUser);

        // FINAL ASSERTIONS:
        // SR-8 is RECEIVED
        $this->assertEquals('RECEIVED', (string) $sr8->fresh()->status);
        // Parent SR-7 is now fully fulfilled via SR-8 -> transitions to RECEIVED!
        $this->assertEquals('RECEIVED', (string) $sr7->fresh()->status, 'SR-7 must transition to RECEIVED when follow-up SR-8 is fulfilled.');
        // Follow-up PR is now COMPLETED
        $this->assertEquals('COMPLETED', (string) $followUpPr->fresh()->status);
        // Follow-up PO is now COMPLETED
        $this->assertEquals('COMPLETED', (string) $po2->fresh()->status);
    }
}
