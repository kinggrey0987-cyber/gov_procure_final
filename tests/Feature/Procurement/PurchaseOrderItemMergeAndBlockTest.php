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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderItemMergeAndBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_same_item_merges_quantity_and_blocks_unlimited_additions(): void
    {
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $office = Office::query()->create(['name' => 'General Services', 'code' => 'GSO', 'status' => 'ACTIVE']);

        $procOfficer = User::factory()->create([
            'role_id' => $roleProc->id,
            'office_id' => $office->id,
            'name' => 'Procurement Staff',
        ]);
        $officeUser = User::factory()->create([
            'role_id' => $roleOffice->id,
            'office_id' => $office->id,
            'name' => 'Office User',
        ]);

        $supplier = Supplier::query()->create([
            'supplier_name' => 'Paper Supplies Inc.',
            'contact_person' => 'Jane Paper',
            'email' => 'jane@papersupplies.test',
            'phone' => '09123456789',
            'address' => 'Paper Street',
            'status' => 'ACTIVE',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-2026-09-0001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'priority_level' => 'NORMAL',
            'request_category' => 'OFFICE_SUPPLIES',
            'purpose' => 'Office Supplies',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);

        // Q1 scheduling: 16 boxes
        $srItem1 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 16,
        ]);

        // Q2 scheduling: 30 boxes (same item, same spec)
        $srItem2 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'item_id' => 1,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 30,
        ]);

        $pr = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'APPROVED',
            'purpose' => 'Test PR',
        ]);

        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $pr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'supply_request_id' => $sr->supply_request_id,
            'created_by' => $procOfficer->id,
            'status' => 'DRAFT',
            'procurement_method' => 'COMPETITIVE_BIDDING',
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'total_amount' => 0,
        ]);

        // 1. Add first line (16 boxes @ 50.00)
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $srItem1->supply_request_item_id,
                'item_id' => 1,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'specification' => '',
                'quantity_ordered' => 16,
                'unit' => 'box',
                'unit_price' => 50.00,
            ])
            ->assertRedirect(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertSessionHas('success');

        $this->assertCount(1, $po->fresh()->items);
        $this->assertEquals(16.00, (float) $po->fresh()->items->first()->quantity_ordered);
        $this->assertEquals(800.00, (float) $po->fresh()->total_amount);

        // 2. Add second line (30 boxes @ 50.00) -> MUST merge into 1 line, total qty 46, total amount 2300
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $srItem2->supply_request_item_id,
                'item_id' => 1,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'specification' => '',
                'quantity_ordered' => 30,
                'unit' => 'box',
                'unit_price' => 50.00,
            ])
            ->assertRedirect(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertSessionHas('success');

        // Verify still ONE line in PO items table, but quantity combined
        $this->assertCount(1, $po->fresh()->items);
        $mergedLine = $po->fresh()->items->first();
        $this->assertEquals(46.00, (float) $mergedLine->quantity_ordered);
        $this->assertEquals(50.00, (float) $mergedLine->unit_price);
        $this->assertEquals(2300.00, (float) $mergedLine->total_price);
        $this->assertEquals(2300.00, (float) $po->fresh()->total_amount);

        // Verify source_request_item_ids contains BOTH IDs
        $this->assertEquals(
            [(int) $srItem1->supply_request_item_id, (int) $srItem2->supply_request_item_id],
            $mergedLine->source_request_item_ids
        );

        // 3. PO Items Index check: BOTH line 1 and line 2 must be marked in encodedSourceItemIds
        $this->actingAs($procOfficer)
            ->get(route('procurement.purchase-orders.items.index', $po->purchase_order_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Procurement/PurchaseOrderItems/Index')
                ->where('encodedSourceItemIds', [
                    (int) $srItem1->supply_request_item_id,
                    (int) $srItem2->supply_request_item_id,
                ])
            );

        // 4. Attempt to add line 2 AGAIN -> MUST be blocked!
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $srItem2->supply_request_item_id,
                'item_id' => 1,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'specification' => '',
                'quantity_ordered' => 30,
                'unit' => 'box',
                'unit_price' => 50.00,
            ])
            ->assertSessionHasErrors('supply_request_item_id');

        // Verify quantity did NOT increase
        $this->assertEquals(46.00, (float) $po->fresh()->items->first()->quantity_ordered);

        // 5. Attempt to add line 1 AGAIN -> MUST also be blocked!
        $this->actingAs($procOfficer)
            ->post(route('procurement.purchase-orders.items.store', $po->purchase_order_id), [
                'supply_request_item_id' => $srItem1->supply_request_item_id,
                'item_id' => 1,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'specification' => '',
                'quantity_ordered' => 16,
                'unit' => 'box',
                'unit_price' => 50.00,
            ])
            ->assertSessionHasErrors('supply_request_item_id');

        $this->assertEquals(46.00, (float) $po->fresh()->items->first()->quantity_ordered);
    }
}
