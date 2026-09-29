<?php

namespace Tests\Feature\Office;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryVerification;
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
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimReplacementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_partial_claim_replacement_confirm_received_and_resolution(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);

        $office = Office::query()->create(['name' => 'MPDO Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Officer']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'status' => 'active']);
        $item = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Bond Paper, Legal (80 GSM)',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        $inventory = Inventory::query()->create([
            'item_id' => $item->item_id,
            'stock_on_hand' => 0,
            'available_stock' => 0,
            'low_stock_threshold' => 5,
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 5000.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 20,
            'unit_cost' => 250.00,
            'estimated_budget' => 5000.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0002',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office bond paper supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 5,
        ]);

        $distRecord = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0002',
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
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_released' => 5,
            'quantity_received' => 0,
            'quantity_missing' => 5,
            'receipt_condition' => 'DISCREPANCY',
            'unit' => 'box',
        ]);

        // 1. Office files claim for 5 missing items
        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-00006',
            'distribution_record_id' => $distRecord->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $office->id,
            'claimed_by' => $officeUser->id,
            'claim_type' => 'MISSING_ITEM',
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 5,
            'status' => 'PENDING_REPLACEMENT',
            'resolution_type' => 'PENDING_REPLACEMENT',
            'replacement_quantity' => 0,
            'claim_date' => now()->toDateString(),
            'remarks' => 'Missing 5 boxes',
        ]);

        // 2. GSO creates follow-up PR
        $prService = app(PurchaseRequestService::class);
        $claimPr = $prService->createFromPendingClaimReplacement($claim, $gsoUser);

        $this->assertInstanceOf(PurchaseRequest::class, $claimPr);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $claimPr->source_type);
        $this->assertSame((int) $claim->claims_id, (int) $claimPr->source_claim_id);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Office Depot Supply',
            'contact_person' => 'Supplier Contact',
            'email' => 'supplier@example.com',
            'phone_number' => '1234567890',
            'address' => 'Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $claimPr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'office_id' => $office->id,
            'procurement_method' => 'SHOPPING',
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 1250.00,
            'status' => 'APPROVED',
            'approved_by' => $gsoUser->id,
            'created_by' => $gsoUser->id,
        ]);

        // Delivery of 5 arrived, but only 4 accepted, 1 discrepancy
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
            'delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'created_by' => $gsoUser->id,
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr->items->first()->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_ordered' => 5,
            'unit' => 'box',
            'unit_price' => 250.00,
            'total_price' => 1250.00,
        ]);

        $deliveryItem = DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'item_id' => $item->item_id,
            'expected_quantity' => 5,
            'received_quantity' => 4,
            'missing_quantity' => 1,
            'item_condition' => 'MISSING',
            'remarks' => '1 box missing on delivery',
        ]);

        // Inventory now has 4
        $inventory->forceFill([
            'stock_on_hand' => 4,
            'available_stock' => 4,
        ])->save();

        // 3. GSO creates follow-up distribution record of 4 items
        $distService = app(DistributionRecordService::class);
        $followUpDist = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'office_id' => $office->id,
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'storage_location' => 'GSO Storage',
            'notes' => 'Claim replacement for CLM-2026-00006',
            'items' => [
                [
                    'item_id' => $item->item_id,
                    'quantity_released' => 4,
                ],
            ],
        ]);

        // Claim should now be updated to REPLACEMENT_RELEASED with replacement_quantity = 4
        $claim->refresh();
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->status);
        $this->assertEquals(4.0, (float) $claim->replacement_quantity);

        // 4. Office views supplies ready for claim
        $response = $this->actingAs($officeUser)->get(route('office.supplies-ready.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Office/SuppliesReadyForClaim/Index')
            ->has('records.data')
            ->where('records.data', fn ($records) => collect($records)->contains('distribution_record_id', (int) $followUpDist->distribution_record_id))
        );

        // 5. Office confirms receipt of the follow-up distribution record
        $confirmResponse = $this->actingAs($officeUser)->post(
            route('office.distribution-records.receive', $followUpDist->distribution_record_id),
            ['remarks' => 'Confirmed receipt of 4 boxes legal bond paper']
        );
        $confirmResponse->assertRedirect();

        $followUpDist->refresh();
        $this->assertSame('RECEIVED', (string) $followUpDist->status);
        $this->assertSame((int) $officeUser->id, (int) $followUpDist->received_by);

        // Claim should now be PARTIALLY_RESOLVED because 4 < 5
        $claim->refresh();
        $this->assertSame('PARTIALLY_RESOLVED', (string) $claim->status);
        $this->assertSame('PARTIAL_REPLACEMENT', (string) $claim->resolution_type);
        $this->assertEquals(4.0, (float) $claim->replacement_quantity);

        // Original distribution item should have received = 4, missing = 1
        $distItem->refresh();
        $this->assertEquals(4.0, (float) $distItem->quantity_received);
        $this->assertEquals(1.0, (float) $distItem->quantity_missing);

        // 6. When the 1 discrepancy replacement arrives, a follow-up delivery is verified and inventory is updated to 1
        $discrepancyDelivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0002',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'VERIFIED',
            'delivery_date' => now()->toDateString(),
            'status' => 'VERIFIED',
            'created_by' => $gsoUser->id,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $discrepancyDelivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'item_id' => $item->item_id,
            'expected_quantity' => 1,
            'received_quantity' => 1,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => '1 box discrepancy replacement delivered in good condition',
        ]);

        $inventory->forceFill([
            'stock_on_hand' => 1,
            'available_stock' => 1,
        ])->save();

        // GSO releases the final 1 box to the office
        $finalDist = $distService->createRecordWithAutomaticRelease($gsoUser, [
            'office_id' => $office->id,
            'supply_request_id' => $sr->supply_request_id,
            'delivery_id' => $discrepancyDelivery->delivery_id,
            'storage_location' => 'GSO Storage',
            'notes' => 'Final claim replacement for CLM-2026-00006',
            'items' => [
                [
                    'item_id' => $item->item_id,
                    'quantity_released' => 1,
                ],
            ],
        ]);

        // Claim replacement_quantity is now 5
        $claim->refresh();
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->status);
        $this->assertEquals(5.0, (float) $claim->replacement_quantity);

        // Office confirms receipt of the final distribution record
        $finalConfirmResponse = $this->actingAs($officeUser)->post(
            route('office.distribution-records.receive', $finalDist->distribution_record_id),
            ['remarks' => 'Confirmed receipt of final box']
        );
        $finalConfirmResponse->assertRedirect();

        // Claim is now fully RESOLVED!
        $claim->refresh();
        $this->assertSame('RESOLVED', (string) $claim->status);
        $this->assertSame('REPLACEMENT_RELEASED', (string) $claim->resolution_type);

        // Original distribution item is now fully received
        $distItem->refresh();
        $this->assertEquals(5.0, (float) $distItem->quantity_received);
        $this->assertEquals(0.0, (float) $distItem->quantity_missing);
        $this->assertSame('GOOD', (string) $distItem->receipt_condition);
    }
}
