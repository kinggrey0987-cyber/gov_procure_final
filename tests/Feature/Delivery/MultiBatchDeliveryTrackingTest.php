<?php

namespace Tests\Feature\Delivery;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
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
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiBatchDeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $gsoOfficer;
    protected User $procurementOfficer;
    protected Supplier $supplier;
    protected Office $office;
    protected PurchaseOrder $purchaseOrder;
    protected PurchaseOrderItem $purchaseOrderItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = $this->createOffice();
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);

        $context = $this->createSubmittedSupplyRequestContext($this->office, $this->gsoOfficer, 'SR-BATCH-0001');

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procurementOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Multi-batch test PR',
        ]);

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'Metro Supply Corp',
            'contact_person' => 'Juan Dela Cruz',
            'email' => 'metro@supply.local',
            'phone' => '09123456789',
            'address' => 'Municipal Trading Hub',
            'status' => 'ACTIVE',
        ]);

        $this->purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->procurementOfficer->id,
            'po_number' => 'PO-BATCH-2026-001',
            'procurement_method' => 'COMPETITIVE_BIDDING',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'total_amount' => 10000.00,
            'terms_notes' => 'Staggered delivery schedule in 2 batches',
            'remarks' => 'Multi-batch staggered delivery order',
        ]);

        $this->purchaseOrderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
            'item_id' => $context['supplyRequestItem']->item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'specification' => '70gsm 500 sheets per ream',
            'quantity_ordered' => 100,
            'unit' => 'REAMS',
            'unit_price' => 100.00,
            'total_price' => 10000.00,
        ]);
    }

    public function test_can_create_initial_batch_with_batch_tracking_metadata(): void
    {
        $response = $this->actingAs($this->gsoOfficer)->post(route('inventory.deliveries.store'), [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 1,
            'total_batches' => 3,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => false,
            'actual_delivery_date' => now()->toDateString(),
            'remarks' => 'Batch 1 of 3 staggered intake',
        ]);

        $response->assertRedirect();

        $delivery = Delivery::query()->where('purchase_order_id', $this->purchaseOrder->purchase_order_id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame(1, $delivery->batch_number);
        $this->assertSame(3, $delivery->total_batches);
        $this->assertSame('STAGGERED_BATCH', $delivery->delivery_schedule_type);
        $this->assertFalse($delivery->is_final_batch);
        $this->assertSame('Batch #1 of 3', $delivery->batch_label);

        $this->assertDatabaseHas('delivery_items', [
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
            'expected_quantity' => 100,
        ]);
    }

    public function test_subsequent_batch_automatically_increments_batch_number_and_calculates_remaining_balance(): void
    {
        $deliveryService = app(DeliveryService::class);
        $verificationService = app(DeliveryVerificationService::class);

        // 1. Create Batch 1 with scheduled partial quantity of 40
        $batch1 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 1,
            'total_batches' => 2,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => false,
            'actual_delivery_date' => now()->subDay()->toDateString(),
            'remarks' => 'Initial partial batch',
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
                    'expected_quantity' => 40,
                ],
            ],
        ]);

        $this->assertSame(1, $batch1->batch_number);

        // Receive 40 units in Batch 1
        $batch1Item = $batch1->items()->first();
        $deliveryService->updateReceipt($batch1, $this->gsoOfficer, [
            'actual_delivery_date' => now()->subDay()->toDateString(),
            'items' => [
                [
                    'delivery_item_id' => $batch1Item->delivery_item_id,
                    'received_quantity' => 40,
                    'damaged_quantity' => 0,
                ],
            ],
        ]);

        $batch1 = $deliveryService->markReceived($batch1, $this->gsoOfficer, 'Batch 1 received at warehouse');

        // Verify Batch 1
        $verificationService->create($batch1, $this->gsoOfficer, [
            'verification_status' => 'APPROVED',
            'verification_date' => now()->toDateString(),
            'inspection_notes' => 'Batch 1 accepted: 40 of 100 units',
        ]);

        // Purchase order should transition to PARTIALLY_DELIVERED
        $this->assertSame('PARTIALLY_DELIVERED', (string) $this->purchaseOrder->fresh()->status);

        // 2. Create Batch 2 without explicitly supplying batch_number or items
        $batch2 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'total_batches' => 2,
            'actual_delivery_date' => now()->toDateString(),
            'remarks' => 'Second batch arrival',
        ]);

        // Assert auto-incremented batch_number
        $this->assertSame(2, $batch2->batch_number);
        $this->assertSame('STAGGERED_BATCH', $batch2->delivery_schedule_type);
        $this->assertTrue($batch2->is_final_batch);
        $this->assertSame('Batch #2 of 2 (Final)', $batch2->batch_label);

        // Assert auto-calculated remaining quantity: 100 ordered - 40 received in batch 1 = 60 expected
        $batch2Item = $batch2->items()->first();
        $this->assertEquals(60.0, (float) $batch2Item->expected_quantity);
    }

    public function test_final_batch_completion_transitions_purchase_order_to_completed(): void
    {
        $deliveryService = app(DeliveryService::class);
        $verificationService = app(DeliveryVerificationService::class);

        // 1. Create and receive Batch 1 (40 units)
        $batch1 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 1,
            'total_batches' => 2,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => false,
            'actual_delivery_date' => now()->subDays(2)->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
                    'expected_quantity' => 40,
                ],
            ],
        ]);

        $batch1Item = $batch1->items()->first();
        $deliveryService->updateReceipt($batch1, $this->gsoOfficer, [
            'actual_delivery_date' => now()->subDays(2)->toDateString(),
            'items' => [
                [
                    'delivery_item_id' => $batch1Item->delivery_item_id,
                    'received_quantity' => 40,
                    'damaged_quantity' => 0,
                ],
            ],
        ]);
        $batch1 = $deliveryService->markReceived($batch1, $this->gsoOfficer);
        $verificationService->create($batch1, $this->gsoOfficer, [
            'verification_status' => 'APPROVED',
            'verification_date' => now()->subDays(2)->toDateString(),
            'inspection_notes' => 'Batch 1 verified',
        ]);

        $this->assertSame('PARTIALLY_DELIVERED', (string) $this->purchaseOrder->fresh()->status);

        // 2. Create and receive Batch 2 (remaining 60 units)
        $batch2 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 2,
            'total_batches' => 2,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => true,
            'actual_delivery_date' => now()->toDateString(),
        ]);

        $batch2Item = $batch2->items()->first();
        $this->assertEquals(60.0, (float) $batch2Item->expected_quantity);

        $deliveryService->updateReceipt($batch2, $this->gsoOfficer, [
            'actual_delivery_date' => now()->toDateString(),
            'items' => [
                [
                    'delivery_item_id' => $batch2Item->delivery_item_id,
                    'received_quantity' => 60,
                    'damaged_quantity' => 0,
                ],
            ],
        ]);
        $batch2 = $deliveryService->markReceived($batch2, $this->gsoOfficer);
        $verificationService->create($batch2, $this->gsoOfficer, [
            'verification_status' => 'APPROVED',
            'verification_date' => now()->toDateString(),
            'inspection_notes' => 'Final batch verified: all 100 units accounted for',
        ]);

        // Purchase order should now be fully COMPLETED
        $this->assertSame('COMPLETED', (string) $this->purchaseOrder->fresh()->status);
    }

    public function test_sibling_deliveries_queryable_under_same_purchase_order_id(): void
    {
        $deliveryService = app(DeliveryService::class);

        $batch1 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 1,
            'total_batches' => 2,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => false,
            'actual_delivery_date' => now()->toDateString(),
        ]);

        $batch2 = $deliveryService->create($this->gsoOfficer, [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'batch_number' => 2,
            'total_batches' => 2,
            'delivery_schedule_type' => 'STAGGERED_BATCH',
            'is_final_batch' => true,
            'actual_delivery_date' => now()->toDateString(),
        ]);

        // Check Delivery Show payload
        $response = $this->actingAs($this->gsoOfficer)->get(route('inventory.deliveries.show', $batch1->delivery_id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inventory/Delivery/Show')
            ->where('delivery.batch_number', 1)
            ->where('delivery.total_batches', 2)
            ->where('delivery.batch_label', 'Batch #1 of 2')
            ->has('delivery.sibling_deliveries', 2)
        );

        // Check Purchase Order Show payload
        $poResponse = $this->actingAs($this->procurementOfficer)->get(route('procurement.purchase-orders.show', $this->purchaseOrder->purchase_order_id));
        $poResponse->assertOk();
        $poResponse->assertInertia(fn ($page) => $page
            ->component('Procurement/PurchaseOrders/Show')
            ->has('deliveries', 2)
            ->where('deliveries.0.batch_number', 1)
            ->where('deliveries.1.batch_number', 2)
        );
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'code' => 'MTO-TEST',
            'name' => 'Municipal Treasury Office Test',
            'status' => 'ACTIVE',
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            ['name' => $role->name, 'status' => 'ACTIVE']
        );

        return User::query()->create([
            'office_id' => $office->id,
            'role_id' => $roleModel->id,
            'emp_id' => 'EMP-'.strtoupper(uniqid()),
            'name' => $role->name.' User',
            'email' => strtolower($role->value).'_batch_test_'.uniqid().'@test.local',
            'password' => bcrypt('Password123!'),
            'status' => 'ACTIVE',
        ]);
    }

    private function createSubmittedSupplyRequestContext(Office $office, User $officeUser, string $requestCode): array
    {
        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'prepared_by' => $officeUser->id,
            'approved_by' => null,
            'fiscal_year' => 2026,
            'status' => 'SUBMITTED',
            'total_estimated_budget' => 10000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 601,
            'item_category_id' => 61,
            'quantity' => 100,
            'unit_cost' => 100,
            'estimated_budget' => 10000,
            'procurement_schedule' => 'Q1',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => $requestCode,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Multi-batch delivery tracking test',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $supplyRequestItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => 601,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 100,
            'unit' => 'REAMS',
        ]);

        return [
            'ppmp' => $ppmp,
            'ppmpItem' => $ppmpItem,
            'supplyRequest' => $supplyRequest,
            'supplyRequestItem' => $supplyRequestItem,
        ];
    }
}
