<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimDeliveryDiscrepancyZeroCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_discrepancy_on_claim_fulfillment_delivery_generates_zero_cost_replacement_pr_and_po(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);

        $office = Office::query()->create(['name' => 'Planning Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->withRole(UserRole::OFFICE_USER)->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->withRole(UserRole::GSO_OFFICER)->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Custodian']);
        $procUser = User::factory()->withRole(UserRole::PROCUREMENT_OFFICER)->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Procurement Staff']);

        $supplier = Supplier::query()->create(['supplier_name' => 'Grand Alliance Commercial Corp.', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->create([
            'category_name' => 'Office Supplies',
            'status' => 'active',
        ]);

        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper, A4',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'prepared_by' => $officeUser->id,
            'status' => 'APPROVED',
            'date_prepared' => now()->toDateString(),
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 100,
            'unit_cost' => 200,
            'estimated_budget' => 20000,
            'procurement_schedule' => 'Q1',
        ]);

        // 1. Initial supply request and distribution from which a claim arose
        $origSr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0010',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office use',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $origSrItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $origSr->supply_request_id,
            'item_id' => $item->item_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => $item->item_name,
            'item_specification' => 'Standard bond paper',
            'quantity_requested' => 20,
        ]);

        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIS-2026-09-0001',
            'supply_request_id' => $origSr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'received_by' => $officeUser->id,
        ]);
        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_released' => 20,
            'unit' => 'box',
        ]);

        // 2. Office files claim for 10 missing/defective units
        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-00007',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'claimed_by' => $officeUser->id,
            'office_id' => $office->id,
            'claim_type' => 'REPLACEMENT',
            'claim_location' => 'Planning Office Room 101',
            'claim_date' => now()->toDateString(),
            'quantity_disputed' => 10,
            'status' => 'PENDING_REPLACEMENT',
        ]);

        // 3. GSO forwards claim to procurement -> Normal procurement PR
        $prService = app(PurchaseRequestService::class);
        $claimPr = $prService->createFromPendingClaimReplacement($claim, $gsoUser, 'Procure replacement for claim');

        $this->assertFalse((bool) $claimPr->is_zero_cost_replacement);
        $this->assertTrue((bool) $claimPr->is_claim_replacement);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $claimPr->source_type);

        // 4. Procurement creates PO for 10 units at normal cost
        $claimPr->forceFill(['status' => 'PROCESSING'])->save();
        $poService = app(PurchaseOrderService::class);
        $claimPo = $poService->create($procUser, [
            'purchase_request_id' => $claimPr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
        ]);

        $this->assertFalse((bool) $claimPo->is_zero_cost_replacement);

        // Add items to PO
        $claimSrItem = $claimPr->supplyRequest->items->first();
        $poi = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $claimPo->purchase_order_id,
            'supply_request_item_id' => $claimSrItem->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => $item->item_name,
            'quantity_ordered' => 10,
            'unit' => 'box',
            'unit_price' => 200,
            'total_price' => 2000,
        ]);
        $claimPo->update(['total_amount' => 2000, 'status' => 'PLACED']);

        // 5. Supplier delivers (DEL-2026-09-0008), but there is a DISCREPANCY: 5 received, 5 missing!
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0008',
            'purchase_order_id' => $claimPo->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => now()->toDateString(),
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poi->purchase_order_item_id,
            'expected_quantity' => 10,
            'received_quantity' => 5,
            'missing_quantity' => 5,
            'damaged_quantity' => 0,
            'item_status' => 'REJECTED',
        ]);

        // 6. GSO generates follow-up PR from the partially received delivery discrepancy
        $followUpPr = $prService->createFromPartialDelivery($delivery, $gsoUser, 'Follow-up for 5 missing boxes');

        // ASSERTIONS:
        // The follow-up PR must be a ZERO-COST delivery discrepancy warranty replacement!
        $this->assertSame('PARTIAL_DELIVERY_FOLLOW_UP', (string) $followUpPr->source_type);
        $this->assertTrue((bool) $followUpPr->is_zero_cost_replacement);
        $this->assertFalse((bool) $followUpPr->is_claim_replacement);
        $this->assertSame((int) $delivery->delivery_id, (int) $followUpPr->source_delivery_id);

        // 7. Verify PR Show props
        $prResponse = $this->actingAs($procUser)->get(route('procurement.purchase-requests.show', $followUpPr->purchase_request_id));
        $prResponse->assertOk();
        $prProps = $prResponse->viewData('page')['props']['purchaseRequest'];
        $this->assertTrue((bool) $prProps['is_zero_cost_replacement']);
        $this->assertFalse((bool) $prProps['is_claim_replacement']);

        // 8. Process follow-up PR and verify PO create page
        $followUpPr->forceFill(['status' => 'PROCESSING'])->save();
        $createResponse = $this->actingAs($procUser)->get(route('procurement.purchase-orders.create', ['pr_id' => $followUpPr->purchase_request_id]));
        $createResponse->assertOk();
        $eligibleList = collect($createResponse->viewData('page')['props']['eligiblePurchaseRequests']);
        $eligible = $eligibleList->firstWhere('purchase_request_id', $followUpPr->purchase_request_id);
        $this->assertNotNull($eligible);
        $this->assertTrue((bool) $eligible['is_discrepancy_follow_up']);
        $this->assertFalse((bool) $eligible['is_claim_replacement']);
        $this->assertSame((int) $supplier->supplier_id, (int) $eligible['suggested_supplier_id']);

        // 9. Create Purchase Order from the follow-up PR -> MUST be ₱0.00 zero-cost warranty order
        $replacementPo = $poService->create($procUser, [
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
        ]);

        $this->assertTrue((bool) $replacementPo->is_zero_cost_replacement);
        $this->assertSame(0.0, (float) $replacementPo->total_amount);
        $this->assertCount(1, $replacementPo->items);
        $this->assertSame(0.0, (float) $replacementPo->items->first()->unit_price);
        $this->assertSame(0.0, (float) $replacementPo->items->first()->total_price);
    }

    public function test_migration_repairs_existing_claim_delivery_discrepancy_prs(): void
    {
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $office = Office::query()->create(['name' => 'Planning Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $user = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Grand Alliance', 'status' => 'ACTIVE']);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0014',
            'office_id' => $office->id,
            'requested_by' => $user->id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Claim follow up',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);

        $parentPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $user->id,
            'status' => 'APPROVED',
        ]);

        $parentPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $parentPr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $user->id,
            'status' => 'PLACED',
            'procurement_method' => 'SHOPPING',
            'total_amount' => 1000,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0008',
            'purchase_order_id' => $parentPo->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $user->id,
            'actual_delivery_date' => now()->toDateString(),
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);

        // Simulate an existing buggy record like PR-2026-09-0011
        $buggyPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0011',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $user->id,
            'source_delivery_id' => $delivery->delivery_id,
            'source_claim_id' => null,
            'source_type' => 'CLAIM_REPLACEMENT_FOLLOW_UP',
            'remarks' => 'Follow-up procurement generated from delivery DEL-2026-09-0008 for SR-MPDO-2026-09-0014.',
            'status' => 'OPEN',
        ]);

        // Before migration, the buggy record was misclassified because source_type was CLAIM_REPLACEMENT_FOLLOW_UP
        $this->assertFalse((bool) $buggyPr->is_zero_cost_replacement);
        $this->assertTrue((bool) $buggyPr->is_claim_replacement);

        // Run the migration explicitly to test database update
        $migration = require database_path('migrations/2026_09_30_000003_repair_claim_delivery_discrepancy_followups.php');
        $migration->up();

        $buggyPr->refresh();
        $this->assertSame('PARTIAL_DELIVERY_FOLLOW_UP', (string) $buggyPr->source_type);
        $this->assertTrue((bool) $buggyPr->is_zero_cost_replacement);
        $this->assertFalse((bool) $buggyPr->is_claim_replacement);
    }
}

