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
use App\Services\Delivery\DeliveryItemService;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $gsoOfficer;
    protected User $procurementOfficer;
    protected User $officeUser;
    protected User $adminUser;
    protected PurchaseOrder $purchaseOrder;
    protected PurchaseOrderItem $purchaseOrderItem;
    protected Supplier $supplier;
    protected Office $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->office = $this->createOffice();
        $this->gsoOfficer = $this->createUser(UserRole::GSO_OFFICER, $this->office);
        $this->procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $this->office);
        $this->officeUser = $this->createUser(UserRole::OFFICE_USER, $this->office);
        $this->adminUser = $this->createUser(UserRole::ADMIN, $this->office);

        $context = $this->createSubmittedSupplyRequestContext($this->office, $this->gsoOfficer, 'SR-DEL-0001');

        $purchaseRequest = PurchaseRequest::query()->create([
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'office_id' => $this->office->id,
            'requested_by' => $this->procurementOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Delivery test PR',
        ]);

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'Delivery Test Supplier',
            'contact_person' => 'Delivery Contact',
            'email' => 'delivery-supplier@test.local',
            'phone' => '09123456789',
            'address' => 'Delivery Supplier Address',
            'status' => 'ACTIVE',
        ]);

        $this->purchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $context['supplyRequest']->supply_request_id,
            'supplier_id' => $this->supplier->supplier_id,
            'created_by' => $this->procurementOfficer->id,
            'po_number' => 'PO-DEL-0001',
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'total_amount' => 500,
            'terms_notes' => 'Net 30',
            'remarks' => 'Delivery test PO',
        ]);

        $this->purchaseOrderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
            'item_id' => $context['supplyRequestItem']->item_id,
            'item_name_snapshot' => 'Item '.$context['supplyRequestItem']->item_id,
            'specification' => 'Delivery test spec',
            'quantity_ordered' => 5,
            'unit' => 'PCS',
            'unit_price' => 100,
            'total_price' => 500,
        ]);
    }

    public function test_gso_officer_can_create_delivery(): void
    {
        $response = $this->actingAs($this->gsoOfficer)->post(route('inventory.deliveries.store'), [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'remarks' => 'Initial delivery',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deliveries', [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'receipt_status' => 'PENDING',
        ]);
    }

    public function test_non_gso_officer_cannot_create_delivery(): void
    {
        $this->actingAs($this->procurementOfficer)->post(route('inventory.deliveries.store'), [
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
        ])->assertForbidden();
    }

    public function test_gso_officer_can_view_delivery_index(): void
    {
        $this->createDelivery();
        $this->createDelivery();
        $this->createDelivery();

        $response = $this->actingAs($this->gsoOfficer)->get(route('inventory.deliveries.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inventory/Delivery/Index')
            ->has('deliveries.data', 3)
        );
    }

    public function test_gso_officer_can_view_delivery_show(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->gsoOfficer)->get(route('inventory.deliveries.show', $delivery->delivery_id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inventory/Delivery/Show')
            ->where('delivery.delivery_id', $delivery->delivery_id)
        );
    }

    public function test_gso_officer_can_update_delivery(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->actingAs($this->gsoOfficer)->put(route('inventory.deliveries.update', $delivery->delivery_id), [
            'remarks' => 'Updated remarks',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'remarks' => 'Updated remarks',
        ]);
    }

    public function test_gso_officer_can_mark_delivery_as_received(): void
    {
        $delivery = $this->createDelivery();

        $service = app(DeliveryService::class);
        $service->markReceived($delivery, $this->gsoOfficer, 'Item received in good condition');

        $this->assertDatabaseHas('deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'receipt_status' => 'RECEIVED',
        ]);
    }

    public function test_verification_can_only_happen_on_received_delivery(): void
    {
        $pendingDelivery = $this->createDelivery();

        $service = app(DeliveryVerificationService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create($pendingDelivery, $this->gsoOfficer, [
            'verification_date' => now()->toDateString(),
            'verification_status' => 'APPROVED',
        ]);
    }

    public function test_gso_officer_can_verify_delivery(): void
    {
        $delivery = $this->createDelivery(status: 'RECEIVED');

        $response = $this->actingAs($this->gsoOfficer)->post(route('inventory.delivery-verifications.store', $delivery->delivery_id), [
            'verification_date' => now()->toDateString(),
            'verification_status' => 'APPROVED',
            'discrepancy_notes' => 'None',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_verifications', [
            'delivery_id' => $delivery->delivery_id,
            'verification_status' => 'APPROVED',
        ]);
    }

    public function test_delivery_items_can_be_added(): void
    {
        $delivery = $this->createDelivery();

        $service = app(DeliveryItemService::class);
        $service->addItem($delivery, $this->gsoOfficer, [
            'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
            'received_quantity' => 5,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
        ]);

        $this->assertDatabaseHas('delivery_items', [
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
            'expected_quantity' => 5,
            'received_quantity' => 5,
        ]);
    }

    public function test_delivery_items_can_be_updated(): void
    {
        $delivery = $this->createDelivery();
        $item = DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
            'expected_quantity' => 5,
            'received_quantity' => 4,
            'damaged_quantity' => 1,
            'missing_quantity' => 0,
            'item_condition' => 'GOOD',
            'remarks' => null,
        ]);

        $service = app(DeliveryItemService::class);
        $service->updateItem($delivery, $item, $this->gsoOfficer, [
            'received_quantity' => 3,
            'damaged_quantity' => 2,
        ]);

        $this->assertDatabaseHas('delivery_items', [
            'delivery_item_id' => $item->delivery_item_id,
            'received_quantity' => 3,
            'damaged_quantity' => 2,
        ]);
    }

    public function test_delivery_creation_rejects_supplier_mismatch_with_purchase_order(): void
    {
        $otherSupplier = Supplier::query()->create([
            'supplier_name' => 'Other Supplier',
            'contact_person' => 'Other Contact',
            'email' => 'other-supplier@test.local',
            'phone' => '09999999999',
            'address' => 'Other Address',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->gsoOfficer)
            ->post(route('inventory.deliveries.store'), [
                'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
                'supplier_id' => $otherSupplier->supplier_id,
            ])
            ->assertSessionHasErrors('supplier_id');
    }

    public function test_delivery_item_quantities_cannot_exceed_expected_quantity(): void
    {
        $delivery = $this->createDelivery();

        $service = app(DeliveryItemService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->addItem($delivery, $this->gsoOfficer, [
            'purchase_order_item_id' => $this->purchaseOrderItem->purchase_order_item_id,
            'received_quantity' => 4,
            'damaged_quantity' => 2,
            'missing_quantity' => 1,
            'item_condition' => 'DAMAGED',
        ]);
    }

    public function test_delivery_item_cannot_reference_unrelated_purchase_order_item(): void
    {
        $delivery = $this->createDelivery();

        $otherSupplier = Supplier::query()->create([
            'supplier_name' => 'Unrelated Supplier',
            'contact_person' => 'Other Contact',
            'email' => 'unrelated-supplier@test.local',
            'phone' => '09888888888',
            'address' => 'Other Address',
            'status' => 'ACTIVE',
        ]);

        $otherPurchaseOrder = PurchaseOrder::query()->create([
            'purchase_request_id' => $this->purchaseOrder->purchase_request_id,
            'supply_request_id' => $this->purchaseOrder->supply_request_id,
            'supplier_id' => $otherSupplier->supplier_id,
            'created_by' => $this->procurementOfficer->id,
            'po_number' => 'PO-DEL-0002',
            'procurement_method' => 'SHOPPING',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 100,
            'terms_notes' => 'Net 30',
            'remarks' => 'Other PO',
        ]);

        $otherPurchaseOrderItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $otherPurchaseOrder->purchase_order_id,
            'supply_request_item_id' => $this->purchaseOrderItem->supply_request_item_id,
            'item_id' => $this->purchaseOrderItem->item_id,
            'item_name_snapshot' => 'Unrelated Item',
            'specification' => 'Other spec',
            'quantity_ordered' => 1,
            'unit' => 'PCS',
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        $service = app(DeliveryItemService::class);
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->addItem($delivery, $this->gsoOfficer, [
            'purchase_order_item_id' => $otherPurchaseOrderItem->purchase_order_item_id,
            'received_quantity' => 1,
            'damaged_quantity' => 0,
            'missing_quantity' => 0,
        ]);
    }

    public function test_verification_rejects_repeated_verification_attempts(): void
    {
        $delivery = $this->createDelivery(status: 'RECEIVED');

        $verificationService = app(DeliveryVerificationService::class);
        $verificationService->create($delivery, $this->gsoOfficer, [
            'verification_date' => now()->toDateString(),
            'verification_status' => 'APPROVED',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $verificationService->create($delivery->fresh(), $this->gsoOfficer, [
            'verification_date' => now()->toDateString(),
            'verification_status' => 'REJECTED',
        ]);
    }

    public function test_procurement_officer_is_denied_on_inventory_delivery_routes_and_cannot_mutate(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->procurementOfficer)
            ->get(route('inventory.deliveries.show', $delivery->delivery_id))
            ->assertForbidden();

        $this->actingAs($this->procurementOfficer)
            ->put(route('inventory.deliveries.update', $delivery->delivery_id), ['remarks' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($this->procurementOfficer)
            ->post(route('inventory.delivery-verifications.store', $delivery->delivery_id), [
                'verification_date' => now()->toDateString(),
                'verification_status' => 'APPROVED',
            ])
            ->assertForbidden();
    }

    public function test_office_user_cannot_mutate_delivery_records(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->officeUser)
            ->post(route('inventory.deliveries.store'), [
                'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
                'supplier_id' => $this->supplier->supplier_id,
            ])
            ->assertForbidden();

        $this->actingAs($this->officeUser)
            ->put(route('inventory.deliveries.update', $delivery->delivery_id), [
                'remarks' => 'blocked update',
            ])
            ->assertForbidden();
    }

    public function test_admin_is_denied_from_delivery_business_routes(): void
    {
        $delivery = $this->createDelivery();

        $this->actingAs($this->adminUser)
            ->get(route('inventory.deliveries.index'))
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->get(route('inventory.deliveries.show', $delivery->delivery_id))
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->post(route('inventory.deliveries.store'), [
                'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
                'supplier_id' => $this->supplier->supplier_id,
            ])
            ->assertForbidden();
    }

    private function createDelivery(string $status = 'PENDING'): Delivery
    {
        return Delivery::query()->create([
            'delivery_code' => 'DEL-TEST-'.uniqid(),
            'purchase_order_id' => $this->purchaseOrder->purchase_order_id,
            'supplier_id' => $this->supplier->supplier_id,
            'actual_delivery_date' => now()->toDateString(),
            'expected_arrival_date' => now()->addDay()->toDateString(),
            'received_by' => $this->gsoOfficer->id,
            'receipt_status' => $status,
            'remarks' => 'Delivery test record',
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
            'total_estimated_budget' => 1000,
            'date_prepared' => now()->toDateString(),
            'date_approved' => null,
            'remarks' => null,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => 501,
            'item_category_id' => 51,
            'quantity' => 10,
            'unit_cost' => 100,
            'estimated_budget' => 1000,
            'procurement_schedule' => 'Q2',
            'fund_source' => 'General Fund',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => $requestCode,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Delivery test context',
            'priority_level' => 'HIGH',
            'status' => 'SUBMITTED',
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $supplyRequestItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => 501,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Item 501',
            'quantity_requested' => 5,
        ]);

        return [
            'supplyRequest' => $supplyRequest,
            'supplyRequestItem' => $supplyRequestItem,
        ];
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Delivery Test Office',
            'code' => 'DEL_OFFICE',
            'description' => 'Delivery feature test office',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for delivery feature tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
