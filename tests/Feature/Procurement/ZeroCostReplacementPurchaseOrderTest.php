<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Office;
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

class ZeroCostReplacementPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_discrepancy_delivery_generates_zero_cost_replacement_purchase_order(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'Health Office', 'code' => 'MHO', 'status' => 'ACTIVE']);

        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Custodian']);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Procurement Officer']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Nurse Requester']);

        $supplier = Supplier::query()->create(['supplier_name' => 'MedSupply Express', 'status' => 'ACTIVE']);

        // 1. Initial Supply Request & PR
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-MHO-001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'HIGH',
            'request_category' => 'MEDICAL',
            'purpose' => 'First Aid Clinic Kits',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $srItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => 201,
            'item_name_snapshot' => 'N95 Medical Masks (Box of 50)',
            'quantity_requested' => 20,
        ]);

        $pr = PurchaseRequest::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Initial bulk order',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-MED-001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procUser->id,
            'status' => 'PLACED',
            'total_amount' => 10000,
            'procurement_method' => 'SHOPPING',
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem->supply_request_item_id,
            'item_id' => 201,
            'item_name_snapshot' => 'N95 Medical Masks (Box of 50)',
            'quantity_ordered' => 20,
            'unit' => 'box',
            'unit_price' => 500,
            'total_price' => 10000,
        ]);

        // 2. Initial Delivery with Damaged/Missing Items (Discrepancy)
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-MED-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
            'remarks' => '5 boxes missing, 2 boxes water damaged',
        ]);

        $delItem = DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 20,
            'received_quantity' => 13,
            'damaged_quantity' => 2,
            'missing_quantity' => 5,
            'item_condition' => 'DAMAGED',
            'remarks' => '7 boxes replacement required',
        ]);

        // 3. GSO generates follow-up PR for discrepancy
        $prService = app(PurchaseRequestService::class);
        $followUpPr = $prService->createFromPartialDelivery(
            delivery: $delivery,
            actor: $gsoUser,
            remarks: 'Warranty replacement for 7 damaged/missing boxes'
        );

        $this->assertEquals('PARTIAL_DELIVERY_FOLLOW_UP', $followUpPr->source_type);
        $this->assertEquals($delivery->delivery_id, $followUpPr->source_delivery_id);
        $this->assertEquals(7, (int) $followUpPr->items->first()->quantity_requested);

        // Transition PR to PROCESSING
        $followUpPr->forceFill(['status' => 'PROCESSING'])->save();

        // 4. Procurement creates PO from the follow-up PR
        $poService = app(PurchaseOrderService::class);
        $replacementPo = $poService->create(
            actor: $procUser,
            payload: [
                'purchase_request_id' => $followUpPr->purchase_request_id,
                'supplier_id' => $supplier->supplier_id,
                'procurement_method' => 'DIRECT_CONTRACTING',
                'expected_delivery_date' => now()->addDays(3)->toDateString(),
            ]
        );

        // Verify Zero-Cost Replacement rules:
        // - Supplier is the original supplier
        // - Total amount is 0.00 (Zero municipal cost)
        // - Item line unit_price and total_price are 0.00
        $this->assertEquals($supplier->supplier_id, $replacementPo->supplier_id);
        $this->assertEquals(0.00, (float) $replacementPo->total_amount);
        $this->assertCount(1, $replacementPo->items);
        $this->assertEquals(7, (int) $replacementPo->items->first()->quantity_ordered);
        $this->assertEquals(0.00, (float) $replacementPo->items->first()->unit_price);
        $this->assertEquals(0.00, (float) $replacementPo->items->first()->total_price);

        // 5. Verify the replacement PO can be placed
        $placedReplacementPo = $poService->place($replacementPo, $procUser, 'Replacement PO placed with supplier');
        $this->assertEquals('PLACED', $placedReplacementPo->status);

        // Verify replacement delivery was created
        $replacementDelivery = Delivery::query()
            ->where('purchase_order_id', $replacementPo->purchase_order_id)
            ->first();
        $this->assertNotNull($replacementDelivery);
        $this->assertEquals('PENDING', $replacementDelivery->receipt_status);

        // 6. Test HTTP Show response renders zero-cost replacement details
        $this->actingAs($procUser)
            ->get(route('procurement.purchase-orders.show', $replacementPo->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->where('purchaseOrder.total_amount', '0.00')
            );

        // 7. GSO verifies the replacement delivery -> Auto-resolves original parent delivery
        $replacementDelivery->forceFill([
            'receipt_status' => 'RECEIVED',
            'actual_delivery_date' => now()->toDateString(),
            'received_by' => $gsoUser->id,
        ])->save();

        DeliveryItem::query()->create([
            'delivery_id' => $replacementDelivery->delivery_id,
            'purchase_order_item_id' => $replacementPo->items->first()->purchase_order_item_id,
            'expected_quantity' => 7,
            'received_quantity' => 7,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => 'Replacement accepted in full',
        ]);

        $verificationService = app(\App\Services\Delivery\DeliveryVerificationService::class);
        $verificationService->create(
            delivery: $replacementDelivery,
            actor: $gsoUser,
            payload: [
                'verification_status' => 'APPROVED',
                'inspection_notes' => '7 replacement boxes verified in good condition',
            ]
        );

        // Verify that the original parent delivery has automatically transitioned to VERIFIED!
        $this->assertEquals('VERIFIED', $delivery->fresh()->receipt_status);
        $this->assertEquals('VERIFIED', $replacementDelivery->fresh()->receipt_status);
    }
}
