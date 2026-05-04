<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\Delivery;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementFoundationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_purchase_request_purchase_order_flow_writes_links_totals_and_status_history(): void
    {
        $office = $this->createOffice();
        $officeUser = $this->createUser(UserRole::OFFICE_USER, $office);
        $procurementOfficer = $this->createUser(UserRole::PROCUREMENT_OFFICER, $office);
        $this->createUser(UserRole::GSO_OFFICER, $office);

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
            'remarks' => null,
        ]);

        $supplyRequest = SupplyRequest::query()->create([
            'request_code' => 'SR-PROC-1',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'request_category' => 'GENERAL',
            'purpose' => 'Procurement flow test',
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
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 5,
        ]);

        $createPurchaseRequestResponse = $this->actingAs($procurementOfficer)->post(route('procurement.purchase-requests.store'), [
            'supply_request_id' => $supplyRequest->supply_request_id,
            'remarks' => 'Start procurement',
        ]);
        $createPurchaseRequestResponse->assertRedirect();

        $purchaseRequest = PurchaseRequest::query()->firstOrFail();

        $this->assertSame($supplyRequest->supply_request_id, $purchaseRequest->supply_request_id);
        $this->assertSame($office->id, $purchaseRequest->office_id);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'item_id' => 501,
            'quantity_requested' => 5,
        ]);

        $this->actingAs($procurementOfficer)->post(route('procurement.purchase-requests.process', $purchaseRequest->purchase_request_id), [
            'action' => 'process',
            'remarks' => 'Processing PR',
        ])->assertRedirect();

        $purchaseRequest->refresh();
        $this->assertSame('PROCESSING', $purchaseRequest->status);

        $this->actingAs($procurementOfficer)->post(route('procurement.suppliers.store'), [
            'supplier_name' => 'Acme Supplies',
            'contact_person' => 'Alice',
            'email' => 'alice@acme.test',
            'phone' => '09123456789',
            'address' => 'Main Street',
            'status' => 'ACTIVE',
        ])->assertRedirect();

        $supplier = Supplier::query()->firstOrFail();

        $this->actingAs($procurementOfficer)->post(route('procurement.purchase-orders.store'), [
            'purchase_request_id' => $purchaseRequest->purchase_request_id,
            'supply_request_id' => $supplyRequest->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'po_number' => 'PO-2026-0001',
            'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
            'expected_delivery_date' => now()->addDays(10)->toDateString(),
            'terms_notes' => 'Net 30',
            'remarks' => 'Draft PO',
        ])->assertRedirect();

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($procurementOfficer)->post(route('procurement.purchase-orders.items.store', $purchaseOrder->purchase_order_id), [
            'supply_request_item_id' => $supplyRequestItem->supply_request_item_id,
            'item_id' => 501,
            'item_name_snapshot' => 'Bond Paper A4',
            'specification' => '80gsm',
            'quantity_ordered' => 5,
            'unit' => 'REAM',
            'unit_price' => 100,
        ])->assertRedirect();

        $purchaseOrder->refresh();

        $this->assertSame('500.00', (string) $purchaseOrder->total_amount);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->purchase_order_id,
            'supply_request_item_id' => $supplyRequestItem->supply_request_item_id,
            'item_id' => 501,
            'total_price' => 500,
        ]);

        $this->actingAs($procurementOfficer)->post(route('procurement.purchase-orders.place', $purchaseOrder->purchase_order_id), [
            'action' => 'process',
            'remarks' => 'PO placed',
        ])->assertRedirect();

        $purchaseOrder->refresh();
        $this->assertSame('PLACED', $purchaseOrder->status);

        $delivery = Delivery::query()->where('purchase_order_id', $purchaseOrder->purchase_order_id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame('PENDING', (string) $delivery->receipt_status);
        $this->assertSame((int) $purchaseOrder->supplier_id, (int) $delivery->supplier_id);

        $prHistory = StatusHistory::query()
            ->where('reference_type', PurchaseRequest::class)
            ->where('reference_id', $purchaseRequest->purchase_request_id)
            ->pluck('new_status')
            ->all();

        $poHistory = StatusHistory::query()
            ->where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $purchaseOrder->purchase_order_id)
            ->pluck('new_status')
            ->all();

        $this->assertContains('OPEN', $prHistory);
        $this->assertContains('PROCESSING', $prHistory);
        $this->assertContains('DRAFT', $poHistory);
        $this->assertContains('PLACED', $poHistory);
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Procurement Office',
            'code' => 'PROC_OFFICE',
            'description' => 'Procurement test office',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for procurement feature tests',
                'is_active' => true,
            ],
        );

        return User::factory()->withRole($role)->forOffice($office)->create([
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
        ]);
    }
}
