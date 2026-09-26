<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Item;
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

    public function test_discrepancy_follow_up_pr_cannot_be_returned_or_rejected(): void
    {
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $office = Office::query()->create(['name' => 'General Services Office', 'code' => 'GSO', 'status' => 'ACTIVE']);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Proc Officer']);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-GSO-099',
            'office_id' => $office->id,
            'requested_by' => $procUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Discrepancy follow up source',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $followUpPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0099',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procUser->id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'OPEN',
            'remarks' => 'Follow up PR for discrepancy replacement',
        ]);

        $this->assertTrue($followUpPr->is_zero_cost_replacement);

        // 1. Attempting to return for revision should fail
        $responseReturn = $this->actingAs($procUser)->post(
            route('procurement.purchase-requests.process', $followUpPr->purchase_request_id),
            [
                'action' => 'return',
                'remarks' => 'Needs revision',
            ]
        );
        $responseReturn->assertSessionHasErrors(['action']);

        // 2. Attempting to reject should fail
        $responseReject = $this->actingAs($procUser)->post(
            route('procurement.purchase-requests.process', $followUpPr->purchase_request_id),
            [
                'action' => 'reject',
                'remarks' => 'Rejecting request',
            ]
        );
        $responseReject->assertSessionHasErrors(['action']);

        // 3. Moving to processing should succeed
        $responseProcess = $this->actingAs($procUser)->post(
            route('procurement.purchase-requests.process', $followUpPr->purchase_request_id),
            [
                'action' => 'process',
                'remarks' => 'Approved to proceed with replacement order',
            ]
        );
        $responseProcess->assertRedirect(route('procurement.purchase-requests.show', ['purchase_request' => $followUpPr->purchase_request_id]));
        $this->assertEquals('PROCESSING', $followUpPr->fresh()->status);
    }

    public function test_warranty_replacement_po_supplier_cannot_be_changed(): void
    {
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $office = Office::query()->create(['name' => 'General Services Office', 'code' => 'GSO', 'status' => 'ACTIVE']);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Proc Officer']);

        $originalSupplier = Supplier::query()->create(['supplier_name' => 'Original Contractor', 'status' => 'ACTIVE']);
        $differentSupplier = Supplier::query()->create(['supplier_name' => 'Different Contractor', 'status' => 'ACTIVE']);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-GSO-100',
            'office_id' => $office->id,
            'requested_by' => $procUser->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Replacement source',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $followUpPr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0100',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procUser->id,
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'PROCESSING',
            'remarks' => 'Discrepancy replacement PR',
        ]);

        $replacementPo = PurchaseOrder::query()->create([
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $originalSupplier->supplier_id,
            'created_by' => $procUser->id,
            'po_number' => 'PO-2026-09-0088',
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'DRAFT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 0.00,
            'terms_notes' => 'Zero-cost replacement',
        ]);

        $this->assertTrue($replacementPo->is_zero_cost_replacement);

        // 1. GET Edit page should pass isZeroCostReplacement: true
        $this->actingAs($procUser)
            ->get(route('procurement.purchase-orders.edit', $replacementPo->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Edit')
                ->where('isZeroCostReplacement', true)
                ->where('purchaseOrder.supplier_id', $originalSupplier->supplier_id)
            );

        // 2. Attempting to update with a different supplier must fail
        $responseChangeSupplier = $this->actingAs($procUser)->put(
            route('procurement.purchase-orders.update', $replacementPo->purchase_order_id),
            [
                'supplier_id' => $differentSupplier->supplier_id,
                'po_number' => $replacementPo->po_number,
                'procurement_method' => 'DIRECT_CONTRACTING',
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'terms_notes' => 'Updated notes',
            ]
        );
        $responseChangeSupplier->assertSessionHasErrors(['supplier_id']);

        // 3. Updating while keeping the original supplier succeeds
        $responseKeepSupplier = $this->actingAs($procUser)->put(
            route('procurement.purchase-orders.update', $replacementPo->purchase_order_id),
            [
                'supplier_id' => $originalSupplier->supplier_id,
                'po_number' => $replacementPo->po_number,
                'procurement_method' => 'DIRECT_CONTRACTING',
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'terms_notes' => 'Updated terms for warranty order',
            ]
        );
        $responseKeepSupplier->assertRedirect(route('procurement.purchase-orders.items.index', $replacementPo->purchase_order_id));
        $this->assertEquals($originalSupplier->supplier_id, $replacementPo->fresh()->supplier_id);
    }

    public function test_executive_views_delivery_receipts_and_compliance_attachments_dossier_on_po_show(): void
    {
        $roleMayor = Role::query()->firstOrCreate(['code' => UserRole::EXECUTIVE_OFFICER->value], ['name' => 'Municipal Mayor']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $office = Office::query()->create(['name' => 'Office of the Mayor', 'code' => 'MO', 'status' => 'ACTIVE']);

        $mayorUser = User::factory()->create(['role_id' => $roleMayor->id, 'office_id' => $office->id, 'name' => 'Mayor Jerry']);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Proc Jeron']);

        $supplier = Supplier::query()->create(['supplier_name' => 'Office Depot PH', 'status' => 'ACTIVE']);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-MO-001',
            'office_id' => $office->id,
            'requested_by' => $mayorUser->id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Office equipment',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-MO-001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $mayorUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Executive equipment request',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-MO-001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procUser->id,
            'status' => 'PLACED',
            'total_amount' => 25000.00,
            'procurement_method' => 'SHOPPING',
        ]);

        $category = \App\Models\ItemCategory::query()->firstOrCreate(['category_name' => 'Office Supplies']);
        $item = Item::query()->firstOrCreate(['item_name' => 'Laser Printer Paper'], ['item_category_id' => $category->item_category_id, 'default_unit' => 'ream', 'status' => 'ACTIVE']);
        $srItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Laser Printer Paper',
            'quantity_requested' => 50,
        ]);
        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $srItem->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Laser Printer Paper',
            'quantity_ordered' => 50,
            'unit' => 'ream',
            'unit_price' => 500,
            'total_price' => 25000,
        ]);

        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-MO-001',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'receipt_status' => 'VERIFIED',
            'delivery_receipt_no' => 'DR-2026-9901',
            'sales_invoice_no' => 'SI-2026-8802',
            'sales_invoice_date' => now()->toDateString(),
            'sales_invoice_amount' => 25000.00,
            'actual_delivery_date' => now()->toDateString(),
            'remarks' => 'Complete delivery verified by GSO',
        ]);

        $deliveryItem = DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poItem->purchase_order_item_id,
            'expected_quantity' => 50,
            'received_quantity' => 50,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => 'All in good condition',
        ]);

        // 1. Executive accesses PO via Executive route -> isExecutive is true, deliveries & DR/SI are present
        $this->actingAs($mayorUser)
            ->get(route('executive.procurement-records.purchase-orders.show', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->where('isExecutive', true)
                ->where('deliveries.0.delivery_receipt_no', 'DR-2026-9901')
                ->where('deliveries.0.sales_invoice_no', 'SI-2026-8802')
                ->where('deliveries.0.sales_invoice_amount', 25000)
                ->where('deliveries.0.receipt_status', 'VERIFIED')
                ->where('deliveries.0.items.0.item_name', 'Laser Printer Paper')
            );

        // 2. Non-executive (Procurement Officer) accesses PO -> isExecutive is false, deliveries array is empty
        $this->actingAs($procUser)
            ->get(route('procurement.purchase-orders.show', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->where('isExecutive', false)
                ->where('deliveries', [])
            );
    }
}
