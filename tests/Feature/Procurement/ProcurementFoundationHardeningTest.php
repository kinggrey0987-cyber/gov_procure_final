<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementFoundationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_po_number_payload_is_ignored_and_system_generates_unique_numbers(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $context = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-DUP-1');
        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $context['supplyRequest']);
        $secondContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-DUP-2', 'SUBMITTED', 502);
        $secondPurchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $secondContext['supplyRequest']);
        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $payload = [
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'po_number' => 'PO-HARD-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(3)->toDateString(),
            'terms_notes' => 'Net 30',
            'remarks' => 'first',
        ];

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), $payload)
            ->assertRedirect();

        $payload['purchase_request_id'] = $secondPurchaseRequest->purchase_request_id;

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), $payload)
            ->assertRedirect();

        $this->assertSame(2, PurchaseOrder::query()->count());

        $orders = PurchaseOrder::query()->orderBy('purchase_order_id')->get();
        $this->assertNotSame('PO-HARD-0001', (string) $orders[0]->po_number);
        $this->assertNotSame('PO-HARD-0001', (string) $orders[1]->po_number);
        $this->assertNotSame((string) $orders[0]->po_number, (string) $orders[1]->po_number);
    }

    public function test_purchase_order_creation_rejects_purchase_request_that_already_has_po(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $context = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-USED-1');
        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $context['supplyRequest']);
        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $payload = [
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'po_number' => 'PO-USED-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'terms_notes' => 'Net 30',
        ];

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), $payload)
            ->assertRedirect();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), $payload)
            ->assertSessionHasErrors('purchase_request_id');

        $this->assertSame(1, PurchaseOrder::query()->where('purchase_request_id', $purchaseRequest->purchase_request_id)->count());
    }

    public function test_purchase_order_create_dropdown_excludes_purchase_requests_with_existing_po(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $usedContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-DROPDOWN-USED');
        $availableContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-DROPDOWN-OPEN', 'SUBMITTED', 502);
        $usedPurchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $usedContext['supplyRequest']);
        $availablePurchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $availableContext['supplyRequest']);
        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        PurchaseOrder::query()->create([
            'purchase_request_id' => $usedPurchaseRequest->purchase_request_id,
            'supply_request_id' => $usedContext['supplyRequest']->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procurementOfficer->id,
            'po_number' => 'PO-DROPDOWN-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'status' => 'PLACED',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
            'total_amount' => 100,
            'terms_notes' => null,
            'remarks' => null,
        ]);

        $this->actingAs($procurementOfficer)
            ->get(route('procurement.purchase-orders.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Create')
                ->has('eligiblePurchaseRequests', 1)
                ->where('eligiblePurchaseRequests.0.purchase_request_id', $availablePurchaseRequest->purchase_request_id)
            );
    }

    public function test_invalid_status_transitions_are_rejected_for_purchase_requests_and_purchase_orders(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $context = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-STATUS-1');
        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $context['supplyRequest']);

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
                'action' => 'process',
                'remarks' => 'repeat process',
            ])
            ->assertForbidden();

        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'po_number' => 'PO-STATUS-0001',
                'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
                'expected_delivery_date' => now()->addDays(5)->toDateString(),
                'terms_notes' => 'Net 30',
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.items.store', $purchaseOrder->purchase_order_id), [
                'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
                'item_id' => $context['supplyRequestItem']->item_id,
                'item_name_snapshot' => 'Bond Paper A4',
                'specification' => '80gsm',
                'quantity_ordered' => 5,
                'unit' => 'REAM',
                'unit_price' => 100,
            ])
            ->assertRedirect();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.place', $purchaseOrder->purchase_order_id), [
                'remarks' => 'place once',
            ])
            ->assertRedirect();

        $purchaseOrder->refresh();
        $this->assertSame('PLACED', $purchaseOrder->status);

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.place', $purchaseOrder->purchase_order_id), [
                'remarks' => 'place again',
            ])
            ->assertForbidden();
    }

    public function test_purchase_request_creation_rejects_ineligible_and_duplicate_supply_request_conversion(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $draftContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-INELIGIBLE-1', 'DRAFT');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.store'), [
                'supply_request_id' => $draftContext['supplyRequest']->supply_request_id,
            ])
            ->assertSessionHasErrors('supply_request_id');

        $submittedContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-INELIGIBLE-2', 'SUBMITTED');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.store'), [
                'supply_request_id' => $submittedContext['supplyRequest']->supply_request_id,
            ])
            ->assertRedirect();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.store'), [
                'supply_request_id' => $submittedContext['supplyRequest']->supply_request_id,
            ])
            ->assertSessionHasErrors('supply_request_id');

        $this->assertSame(1, PurchaseRequest::query()->where('supply_request_id', $submittedContext['supplyRequest']->supply_request_id)->count());
    }

    public function test_purchase_order_creation_requires_active_supplier_and_derives_supply_request_from_purchase_request(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $primaryContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-LINK-1');
        $otherContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-PO-LINK-2', 'SUBMITTED', 777);

        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $primaryContext['supplyRequest']);
        $inactiveSupplier = $this->createSupplier($procurementOfficer, 'INACTIVE');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $otherContext['supplyRequest']->supply_request_id,
                'supplier_id' => $inactiveSupplier->supplier_id,
            'po_number' => 'PO-LINK-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertSessionHasErrors('supplier_id');

        $activeSupplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $otherContext['supplyRequest']->supply_request_id,
                'supplier_id' => $activeSupplier->supplier_id,
            'po_number' => 'PO-LINK-0002',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $this->assertSame((int) $primaryContext['supplyRequest']->supply_request_id, (int) $purchaseOrder->supply_request_id);
        $this->assertSame(1, PurchaseOrder::query()->count());
    }

    public function test_purchase_order_items_must_belong_to_linked_supply_request(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $primaryContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-ITEM-LINK-1');
        $otherContext = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-ITEM-LINK-2', 'SUBMITTED', 900);

        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $primaryContext['supplyRequest']);
        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $primaryContext['supplyRequest']->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
            'po_number' => 'PO-ITEM-LINK-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.items.store', $purchaseOrder->purchase_order_id), [
                'supply_request_item_id' => $otherContext['supplyRequestItem']->supply_request_item_id,
                'item_id' => $otherContext['supplyRequestItem']->item_id,
                'item_name_snapshot' => 'Unrelated Item',
                'quantity_ordered' => 1,
                'unit' => 'PCS',
                'unit_price' => 10,
            ])
            ->assertSessionHasErrors('supply_request_item_id');

        $this->assertSame(0, $purchaseOrder->items()->count());
    }

    public function test_purchase_order_totals_recompute_when_items_are_updated(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);

        $context = $this->createSubmittedSupplyRequestContext($office, $officeUser, 'SR-TOTALS-1');
        $purchaseRequest = $this->createProcessingPurchaseRequest($procurementOfficer, $context['supplyRequest']);
        $supplier = $this->createSupplier($procurementOfficer, 'ACTIVE');

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.store'), [
                'purchase_request_id' => $purchaseRequest->purchase_request_id,
                'supply_request_id' => $context['supplyRequest']->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
            'po_number' => 'PO-TOTALS-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-orders.items.store', $purchaseOrder->purchase_order_id), [
                'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
                'item_id' => $context['supplyRequestItem']->item_id,
                'item_name_snapshot' => 'Bond Paper A4',
                'quantity_ordered' => 2,
                'unit' => 'REAM',
                'unit_price' => 120,
            ])
            ->assertRedirect();

        $purchaseOrder->refresh();
        $this->assertSame('600.00', (string) $purchaseOrder->total_amount);

        /** @var PurchaseOrderItem $orderItem */
        $orderItem = $purchaseOrder->items()->firstOrFail();

        $this->actingAs($procurementOfficer)
            ->put(route('procurement.purchase-orders.items.update', [
                'purchase_order' => $purchaseOrder->purchase_order_id,
                'purchaseOrderItem' => $orderItem->purchase_order_item_id,
            ]), [
                'supply_request_item_id' => $context['supplyRequestItem']->supply_request_item_id,
                'item_id' => $context['supplyRequestItem']->item_id,
                'item_name_snapshot' => 'Bond Paper A4',
                'quantity_ordered' => 4,
                'unit' => 'REAM',
                'unit_price' => 150,
            ])
            ->assertRedirect();

        $purchaseOrder->refresh();
        $orderItem->refresh();

        $this->assertSame('750.00', (string) $orderItem->total_price);
        $this->assertSame('750.00', (string) $purchaseOrder->total_amount);
    }

    public function test_office_user_procurement_visibility_is_read_only_and_admin_stays_denied(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $admin = $this->createUser(UserRole::ADMIN, $office);

        $this->actingAs($officeUser)->get(route('office.tracking'))->assertOk();

        $this->actingAs($officeUser)->post(route('procurement.purchase-requests.store'), [
            'supply_request_id' => 1,
        ])->assertForbidden();

        $this->actingAs($officeUser)->post(route('procurement.suppliers.store'), [
            'supplier_name' => 'Blocked Supplier',
            'status' => 'ACTIVE',
        ])->assertForbidden();

        $this->actingAs($admin)->get(route('procurement.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.purchase-requests.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('procurement.purchase-requests.store'), [
            'supply_request_id' => 1,
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.suppliers.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('procurement.suppliers.store'), [
            'supplier_name' => 'Blocked Admin Supplier',
            'status' => 'ACTIVE',
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('procurement.purchase-orders.index'))->assertForbidden();
    }

    private function createSubmittedSupplyRequestContext(
        Office $office,
        User $officeUser,
        string $requestCode,
        string $status = 'SUBMITTED',
        int $itemId = 501,
    ): array {
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
            'item_id' => $itemId,
            'item_category_id' => 51,
            'quantity' => 10,
            'unit_cost' => 100,
            'estimated_budget' => 1000,
            'procurement_schedule' => 'Q2',
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => $requestCode,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Hardening test context',
            'priority_level' => 'HIGH',
            'status' => $status,
            'review_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $supplyRequestItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $supplyRequest->supply_request_id,
            'item_id' => $itemId,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_name_snapshot' => 'Item '.$itemId,
            'quantity_requested' => 5,
        ]);

        return [
            'supplyRequest' => $supplyRequest,
            'supplyRequestItem' => $supplyRequestItem,
        ];
    }

    private function createProcessingPurchaseRequest(User $procurementOfficer, SupplyRequest $supplyRequest): PurchaseRequest
    {
        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.store'), [
                'supply_request_id' => $supplyRequest->supply_request_id,
                'remarks' => 'Create for hardening',
            ])
            ->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->firstOrFail();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
                'action' => 'process',
                'remarks' => 'Move to processing',
            ])
            ->assertRedirect();

        return $purchaseRequest->fresh() ?? $purchaseRequest;
    }

    private function createSupplier(User $procurementOfficer, string $status): Supplier
    {
        $name = $status === 'ACTIVE' ? 'Active Supplier '.uniqid() : 'Inactive Supplier '.uniqid();

        $this->actingAs($procurementOfficer)
            ->post(route('procurement.suppliers.store'), [
                'supplier_name' => $name,
                'contact_person' => 'Contact',
                'email' => 'supplier'.uniqid().'@test.local',
                'phone' => '09123456789',
                'address' => 'Supplier Address',
                'status' => $status,
            ])
            ->assertRedirect();

        return Supplier::query()->latest('supplier_id')->firstOrFail();
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Procurement Office',
            'code' => 'PROC_OFFICE',
            'description' => 'Procurement hardening office',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for procurement hardening feature tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
