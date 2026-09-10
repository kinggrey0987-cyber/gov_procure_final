<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Procurement\ConsolidationTraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidationTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_consolidation_traceability_resolves_gso_and_multi_office_allocations(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleExec = Role::query()->firstOrCreate(['code' => UserRole::EXECUTIVE_OFFICER->value], ['name' => 'Executive Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $officeMpdo = Office::query()->create(['name' => 'Planning Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeEng = Office::query()->create(['name' => 'Engineering Office', 'code' => 'ENG', 'status' => 'ACTIVE']);

        $gsoOfficer = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $officeMpdo->id, 'name' => 'GSO Custodian']);
        $procOfficer = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $officeMpdo->id, 'name' => 'BAC Processor']);
        $execOfficer = User::factory()->create(['role_id' => $roleExec->id, 'office_id' => $officeMpdo->id, 'name' => 'Mayor Approver']);
        $userMpdo = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $officeMpdo->id, 'name' => 'MPDO Requester']);
        $userEng = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $officeEng->id, 'name' => 'ENG Requester']);

        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-MPDO-001',
            'office_id' => $officeMpdo->id,
            'requested_by' => $userMpdo->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Q1 Office Supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => 101,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 10,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-ENG-002',
            'office_id' => $officeEng->id,
            'requested_by' => $userEng->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Engineering Supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => 101,
            'item_name_snapshot' => 'Bond Paper A4',
            'quantity_requested' => 5,
        ]);

        // Create consolidated PR forwarded by GSO
        $pr = PurchaseRequest::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $officeMpdo->id,
            'requested_by' => $gsoOfficer->id,
            'status' => 'OPEN',
            'remarks' => 'Consolidated PR for Planning & Engineering from 2 supply request(s): SR-2026-MPDO-001, SR-2026-ENG-002.',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Test Consolidated Trading',
            'status' => 'ACTIVE',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-TEST-001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procOfficer->id,
            'approved_by' => $execOfficer->id,
            'date_approved' => now()->toDateString(),
            'status' => 'PLACED',
            'total_amount' => 15000,
            'procurement_method' => 'SHOPPING',
        ]);

        $service = app(ConsolidationTraceabilityService::class);
        $prTraceability = $service->resolveForPurchaseRequest($pr);
        $poTraceability = $service->resolveForPurchaseOrder($po);

        // Assertions for PR Traceability
        $this->assertTrue($prTraceability['is_consolidated']);
        $this->assertEquals('GSO Custodian', $prTraceability['gso_forwarder']['name']);
        $this->assertEquals('GSO Property Custodian', $prTraceability['gso_forwarder']['role']);
        $this->assertCount(2, $prTraceability['source_supply_requests']);
        $this->assertNotEmpty($prTraceability['office_allocations']);
        $this->assertEquals('Bond Paper A4', $prTraceability['office_allocations'][0]['item_name']);
        $this->assertEquals(15.0, $prTraceability['office_allocations'][0]['total_quantity']);

        // Assertions for PO Traceability
        $this->assertTrue($poTraceability['is_consolidated']);
        $this->assertEquals('BAC Processor', $poTraceability['procurement_officer']['name']);
        $this->assertEquals('Mayor Approver', $poTraceability['executive_approver']['name']);

        // Test HTTP Show Responses contain traceability
        $this->actingAs($procOfficer)
            ->get(route('procurement.purchase-requests.show', $pr->purchase_request_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseRequests/Show')
                ->has('traceability')
                ->where('traceability.is_consolidated', true)
                ->where('traceability.gso_forwarder.name', 'GSO Custodian')
            );

        $this->actingAs($procOfficer)
            ->get(route('procurement.purchase-orders.show', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->has('traceability')
                ->where('traceability.is_consolidated', true)
                ->where('traceability.procurement_officer.name', 'BAC Processor')
            );
    }

    public function test_consolidated_po_exposes_all_items_and_deduplicates_signatories_and_is_not_zero_cost(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $officeMpdo = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);

        $gsoOfficer = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $officeMpdo->id, 'name' => 'Gso Rieny']);
        $procOfficer = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $officeMpdo->id, 'name' => 'Beta Test Procurement Unit']);
        $userEarl = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $officeMpdo->id, 'name' => 'Earl']);

        // 2 supply requests from the SAME office & SAME requester
        $sr1 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-202609-07',
            'office_id' => $officeMpdo->id,
            'requested_by' => $userEarl->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Office supplies batch 1',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $itemBond = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'item_id' => 101,
            'item_name_snapshot' => 'Bond Paper',
            'quantity_requested' => 100,
        ]);

        $sr2 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-202609-08',
            'office_id' => $officeMpdo->id,
            'requested_by' => $userEarl->id,
            'status' => 'SUBMITTED',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Office supplies batch 2',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        $itemPen = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr2->supply_request_id,
            'item_id' => 102,
            'item_name_snapshot' => 'Ballpen',
            'quantity_requested' => 50,
        ]);

        // Consolidated PR
        $pr = PurchaseRequest::query()->create([
            'supply_request_id' => $sr1->supply_request_id,
            'office_id' => $officeMpdo->id,
            'requested_by' => $gsoOfficer->id,
            'status' => 'PROCESSING',
            'remarks' => 'Consolidated PR for Municipal Planning and Development Office from 2 supply request(s): SR-MPDO-202609-07, SR-MPDO-202609-08.',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Metro Office Depot Trading',
            'status' => 'ACTIVE',
        ]);

        // Create PO in DRAFT with total_amount = 0
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-00003',
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr1->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procOfficer->id,
            'status' => 'DRAFT',
            'total_amount' => 0,
            'procurement_method' => 'COMPETITIVE_BIDDING',
            'remarks' => 'test',
        ]);

        // 1. Traceability Signatories Deduplication Check
        $service = app(ConsolidationTraceabilityService::class);
        $traceability = $service->resolveForPurchaseOrder($po);

        $this->assertTrue($traceability['is_consolidated']);
        $this->assertCount(2, $traceability['source_supply_requests']);
        // Requisitioning signatories should be deduplicated: Earl should only appear once!
        $this->assertCount(1, $traceability['requisitioning_signatories']);
        $this->assertEquals('Earl', $traceability['requisitioning_signatories'][0]['requester_name']);
        $this->assertEquals('MPDO', $traceability['requisitioning_signatories'][0]['office_code']);

        // 2. Draft PO Show check: must NOT be zero-cost warranty replacement
        $this->assertFalse((bool) $po->is_zero_cost_replacement);
        $this->actingAs($procOfficer)
            ->get(route('procurement.purchase-orders.show', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrders/Show')
                ->where('purchaseOrder.is_zero_cost_replacement', false)
                ->where('isZeroCostReplacement', false)
                ->has('traceability.requisitioning_signatories', 1)
            );

        // 3. PO Items Index check: BOTH Bond Paper and Ballpen must be available
        $this->actingAs($procOfficer)
            ->get(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrderItems/Index')
                ->has('sourceItems', 2)
                ->where('sourceItems.0.item_name_snapshot', 'Bond Paper')
                ->where('sourceItems.1.item_name_snapshot', 'Ballpen')
            );

        // 4. Add Ballpen from SR2 to PO: must succeed without context mismatch error
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $itemPen->supply_request_item_id,
                'item_id' => 102,
                'item_name_snapshot' => 'Ballpen',
                'specification' => 'Black 0.5mm',
                'quantity_ordered' => 50,
                'unit' => 'piece',
                'unit_price' => 15.00,
            ])
            ->assertRedirect(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertSessionHas('success');

        // 5. Add Bond Paper from SR1 to PO: must succeed
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $itemBond->supply_request_item_id,
                'item_id' => 101,
                'item_name_snapshot' => 'Bond Paper',
                'specification' => 'A4 70gsm',
                'quantity_ordered' => 100,
                'unit' => 'box',
                'unit_price' => 200.00,
            ])
            ->assertRedirect(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertSessionHas('success');

        // Verify total amount recomputed correctly: 50 * 15 + 100 * 200 = 750 + 20000 = 20750
        $this->assertEquals(20750.00, (float) $po->fresh()->total_amount);
        $this->assertCount(2, $po->fresh()->items);
    }
}
