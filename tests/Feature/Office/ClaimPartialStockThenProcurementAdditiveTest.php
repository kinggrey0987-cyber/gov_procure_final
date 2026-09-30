<?php

namespace Tests\Feature\Office;

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
use App\Services\Inventory\ClaimService;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimPartialStockThenProcurementAdditiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_claim_partial_stock_release_then_procured_distribution_is_additive_and_verifiable(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);

        $office = Office::query()->create(['name' => 'MPDO Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Officer']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'status' => 'active']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Correction Tape',
            'default_unit' => 'piece',
            'status' => 'active',
        ]);

        // Warehouse has 4 in stock
        $inventory = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 4,
            'available_stock' => 4,
            'low_stock_threshold' => 5,
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 10000.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 100,
            'unit_cost' => 50.00,
            'estimated_budget' => 5000.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0010',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office correction tape supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Correction Tape',
            'quantity_requested' => 20,
        ]);

        $distRecord = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0010',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'received_by' => $officeUser->id,
        ]);

        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $distRecord->distribution_record_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Correction Tape',
            'quantity_released' => 20,
            'quantity_received' => 4,
            'quantity_missing' => 16,
            'receipt_condition' => 'DISCREPANCY',
            'unit' => 'piece',
        ]);

        // 1. Office files claim for 16 missing items
        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-00016',
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $office->id,
            'claimed_by' => $officeUser->id,
            'claim_type' => 'MISSING_ITEM',
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 16,
            'quantity_missing' => 16,
            'status' => 'OPEN',
            'claim_date' => now()->toDateString(),
            'remarks' => 'Missing 16 pieces',
        ]);

        // 2. GSO releases 4 partial stock from warehouse directly on the claim
        $claimService = app(ClaimService::class);
        $claimService->updateClaimStatus($claim, $gsoUser, [
            'status' => 'PARTIALLY_RESOLVED',
            'resolution_type' => 'PARTIAL_REPLACEMENT',
            'replacement_quantity' => 4,
            'remarks' => 'Releasing 4 pieces available in warehouse stock',
        ]);

        $claim->refresh();
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->status);
        $this->assertEquals(4.0, (float) $claim->replacement_quantity);
        $this->assertEquals(0.0, (float) $inventory->fresh()->available_stock);

        // 3. GSO queues follow-up PR for remaining 12
        $prService = app(PurchaseRequestService::class);
        $followUpPr = $prService->createFromPendingClaimReplacement($claim, $gsoUser);
        $this->assertInstanceOf(PurchaseRequest::class, $followUpPr);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $followUpPr->source_type);
        $this->assertEquals(12.0, (float) $followUpPr->items->sum('quantity_requested'));

        // 4. Verify Office sees the 4 released items in "Supplies Ready to Claim" with status RELEASED
        $response = $this->actingAs($officeUser)->get(route('office.supplies-ready.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Office/SuppliesReadyForClaim/Index')
            ->has('records.data')
            ->where('records.data', function ($records) use ($claim) {
                $row = collect($records)->firstWhere('claim_id', (int) $claim->claims_id);
                return $row !== null && $row['status'] === 'RELEASED';
            })
        );

        // 5. Office confirms receipt of the 4 items
        $claimService->verifyAndResolveClaim($claim, $officeUser, ['remarks' => 'Received 4 from warehouse']);
        $claim->refresh();
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->status);

        // 6. Procurement creates PO for the 12 items -> verify NOT zero-cost!
        $supplier = Supplier::query()->create([
            'supplier_name' => 'Fast Supplies Inc',
            'contact_person' => 'Supplier Contact',
            'email' => 'fast@example.com',
            'phone_number' => '1234567890',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $roleProcurement = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $procurementUser = User::factory()->create(['role_id' => $roleProcurement->id, 'office_id' => $office->id, 'name' => 'Procurement Officer']);

        $followUpPr->forceFill(['status' => 'PROCESSING'])->save();

        $poService = app(PurchaseOrderService::class);
        $po = $poService->create($procurementUser, [
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'office_id' => $office->id,
            'procurement_method' => 'SHOPPING',
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertFalse((bool) $po->is_zero_cost);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Correction Tape',
            'quantity_ordered' => 12,
            'unit' => 'piece',
            'unit_price' => 50.00,
            'total_price' => 600.00,
        ]);
        $po->forceFill(['total_amount' => 600.00])->save();
        $this->assertGreaterThan(0, (float) $po->total_amount);

        // 7. Delivery of the 12 items arrives and is verified
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0012',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'VERIFIED',
            'delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'created_by' => $gsoUser->id,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'item_id' => $item->item_id,
            'expected_quantity' => 12,
            'received_quantity' => 12,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => 'All 12 received in good condition',
        ]);

        $inventory->forceFill([
            'stock_on_hand' => 12,
            'available_stock' => 12,
        ])->save();

        // 8. GSO creates distribution record for the 12 items
        $distService = app(DistributionRecordService::class);
        $distRec12 = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'office_id' => $office->id,
            'supply_request_id' => $delivery->purchaseOrder->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'storage_location' => 'GSO Storage',
            'notes' => 'Claim replacement for CLM-2026-00016',
            'items' => [
                [
                    'item_id' => $item->item_id,
                    'quantity_released' => 12,
                ],
            ],
        ]);

        // 9. Total replacement released MUST BE 16 (4 warehouse stock + 12 procured), NOT 12!
        $claim->refresh();
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->status);
        $this->assertEquals(16.0, (float) $claim->replacement_quantity);

        // 10. Office receives and confirms the 12 distribution record
        $distService->reconcileOfficeReceipt($distRec12, $officeUser, [
            [
                'distribution_item_id' => $distRec12->items->first()->distribution_item_id,
                'quantity_received' => 12,
                'quantity_damaged' => 0,
                'quantity_missing' => 0,
            ],
        ]);

        $claim->refresh();
        $this->assertSame('RESOLVED', (string) $claim->status);
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->resolution_type);
        $this->assertEquals(16.0, (float) $claim->replacement_quantity);
    }
}
