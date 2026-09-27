<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
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
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\ProcurementStatusSyncService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpDiscrepancyPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Test 1: PPMP quarterly lines of the same item are preserved in follow-up PR (Fix "Gn Tingob")
     */
    public function test_follow_up_pr_preserves_distinct_ppmp_quarterly_lines_and_does_not_collapse_them(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'General Services Office', 'code' => 'MHO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Office Supply Co', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'code' => 'OFF-SUP',
            'status' => 'ACTIVE',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'BP-BLK-01',
            'item_name' => 'Ballpen Black',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'fiscal_year' => 2026,
            'date_prepared' => now()->toDateString(),
            'status' => 'APPROVED',
        ]);

        $ppmpQ1 = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_category_id' => $category->item_category_id,
            'item_id' => $item->item_id,
            'quantity' => 200,
            'unit_cost' => 100,
            'estimated_budget' => 20000,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
        ]);

        $ppmpQ3 = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_category_id' => $category->item_category_id,
            'item_id' => $item->item_id,
            'quantity' => 200,
            'unit_cost' => 100,
            'estimated_budget' => 20000,
            'procurement_schedule' => 'Q3',
            'fund_source' => 'General Fund',
        ]);

        // Main Supply Request with 2 lines for same item (Q1 & Q3)
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-0001',
            'office_id' => $office->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office Supplies for Q1 and Q3',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $srItem1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpQ1->ppmp_item_id,
            'item_name_snapshot' => 'Ballpen Black (Q1)',
            'quantity_requested' => 200,
        ]);

        $srItem2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpQ3->ppmp_item_id,
            'item_name_snapshot' => 'Ballpen Black (Q3)',
            'quantity_requested' => 200,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'PROCESSING',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-0001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 40000,
            'procurement_method' => 'SHOPPING',
        ]);

        $poItem1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem1->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black (Q1)',
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 20000,
        ]);

        $poItem2 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem2->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black (Q3)',
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 20000,
        ]);

        // Delivery 1 arrives with discrepancies on both lines
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-0001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
            'actual_delivery_date' => now()->toDateString(),
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem1->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 165,
            'damaged_quantity' => 35,
            'missing_quantity' => 0,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem2->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 180,
            'damaged_quantity' => 0,
            'missing_quantity' => 20,
        ]);

        // GSO creates follow-up PR from partial delivery
        $prService = app(PurchaseRequestService::class);
        $followUpPr = $prService->createFromPartialDelivery($delivery, $gsoUser, 'Follow-up for Q1 and Q3 discrepancies');

        // ASSERT: Follow-up PR must have 2 distinct lines (not collapsed into 1 row)
        $followUpPrItems = $followUpPr->items;
        $this->assertCount(2, $followUpPrItems, 'Follow-up PR must not collapse distinct quarterly items into one line');

        $followUpSr = $followUpPr->supplyRequest;
        $this->assertNotNull($followUpSr);
        $this->assertCount(2, $followUpSr->items, 'Follow-up Supply Request must preserve both distinct quarterly lines');

        $ppmpItemIds = $followUpSr->items->pluck('ppmp_item_id')->all();
        $this->assertContains((int) $ppmpQ1->ppmp_item_id, $ppmpItemIds);
        $this->assertContains((int) $ppmpQ3->ppmp_item_id, $ppmpItemIds);

        // Quantities requested in follow-up must match discrepancy amounts
        $q1FollowUpItem = $followUpSr->items->firstWhere('ppmp_item_id', $ppmpQ1->ppmp_item_id);
        $q3FollowUpItem = $followUpSr->items->firstWhere('ppmp_item_id', $ppmpQ3->ppmp_item_id);
        $this->assertEquals(35.0, (float) $q1FollowUpItem->quantity_requested);
        $this->assertEquals(20.0, (float) $q3FollowUpItem->quantity_requested);
    }

    /**
     * Test 2: Sequential FIFO allocation for duplicate item lines (Fix "Requested 200, Released 280")
     */
    public function test_multiple_lines_of_same_item_allocate_released_quantities_sequentially_without_duplication(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'Accounting Office', 'code' => 'ACC', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'code' => 'SUP', 'status' => 'ACTIVE']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'BP-BLK-02',
            'item_name' => 'Ballpen Black',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-0002',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'PARTIALLY_RELEASED',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Two quarters of ballpens',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $srItem1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black Line 1',
            'quantity_requested' => 200,
        ]);

        $srItem2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black Line 2',
            'quantity_requested' => 200,
        ]);

        // Total requested is 400. We release 280 in a distribution record.
        $dr = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-0002',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'status' => 'RELEASED',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $dr->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Ballpen Black',
            'quantity_released' => 280,
            'unit' => 'box',
        ]);

        // Act: Fetch supply request via office controller show
        $response = $this->actingAs($officeUser)->get(route('office.supply-requests.show', $sr->supply_request_id));
        $response->assertOk();

        $pageProps = $response->original->getData()['page']['props'];
        $mappedItems = $pageProps['supplyRequest']['items'];

        $this->assertCount(2, $mappedItems);
        // Line 1 should consume up to its requested 200
        $this->assertEquals(200.0, (float) $mappedItems[0]['quantity_requested']);
        $this->assertEquals(200.0, (float) $mappedItems[0]['quantity_released']);

        // Line 2 should consume the remaining 80 (NOT 280!)
        $this->assertEquals(200.0, (float) $mappedItems[1]['quantity_requested']);
        $this->assertEquals(80.0, (float) $mappedItems[1]['quantity_released']);
    }

    /**
     * Test 3: Multi-tier follow-up chain marks all ancestor requests RECEIVED when completed (Fix stuck PARTIALLY_RECEIVED)
     */
    public function test_multi_tier_follow_up_chain_marks_all_ancestor_requests_received_when_completed(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'Treasury', 'code' => 'TRZ', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Paper Supplier', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->create(['category_name' => 'Paper', 'code' => 'PPR', 'status' => 'ACTIVE']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'A4-BOND',
            'item_name' => 'A4 Bond Paper',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $prService = app(PurchaseRequestService::class);
        $syncService = app(ProcurementStatusSyncService::class);

        // Tier 1: Original Supply Request (46 boxes)
        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-TRZ-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'A4 Paper',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_requested' => 46,
        ]);

        $pr1 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-TRZ-0001',
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'PROCESSING',
        ]);

        $po1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-TRZ-0001',
            'purchase_request_id' => $pr1->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 4600,
            'procurement_method' => 'SHOPPING',
        ]);
        $poi1 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po1->purchase_order_id,
            'supply_request_item_id' => $sr1->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_ordered' => 46,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 4600,
        ]);

        // Delivery 1: 36 received, 5 damaged, 5 missing (total 10 discrepancy)
        $del1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-TRZ-0001',
            'purchase_order_id' => $po1->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poi1->purchase_order_item_id,
            'expected_quantity' => 46,
            'received_quantity' => 36,
            'damaged_quantity' => 5,
            'missing_quantity' => 5,
        ]);

        // Distribution 1 for SR-1 (36 good boxes received by office)
        $dr1 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-TRZ-0001',
            'supply_request_id' => $sr1->supply_request_id,
            'delivery_id' => $del1->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr1->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_released' => 36,
            'quantity_received' => 36,
            'unit' => 'box',
        ]);

        // Tier 2: Follow-up PR 1 -> SR-2 (10 boxes)
        $pr2 = $prService->createFromPartialDelivery($del1, $gsoUser, 'Discrepancy 1');
        $sr2 = $pr2->supplyRequest;
        $this->assertEquals(10.0, (float) $sr2->items->sum('quantity_requested'));

        $po2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-TRZ-0002',
            'purchase_request_id' => $pr2->purchase_request_id,
            'supply_request_id' => $sr2->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 1000,
            'procurement_method' => 'DIRECT_CONTRACTING',
        ]);
        $poi2 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po2->purchase_order_id,
            'supply_request_item_id' => $sr2->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 1000,
        ]);

        // Delivery 2: 9 received, 1 damaged
        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-TRZ-0002',
            'purchase_order_id' => $po2->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $poi2->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 9,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
        ]);

        // Distribution 2 for SR-2 (9 boxes received)
        $dr2 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-TRZ-0002',
            'supply_request_id' => $sr2->supply_request_id,
            'delivery_id' => $del2->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr2->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_released' => 9,
            'quantity_received' => 9,
            'unit' => 'box',
        ]);

        // Tier 3: Follow-up PR 2 -> SR-3 (1 box)
        $pr3 = $prService->createFromPartialDelivery($del2, $gsoUser, 'Discrepancy 2');
        $sr3 = $pr3->supplyRequest;
        $this->assertEquals(1.0, (float) $sr3->items->sum('quantity_requested'));

        $po3 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-TRZ-0003',
            'purchase_request_id' => $pr3->purchase_request_id,
            'supply_request_id' => $sr3->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'PLACED',
            'total_amount' => 100,
            'procurement_method' => 'DIRECT_CONTRACTING',
        ]);
        $poi3 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po3->purchase_order_id,
            'supply_request_item_id' => $sr3->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_ordered' => 1,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        // Delivery 3: 1 received, 0 damaged, 0 missing
        $del3 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-TRZ-0003',
            'purchase_order_id' => $po3->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'DELIVERED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del3->delivery_id,
            'purchase_order_item_id' => $poi3->purchase_order_item_id,
            'expected_quantity' => 1,
            'received_quantity' => 1,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ]);

        // Distribution 3 for SR-3 (1 box received)
        $dr3 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-TRZ-0003',
            'supply_request_id' => $sr3->supply_request_id,
            'delivery_id' => $del3->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr3->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_released' => 1,
            'quantity_received' => 1,
            'unit' => 'box',
        ]);

        // Trigger synchronization when SR-3 is received
        $syncService->syncCompletedStatusesForReceivedSupplyRequest($sr3, $officeUser);

        // ASSERT: All three requests in the chain must transition to RECEIVED!
        $this->assertEquals('RECEIVED', (string) $sr3->fresh()->status, 'Leaf child SR-3 must be RECEIVED');
        $this->assertEquals('RECEIVED', (string) $sr2->fresh()->status, 'Intermediate SR-2 must be RECEIVED, not stuck in PARTIALLY_RECEIVED');
        $this->assertEquals('RECEIVED', (string) $sr1->fresh()->status, 'Root ancestor SR-1 must be RECEIVED');

        // Also assert that office tracking index resolves all as RECEIVED
        $trackingIndex = $this->actingAs($officeUser)->get(route('office.supply-requests.index'));
        $trackingIndex->assertOk();
        $trackingRows = collect($trackingIndex->original->getData()['page']['props']['supplyRequests']['data']);

        $sr1Row = $trackingRows->firstWhere('supply_request_id', $sr1->supply_request_id);
        $sr2Row = $trackingRows->firstWhere('supply_request_id', $sr2->supply_request_id);
        $sr3Row = $trackingRows->firstWhere('supply_request_id', $sr3->supply_request_id);

        $this->assertEquals('RECEIVED', $sr1Row['status']);
        $this->assertEquals('RECEIVED', $sr2Row['status']);
        $this->assertEquals('RECEIVED', $sr3Row['status']);
    }

    /**
     * Test 4: Warehouse stock partial release when shortages exist
     */
    public function test_warehouse_stock_can_be_released_when_request_has_shortages(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'Mayor Office', 'code' => 'MO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);

        $category = ItemCategory::query()->create(['category_name' => 'Paper', 'code' => 'PPR2', 'status' => 'ACTIVE']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'A4-BOND-2',
            'item_name' => 'A4 Bond Paper',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        // Warehouse has 4 boxes on hand
        Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 4,
            'available_stock' => 4,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 10,
        ]);

        // Office submits request for 50 boxes (shortage = 46)
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-MO-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'A4 Bond Paper for printing forms',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'A4 Bond Paper',
            'quantity_requested' => 50,
        ]);

        // GSO processes distribution from available inventory
        $distService = app(DistributionRecordService::class);
        $record = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'supply_request_id' => (int) $sr->supply_request_id,
            'office_id' => (int) $sr->office_id,
        ]);

        // ASSERT: Distribution record is created and automatically released the 4 warehouse boxes
        $this->assertNotNull($record);
        $recordItems = $record->items;
        $this->assertCount(1, $recordItems);
        $this->assertEquals(4.0, (float) $recordItems->first()->quantity_released, 'Must release exactly the 4 available warehouse boxes');

        // Cannot release more than available warehouse stock (4)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $distService->addDistributionItem($record, $gsoUser, [
            'item_id' => $item->item_id,
            'quantity_released' => 10,
        ]);
    }
}
