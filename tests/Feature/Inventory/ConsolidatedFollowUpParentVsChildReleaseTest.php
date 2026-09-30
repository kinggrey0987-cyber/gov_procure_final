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
use App\Services\Inventory\DistributionRecordService;
use App\Services\Inventory\SupplyRequestFollowUpService;
use App\Services\Procurement\ProcurementStatusSyncService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedFollowUpParentVsChildReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $gsoUser;
    protected User $procUser;
    protected User $officeUser;
    protected Office $office;
    protected Supplier $supplier;
    protected Item $item;
    protected Item $itemLegal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $this->office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $this->gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $this->office->id, 'name' => 'GSO Staff']);
        $this->procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $this->office->id, 'name' => 'Procurement Staff']);
        $this->officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $this->office->id, 'name' => 'Office Requester']);

        $this->supplier = Supplier::query()->create(['supplier_name' => 'Gov Supplies Co', 'status' => 'ACTIVE']);
        $category = ItemCategory::query()->create(['category_name' => 'Stationery', 'code' => 'STAT', 'status' => 'ACTIVE']);
        $this->item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'PAPER-A4',
            'item_name' => 'Bond Paper A4',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        $this->itemLegal = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'PAPER-LEGAL',
            'item_name' => 'Bond Paper Legal',
            'default_unit' => 'box',
            'status' => 'ACTIVE',
        ]);

        Inventory::query()->create([
            'item_id' => $this->item->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);

        Inventory::query()->create([
            'item_id' => $this->itemLegal->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_reproducing_releasing_follow_up_delivery_to_parent_instead_of_child(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);
        $syncService = app(ProcurementStatusSyncService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        // PPMP for Office
        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $this->officeUser->id,
            'date_prepared' => now()->toDateString(),
        ]);
        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $this->item->item_id,
            'item_category_id' => $this->item->item_category_id,
            'quantity' => 500,
            'unit_cost' => 100,
            'estimated_budget' => 50000,
            'procurement_schedule' => 'Q1',
        ]);

        // 1. Two Supply Requests: SR-6 (100 boxes) and SR-7 (100 boxes)
        $sr6 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0006',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office use batch 1',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sri6 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr6->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        $sr7 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office use batch 2',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sri7 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr7->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        // 2. Consolidated PR & PO for 200 boxes
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0006',
            'supply_request_id' => $sr6->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for requests %s, %s', $sr6->request_code, $sr7->request_code),
        ]);
        $pri = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
            'unit' => 'box',
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 20000,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0006',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr6->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 20000,
        ]);
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $pri->purchase_request_item_id,
            'supply_request_item_id' => $sri6->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 20000,
        ]);

        // 3. First Delivery (DEL-1) arrives with DISCREPANCY: 50 received, 150 missing
        $del01 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del01->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 150,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        // GSO partially releases the 50 received units to SR-6
        $inv = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 50, 'available_stock' => 50])->save();

        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr6->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(50.0, (float) $dist1->items->sum('quantity_released'));

        // Office receives dist1
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id));
        $this->assertNotSame('RECEIVED', (string) $sr6->status);

        // 4. Follow-up PR created from DEL-1 for 150 missing items -> Creates child SR-8
        $followUpPr = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr8 = $followUpPr->supplyRequest;
        $this->assertStringStartsWith('SR-MPDO-', (string) $sr8->request_code);
        $this->assertEquals(150.0, (float) $sr8->items->sum('quantity_requested'));

        // 5. Follow-up PO placed for SR-8 and Delivery 2 (DEL-2) arrives with 150 units
        $followUpPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0008',
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr8->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $followUpPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supply_request_item_id' => $sr8->items->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 150,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        $del02 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
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
            'expected_quantity' => 150,
            'received_quantity' => 150,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $inv->forceFill(['stock_on_hand' => 150, 'available_stock' => 150])->save();

        // 6. Verified follow-up delivery DEL-02 arrives for child SR-8 (150 units).
        // Attempting to release DEL-02 to parent SR-6 MUST be blocked:
        try {
            $distService->createRecordWithAutomaticRelease($this->gsoUser, [
                'supply_request_id' => $sr6->supply_request_id,
                'delivery_id' => $del02->delivery_id,
                'office_id' => $this->office->id,
            ]);
            $this->fail('Expected ValidationException when releasing follow-up delivery to parent SR-6.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // Releasing DEL-02 to child SR-8 succeeds:
        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr8->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(150.0, (float) $dist2->items->sum('quantity_released'));

        // Office receives dist2:
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        $sr6->refresh();
        $sr7->refresh();
        $sr8->refresh();

        // All requests across the consolidated family are now fully received!
        $this->assertSame('RECEIVED', (string) $sr6->status);
        $this->assertSame('RECEIVED', (string) $sr7->status);
        $this->assertSame('RECEIVED', (string) $sr8->status);

        $responseSr6 = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $pageProps = $responseSr6->viewData('page')['props'];
        $requestsOnPage = collect($pageProps['supplyRequests']['data'] ?? $pageProps['supply_requests']['data'] ?? []);
        $uiSr6 = $requestsOnPage->firstWhere('request_code', 'SR-MPDO-2026-09-0006');
        $uiSr7 = $requestsOnPage->firstWhere('request_code', 'SR-MPDO-2026-09-0007');
        $uiSr8 = $requestsOnPage->firstWhere('request_code', $sr8->request_code);

        $this->assertSame('RECEIVED', $uiSr6['status'] ?? null);
        $this->assertSame('RECEIVED', $uiSr7['status'] ?? null);
        $this->assertSame('RECEIVED', $uiSr8['status'] ?? null);
    }

    public function test_reproducing_exact_user_scenario_sr13_and_sr14(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);
        $syncService = app(ProcurementStatusSyncService::class);
        $followUpService = app(SupplyRequestFollowUpService::class);

        // PPMP for Office
        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $this->officeUser->id,
            'date_prepared' => now()->toDateString(),
        ]);
        $ppmpA4 = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $this->item->item_id,
            'item_category_id' => $this->item->item_category_id,
            'quantity' => 1000,
            'unit_cost' => 100,
            'estimated_budget' => 100000,
            'procurement_schedule' => 'Q1',
        ]);
        $ppmpLegal = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $this->itemLegal->item_id,
            'item_category_id' => $this->itemLegal->item_category_id,
            'quantity' => 1000,
            'unit_cost' => 120,
            'estimated_budget' => 120000,
            'procurement_schedule' => 'Q1',
        ]);

        // 1. Two Supply Requests: SR-13 (200 A4, 200 Legal) and SR-14 (100 A4, 100 Legal)
        $sr13 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0013',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office supplies batch 13',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sr13ItemA4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr13->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $ppmpA4->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
        ]);
        $sr13ItemLegal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr13->supply_request_id,
            'item_id' => $this->itemLegal->item_id,
            'ppmp_item_id' => $ppmpLegal->ppmp_item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 200,
        ]);

        $sr14 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0014',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office supplies batch 14',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sr14ItemA4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr14->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $ppmpA4->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);
        $sr14ItemLegal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr14->supply_request_id,
            'item_id' => $this->itemLegal->item_id,
            'ppmp_item_id' => $ppmpLegal->ppmp_item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 100,
        ]);

        // 2. Consolidated PR & PO for 300 A4 + 300 Legal
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0013',
            'supply_request_id' => $sr13->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for %s, %s', $sr13->request_code, $sr14->request_code),
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
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 120,
            'estimated_total_cost' => 36000,
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0013',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr13->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 66000,
        ]);
        $poiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $priA4->purchase_request_item_id,
            'supply_request_item_id' => $sr13ItemA4->supply_request_item_id,
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
            'supply_request_item_id' => $sr13ItemLegal->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 120,
            'total_price' => 36000,
        ]);

        // 3. First Delivery (DEL-1): 50 A4, 50 Legal received. (Missing: 250 A4, 250 Legal)
        $del01 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
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

        // GSO partially releases 50/50 to SR-14
        $invA4 = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $invA4->forceFill(['stock_on_hand' => 50, 'available_stock' => 50])->save();
        $invLegal = Inventory::query()->where('item_id', $this->itemLegal->item_id)->first();
        $invLegal->forceFill(['stock_on_hand' => 50, 'available_stock' => 50])->save();

        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr14->supply_request_id,
            'delivery_id' => $del01->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(100.0, (float) $dist1->items->sum('quantity_released'));

        // Office receives dist1 -> SR-14 partially received
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id));
        $sr14->refresh();
        $this->assertNotSame('RECEIVED', (string) $sr14->status);

        // 4. Follow-up PR 1 created from DEL-01 (missing 250/250) -> Child SR-15
        $followUpPr1 = $prService->createFromPartialDelivery($del01, $this->gsoUser);
        $sr15 = $followUpPr1->supplyRequest;
        $this->assertNotNull($sr15);
        echo "\n[SR13/14 SCENARIO] Follow-up PR 1 created child SR: {$sr15->request_code} (linked to parent {$sr15->purchaseRequests->first()->source_supply_request_id})\n";

        // Place PO for SR-15
        $followUpPo1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0015',
            'purchase_request_id' => $followUpPr1->purchase_request_id,
            'supply_request_id' => $sr15->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $followUpPoi1A4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supply_request_item_id' => $sr15->items->where('item_id', $this->item->item_id)->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);
        $followUpPoi1Legal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supply_request_item_id' => $sr15->items->where('item_id', $this->itemLegal->item_id)->first()->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        // 5. Delivery 2 arrives: 150 A4, 150 Legal received. (Missing: 100 A4, 100 Legal)
        $del02 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $followUpPoi1A4->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 150,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del02->delivery_id,
            'purchase_order_item_id' => $followUpPoi1Legal->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 150,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        $invA4->forceFill(['stock_on_hand' => 150, 'available_stock' => 150])->save();
        $invLegal->forceFill(['stock_on_hand' => 150, 'available_stock' => 150])->save();

        // When DEL-02 arrives, attempting to release to parent SR-13 must be BLOCKED:
        try {
            $distService->createRecordWithAutomaticRelease($this->gsoUser, [
                'supply_request_id' => $sr13->supply_request_id,
                'delivery_id' => $del02->delivery_id,
                'office_id' => $this->office->id,
            ]);
            $this->fail('Releasing DEL-02 to parent SR-13 should have been blocked with ValidationException.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // GSO releases DEL-02 to child SR-15:
        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr15->supply_request_id,
            'delivery_id' => $del02->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(300.0, (float) $dist2->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        // 6. Follow-up PR 2 created from DEL-02 (missing 100/100) -> Child SR-16
        $followUpPr2 = $prService->createFromPartialDelivery($del02, $this->gsoUser);
        $sr16 = $followUpPr2->supplyRequest;
        $this->assertNotNull($sr16);

        // Place PO for SR-16
        $followUpPo2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0016',
            'purchase_request_id' => $followUpPr2->purchase_request_id,
            'supply_request_id' => $sr16->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $followUpPoi2A4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supply_request_item_id' => $sr16->items->where('item_id', $this->item->item_id)->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 100,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);
        $followUpPoi2Legal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supply_request_item_id' => $sr16->items->where('item_id', $this->itemLegal->item_id)->first()->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 100,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        // 7. Delivery 3 arrives: 100 A4, 100 Legal received (all arrived, 0 missing)
        $del03 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0003',
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del03->delivery_id,
            'purchase_order_item_id' => $followUpPoi2A4->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del03->delivery_id,
            'purchase_order_item_id' => $followUpPoi2Legal->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $invA4->forceFill(['stock_on_hand' => 100, 'available_stock' => 100])->save();
        $invLegal->forceFill(['stock_on_hand' => 100, 'available_stock' => 100])->save();

        // Attempting to release DEL-03 to parent SR-15 or SR-13 must be BLOCKED:
        try {
            $distService->createRecordWithAutomaticRelease($this->gsoUser, [
                'supply_request_id' => $sr15->supply_request_id,
                'delivery_id' => $del03->delivery_id,
                'office_id' => $this->office->id,
            ]);
            $this->fail('Releasing DEL-03 to SR-15 should have been blocked.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // GSO releases DEL-03 to SR-16:
        $dist3 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr16->supply_request_id,
            'delivery_id' => $del03->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(200.0, (float) $dist3->items->sum('quantity_released'));
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist3->distribution_record_id));

        // NOW LET'S CHECK THE STATUSES OF ALL 4 REQUESTS!
        $sr13->refresh();
        $sr14->refresh();
        $sr15->refresh();
        $sr16->refresh();

        $this->assertSame('RECEIVED', (string) $sr13->status);
        $this->assertSame('RECEIVED', (string) $sr14->status);
        $this->assertSame('RECEIVED', (string) $sr15->status);
        $this->assertSame('RECEIVED', (string) $sr16->status);

        $responseSr = $this->actingAs($this->officeUser)->get(route('office.supply-requests.index'));
        $pageProps = $responseSr->viewData('page')['props'];
        $requestsOnPage = collect($pageProps['supplyRequests']['data'] ?? $pageProps['supply_requests']['data'] ?? []);
        $ui13 = $requestsOnPage->firstWhere('request_code', $sr13->request_code);
        $ui14 = $requestsOnPage->firstWhere('request_code', $sr14->request_code);
        $ui15 = $requestsOnPage->firstWhere('request_code', $sr15->request_code);
        $ui16 = $requestsOnPage->firstWhere('request_code', $sr16->request_code);

        $this->assertSame('RECEIVED', $ui13['status'] ?? null);
        $this->assertSame('RECEIVED', $ui14['status'] ?? null);
        $this->assertSame('RECEIVED', $ui15['status'] ?? null);
        $this->assertSame('RECEIVED', $ui16['status'] ?? null);

        // Verify Show page for SR-14 contains related distribution records that allocated stock to it (Delivery 1 and Delivery 2 via SR-15):
        $showResponse = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr14->supply_request_id));
        $relatedDistributions = $showResponse->viewData('page')['props']['distributionRecords'];
        $this->assertNotEmpty($relatedDistributions);
        $this->assertCount(2, $relatedDistributions);
        $this->assertNotNull($relatedDistributions[0]['items'] ?? null);

        // Verify Show page for SR-13 contains the distribution records that allocated stock to it (Delivery 2 spillover of 100/100 and Delivery 3 of 100/100):
        $showResponse13 = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr13->supply_request_id));
        $relatedDistributions13 = $showResponse13->viewData('page')['props']['distributionRecords'];
        $this->assertNotEmpty($relatedDistributions13);
        $this->assertCount(2, $relatedDistributions13);
    }

    public function test_claims_with_replacement_prs_releasing_to_parent_vs_child(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);

        // PPMP for Office
        $ppmp = Ppmp::query()->create([
            'office_id' => $this->office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $this->officeUser->id,
            'date_prepared' => now()->toDateString(),
        ]);
        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $this->item->item_id,
            'item_category_id' => $this->item->item_category_id,
            'quantity' => 500,
            'unit_cost' => 100,
            'estimated_budget' => 50000,
            'procurement_schedule' => 'Q1',
        ]);

        // 1. Supply Request for 100 items
        $srParent = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0020',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Claim parent request test',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sri = SupplyRequestItem::query()->create([
            'supply_request_id' => $srParent->supply_request_id,
            'item_id' => $this->item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);

        // PR and PO
        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0020',
            'supply_request_id' => $srParent->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
        ]);
        $pri = PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr->purchase_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
            'unit' => 'box',
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 10000,
        ]);
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0020',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $srParent->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 10000,
        ]);
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'purchase_request_item_id' => $pri->purchase_request_item_id,
            'supply_request_item_id' => $sri->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 100,
            'unit' => 'box',
            'unit_price' => 100,
            'total_price' => 10000,
        ]);

        // Delivery 1: 100 delivered
        $del1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0020',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 100,
            'received_quantity' => 100,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $inv = Inventory::query()->where('item_id', $this->item->item_id)->first();
        $inv->forceFill(['stock_on_hand' => 100, 'available_stock' => 100])->save();

        // Release 100 to Office
        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $srParent->supply_request_id,
            'delivery_id' => $del1->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $distItem1 = $dist1->items->first();

        // Office files Claim: 20 damaged
        $claim = \App\Models\Claim::query()->create([
            'claim_code' => 'CLM-2026-09-0001',
            'distribution_record_id' => $dist1->distribution_record_id,
            'distribution_item_id' => $distItem1->distribution_item_id,
            'office_id' => $this->office->id,
            'claimed_by' => $this->officeUser->id,
            'claim_date' => now()->toDateString(),
            'claim_location' => 'MPDO Storage',
            'claim_type' => 'DAMAGED_ITEM',
            'quantity_disputed' => 20,
            'status' => 'PENDING_REPLACEMENT',
            'resolution_type' => 'PENDING_REPLACEMENT',
        ]);

        // GSO creates replacement PR -> child SR
        $claimPr = $prService->createFromPendingClaimReplacement($claim, $this->gsoUser);
        $childReplacementSr = $claimPr->supplyRequest;
        $this->assertNotNull($childReplacementSr);

        // Place replacement PO and Delivery 2 arrives: 20 received
        $replacementPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0021',
            'purchase_request_id' => $claimPr->purchase_request_id,
            'supply_request_id' => $childReplacementSr->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $replacementPoi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $replacementPo->purchase_order_id,
            'supply_request_item_id' => $childReplacementSr->items->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 20,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0021',
            'purchase_order_id' => $replacementPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $replacementPoi->purchase_order_item_id,
            'expected_quantity' => 20,
            'received_quantity' => 20,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        $inv->forceFill(['stock_on_hand' => 20, 'available_stock' => 20])->save();

        // Attempting to release replacement delivery DEL-2 to PARENT must be BLOCKED:
        try {
            $distService->createRecordWithAutomaticRelease($this->gsoUser, [
                'supply_request_id' => $srParent->supply_request_id,
                'delivery_id' => $del2->delivery_id,
                'office_id' => $this->office->id,
            ]);
            $this->fail('Releasing claim replacement delivery to parent must be blocked with ValidationException.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('cannot be released directly against parent requisition', $e->getMessage());
        }

        // Releasing to child replacement SR succeeds:
        $distRepl = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $childReplacementSr->supply_request_id,
            'delivery_id' => $del2->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->assertEquals(20.0, (float) $distRepl->items->sum('quantity_released'));

        // Office receives replacement distribution:
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $distRepl->distribution_record_id));

        $claim->refresh();
        $childReplacementSr->refresh();
        $this->assertSame('RESOLVED', (string) $claim->status);
        $this->assertEquals(20.0, (float) $claim->replacement_quantity);
        $this->assertSame('RECEIVED', (string) $childReplacementSr->status);
    }

    public function test_waterfall_allocation_multi_sr_multi_followup_scenario(): void
    {
        $distService = app(DistributionRecordService::class);
        $prService = app(PurchaseRequestService::class);

        // SR-3: 200 A4, 200 Legal
        $sr3 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0003',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'SUPPLIES',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Consolidated SR-3',
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr3->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 200,
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr3->supply_request_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 200,
        ]);

        // SR-4: 100 A4, 100 Legal
        $sr4 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0004',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'SUPPLIES',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'purpose' => 'Consolidated SR-4',
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr4->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 100,
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr4->supply_request_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 100,
        ]);

        // Consolidated PR & PO placed for 300 A4, 300 Legal
        $consolidatedPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0003',
            'supply_request_id' => $sr3->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for %s, %s', $sr3->request_code, $sr4->request_code),
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
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_requested' => 300,
            'unit' => 'box',
            'estimated_unit_cost' => 120,
            'estimated_total_cost' => 36000,
        ]);

        $rootPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0003',
            'purchase_request_id' => $consolidatedPr->purchase_request_id,
            'supply_request_id' => $sr3->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 0,
            'remarks' => 'Consolidated PO for SR-MPDO-2026-09-0003 and SR-MPDO-2026-09-0004',
        ]);
        $poiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $rootPo->purchase_order_id,
            'supply_request_item_id' => $sr3->items->where('item_id', $this->item->item_id)->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);
        $poiLegal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $rootPo->purchase_order_id,
            'supply_request_item_id' => $sr3->items->where('item_id', $this->itemLegal->item_id)->first()->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 300,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        // 1. Delivery 1 arrives: 50 A4, 50 Legal received. (Missing: 250 A4, 250 Legal)
        $del1 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $rootPo->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poiA4->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 50,
            'missing_quantity' => 250,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del1->delivery_id,
            'purchase_order_item_id' => $poiLegal->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 50,
            'missing_quantity' => 250,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        Inventory::query()->where('item_id', $this->item->item_id)->update(['stock_on_hand' => 50, 'available_stock' => 50]);
        Inventory::query()->where('item_id', $this->itemLegal->item_id)->update(['stock_on_hand' => 50, 'available_stock' => 50]);

        // Release 50/50 to SR-4:
        $dist1 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr4->supply_request_id,
            'delivery_id' => $del1->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist1->distribution_record_id));

        // Follow-up PR 1 -> SR-5 (deficit: 250/250)
        $followUpPr1 = $prService->createFromPartialDelivery($del1, $this->gsoUser);
        $sr5 = $followUpPr1->supplyRequest;
        $this->assertNotNull($sr5);

        // Place PO for SR-5
        $followUpPo1 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0005',
            'purchase_request_id' => $followUpPr1->purchase_request_id,
            'supply_request_id' => $sr5->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $fuPoi1A4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supply_request_item_id' => $sr5->items->where('item_id', $this->item->item_id)->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);
        $fuPoi1Legal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supply_request_item_id' => $sr5->items->where('item_id', $this->itemLegal->item_id)->first()->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 250,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        // 2. Delivery 2 arrives: only 50 A4, 50 Legal received on SR-5 (Missing: 200 A4, 200 Legal)
        $del2 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $followUpPo1->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $fuPoi1A4->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 50,
            'missing_quantity' => 200,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del2->delivery_id,
            'purchase_order_item_id' => $fuPoi1Legal->purchase_order_item_id,
            'expected_quantity' => 250,
            'received_quantity' => 50,
            'missing_quantity' => 200,
            'item_status' => 'PARTIALLY_RECEIVED',
        ]);

        Inventory::query()->where('item_id', $this->item->item_id)->update(['stock_on_hand' => 50, 'available_stock' => 50]);
        Inventory::query()->where('item_id', $this->itemLegal->item_id)->update(['stock_on_hand' => 50, 'available_stock' => 50]);

        // Release to child SR-5:
        $dist2 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr5->supply_request_id,
            'delivery_id' => $del2->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist2->distribution_record_id));

        // CHECK MIDWAY STATE:
        // SR-4 already had 50/100, needed 50 more -> Del 2 allocated 50 to SR-4!
        // SR-4 is now 100/100 -> RECEIVED!
        // SR-3 still has 0/200 received -> NOT received! (Zero fake items added to SR-3!)
        $sr4->refresh();
        $sr3->refresh();
        $this->assertSame('RECEIVED', (string) $sr4->status);
        $this->assertNotSame('RECEIVED', (string) $sr3->status);

        // Check show page for SR-3: received must be 0!
        $show3Mid = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr3->supply_request_id));
        $distRecords3Mid = $show3Mid->viewData('page')['props']['distributionRecords'];
        $this->assertCount(0, $distRecords3Mid);

        // Check show page for SR-4: received must be 100!
        $show4Mid = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr4->supply_request_id));
        $distRecords4Mid = $show4Mid->viewData('page')['props']['distributionRecords'];
        $this->assertCount(2, $distRecords4Mid);

        // 3. Follow-up PR 2 -> SR-6 (deficit: 200/200)
        $followUpPr2 = $prService->createFromPartialDelivery($del2, $this->gsoUser);
        $sr6 = $followUpPr2->supplyRequest;
        $this->assertNotNull($sr6);

        $followUpPo2 = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0006',
            'purchase_request_id' => $followUpPr2->purchase_request_id,
            'supply_request_id' => $sr6->supply_request_id,
            'created_by' => $this->gsoUser->id,
            'supplier_id' => $this->supplier->supplier_id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 0,
        ]);
        $fuPoi2A4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supply_request_item_id' => $sr6->items->where('item_id', $this->item->item_id)->first()->supply_request_item_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);
        $fuPoi2Legal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supply_request_item_id' => $sr6->items->where('item_id', $this->itemLegal->item_id)->first()->supply_request_item_id,
            'item_id' => $this->itemLegal->item_id,
            'item_name_snapshot' => $this->itemLegal->item_name,
            'quantity_ordered' => 200,
            'unit' => 'box',
            'unit_price' => 0,
            'total_price' => 0,
        ]);

        Inventory::query()->where('item_id', $this->item->item_id)->update(['stock_on_hand' => 200, 'available_stock' => 200]);
        Inventory::query()->where('item_id', $this->itemLegal->item_id)->update(['stock_on_hand' => 200, 'available_stock' => 200]);

        // Delivery 3 arrives: 200 A4, 200 Legal received (100% complete)
        $del3 = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0003',
            'purchase_order_id' => $followUpPo2->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'received_by' => $this->gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'receipt_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del3->delivery_id,
            'purchase_order_item_id' => $fuPoi2A4->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 200,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $del3->delivery_id,
            'purchase_order_item_id' => $fuPoi2Legal->purchase_order_item_id,
            'expected_quantity' => 200,
            'received_quantity' => 200,
            'missing_quantity' => 0,
            'item_status' => 'VERIFIED',
        ]);

        // Release to child SR-6:
        $dist3 = $distService->createRecordWithAutomaticRelease($this->gsoUser, [
            'supply_request_id' => $sr6->supply_request_id,
            'delivery_id' => $del3->delivery_id,
            'office_id' => $this->office->id,
        ]);
        $this->actingAs($this->officeUser)->post(route('office.distribution-records.receive', $dist3->distribution_record_id));

        // NOW CHECK FINAL STATUSES:
        $sr3->refresh();
        $sr4->refresh();
        $sr5->refresh();
        $sr6->refresh();

        $this->assertSame('RECEIVED', (string) $sr3->status);
        $this->assertSame('RECEIVED', (string) $sr4->status);
        $this->assertSame('RECEIVED', (string) $sr5->status);
        $this->assertSame('RECEIVED', (string) $sr6->status);

        // SR-3 now has exactly 1 distribution record (from Del 3 via SR-6 for 200 items):
        $show3Final = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr3->supply_request_id));
        $distRecords3Final = $show3Final->viewData('page')['props']['distributionRecords'];
        $this->assertCount(1, $distRecords3Final);
        $this->assertEquals(200.0, (float) $distRecords3Final[0]['items'][0]['quantity_received']);

        // SR-4 still has exactly 2 distribution records:
        $show4Final = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr4->supply_request_id));
        $distRecords4Final = $show4Final->viewData('page')['props']['distributionRecords'];
        $this->assertCount(2, $distRecords4Final);
    }

    public function test_nested_follow_up_sr_lineage_syncs_and_displays_records_properly(): void
    {
        $traceability = app(\App\Services\Procurement\ConsolidationTraceabilityService::class);
        $syncService = app(ProcurementStatusSyncService::class);

        // Root SR-4
        $rootSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0004',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'NORMAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Root Requisition',
            'status' => 'PARTIALLY_RECEIVED',
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $rootSr->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 50,
            'quantity_remaining' => 0,
            'unit' => 'box',
        ]);

        // Direct distribution on root SR (10 boxes)
        $distRoot = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0004',
            'supply_request_id' => $rootSr->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);
        \App\Models\DistributionItem::query()->create([
            'distribution_record_id' => $distRoot->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
            'quantity_released' => 10,
            'quantity_received' => 10,
            'unit' => 'box',
        ]);

        // Intermediate follow-up SR-8 (40 boxes deficit from root)
        $sr8 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'FOLLOW_UP',
            'priority_level' => 'NORMAL',
            'purpose' => 'Follow up requisition 1',
            'status' => 'PARTIALLY_RECEIVED',
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr8->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 40,
            'quantity_remaining' => 0,
            'unit' => 'box',
        ]);
        PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0008',
            'supply_request_id' => $sr8->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'source_supply_request_id' => $rootSr->supply_request_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'PROCESSING',
        ]);

        // First delivery/distribution on SR-8 (30 boxes)
        $dist8 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0008',
            'supply_request_id' => $sr8->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);
        \App\Models\DistributionItem::query()->create([
            'distribution_record_id' => $dist8->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 30,
            'quantity_released' => 30,
            'quantity_received' => 30,
            'unit' => 'box',
        ]);

        // Second follow-up SR-9 (10 boxes deficit from SR-8)
        $sr9 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0009',
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
            'request_category' => 'FOLLOW_UP',
            'priority_level' => 'NORMAL',
            'purpose' => 'Follow up requisition 2',
            'status' => 'APPROVED',
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr9->supply_request_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
            'quantity_remaining' => 0,
            'unit' => 'box',
        ]);
        PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0009',
            'supply_request_id' => $sr9->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->officeUser->id,
            'source_supply_request_id' => $sr8->supply_request_id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'PROCESSING',
        ]);

        // Distribution on SR-9 (10 boxes)
        $dist9 = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0009',
            'supply_request_id' => $sr9->supply_request_id,
            'office_id' => $this->office->id,
            'prepared_by' => $this->gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
        ]);
        \App\Models\DistributionItem::query()->create([
            'distribution_record_id' => $dist9->distribution_record_id,
            'item_id' => $this->item->item_id,
            'item_name_snapshot' => $this->item->item_name,
            'quantity_requested' => 10,
            'quantity_released' => 10,
            'quantity_received' => 10,
            'unit' => 'box',
        ]);

        // Test calculateAllocatedFulfillment for SR-8
        $fulfillment8 = $traceability->calculateAllocatedFulfillment($sr8);
        $this->assertEquals(40.0, (float) $fulfillment8['requested']);
        $this->assertEquals(40.0, (float) $fulfillment8['released']);
        $this->assertEquals(40.0, (float) $fulfillment8['received']);
        $this->assertTrue((bool) $fulfillment8['is_fully_received']);
        $this->assertCount(2, $fulfillment8['allocated_records']);

        // Now test syncCompletedStatusesForReceivedSupplyRequest starting from SR-9
        $syncService->syncCompletedStatusesForReceivedSupplyRequest($sr9, $this->officeUser);

        $sr9->refresh();
        $sr8->refresh();
        $rootSr->refresh();

        $this->assertSame('RECEIVED', (string) $sr9->status);
        $this->assertSame('RECEIVED', (string) $sr8->status);
        $this->assertSame('RECEIVED', (string) $rootSr->status);

        // Check Show page of SR-8: Related Records should display both records
        $response8 = $this->actingAs($this->officeUser)->get(route('office.supply-requests.show', $sr8->supply_request_id));
        $props8 = $response8->viewData('page')['props'];
        $this->assertSame('RECEIVED', (string) $props8['supplyRequest']['status']);
        $this->assertCount(2, $props8['distributionRecords']);
    }
}
