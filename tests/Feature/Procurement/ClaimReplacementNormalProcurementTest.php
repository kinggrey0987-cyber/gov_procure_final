<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
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
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaimReplacementNormalProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_claim_replacement_follow_up_pr_and_po_are_treated_as_normal_procurement(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);

        $office = Office::query()->create(['name' => 'Planning Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id, 'name' => 'Office Requester']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id, 'name' => 'GSO Custodian']);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id, 'name' => 'Procurement Staff']);

        $supplier = Supplier::query()->create(['supplier_name' => 'Office Depot', 'status' => 'ACTIVE']);

        $category = \App\Models\ItemCategory::query()->create([
            'category_name' => 'IT Supplies',
            'status' => 'active',
        ]);

        $catalogItem = \App\Models\Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Printer Ink, Black',
            'default_unit' => 'box',
            'status' => 'active',
        ]);

        // PPMP for the office with benchmark price
        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 4500.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $catalogItem->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 450.00,
            'estimated_budget' => 4500.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
            'remarks' => 'Brand genuine black ink',
        ]);

        // Original Supply Request
        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-001',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office printing',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        $srItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $catalogItem->item_id,
            'item_name_snapshot' => 'Printer Ink, Black',
            'item_specification' => 'Brand genuine black ink',
            'quantity_requested' => 5,
        ]);

        // Distribution Record
        $dist = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-001',
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'release_date' => now()->toDateString(),
            'status' => 'RECEIVED',
            'received_by' => $officeUser->id,
        ]);

        $distItem = DistributionItem::query()->create([
            'distribution_record_id' => $dist->distribution_record_id,
            'item_id' => $catalogItem->item_id,
            'item_name_snapshot' => 'Printer Ink, Black',
            'quantity_released' => 5,
            'unit' => 'box',
        ]);

        // Claim filed by office
        $claim = Claim::query()->create([
            'claim_code' => 'CLM-2026-001',
            'distribution_record_id' => $dist->distribution_record_id,
            'distribution_item_id' => $distItem->distribution_item_id,
            'office_id' => $office->id,
            'claimed_by' => $officeUser->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_location' => 'Office Storage',
            'quantity_disputed' => 5,
            'status' => 'PENDING_REPLACEMENT',
            'resolution_type' => 'PENDING_REPLACEMENT',
            'replacement_quantity' => 0,
            'claim_date' => now()->toDateString(),
            'remarks' => 'Defective ink cartridges out of stock in warehouse',
        ]);

        // 1. Create Follow-up PR via PurchaseRequestService
        $prService = app(PurchaseRequestService::class);
        $claimPr = $prService->createFromPendingClaimReplacement($claim, $gsoUser);

        // Verification 1: PR is NOT a zero cost replacement
        $this->assertFalse((bool) $claimPr->is_zero_cost_replacement);
        $this->assertSame('CLAIM_REPLACEMENT_FOLLOW_UP', (string) $claimPr->source_type);
        $this->assertSame((int) $claim->claims_id, (int) $claimPr->source_claim_id);

        // Verify that the generated SupplyRequestItem has the linked PPMP info
        $followUpSrItem = $claimPr->supplyRequest?->items->first();
        $this->assertNotNull($followUpSrItem);
        $this->assertSame((int) $ppmpItem->ppmp_item_id, (int) $followUpSrItem->ppmp_item_id);

        // 2. Process PR and Create Purchase Order from this PR
        $claimPr->forceFill(['status' => 'PROCESSING'])->save();

        $poService = app(PurchaseOrderService::class);
        $po = $poService->create($procUser, [
            'purchase_request_id' => $claimPr->purchase_request_id,
            'supplier_id' => $supplier->supplier_id,
            'procurement_method' => 'SHOPPING',
            'delivery_address' => 'Municipal Hall',
            'payment_terms' => 'COD',
            'delivery_timeline_days' => 7,
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
        ]);

        // Verification 2: PO is NOT a zero cost replacement
        $this->assertFalse((bool) $po->is_zero_cost_replacement);
        $this->assertEmpty($po->items); // It did NOT force-populate zero-cost dummy items

        // 3. Test item index controller endpoint supplies suggested_unit_price
        $response = $this->actingAs($procUser)->get(route('procurement.purchase-orders.items.index', $po->purchase_order_id));
        $response->assertOk();
        $sourceItems = $response->viewData('page')['props']['sourceItems'];
        $this->assertNotEmpty($sourceItems);
        $this->assertEquals(450.00, (float) $sourceItems[0]['suggested_unit_price']);

        // 4. Test storing the item with unit price
        $storeResponse = $this->actingAs($procUser)->post(
            route('procurement.purchase-orders.items.store', $po->purchase_order_id),
            [
                'supply_request_item_id' => $followUpSrItem->supply_request_item_id,
                'item_id' => $catalogItem->item_id,
                'item_name_snapshot' => 'Printer Ink, Black',
                'specification' => 'Brand genuine black ink',
                'quantity_ordered' => 5,
                'unit' => 'box',
                'unit_price' => 450.00,
            ]
        );
        $storeResponse->assertRedirect();

        $po->refresh();
        $this->assertSame('2250.00', (string) $po->total_amount);
        $this->assertFalse((bool) $po->is_zero_cost_replacement);
    }

    public function test_migration_fixes_existing_zero_cost_claim_replacement_purchase_orders(): void
    {
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $roleProc = Role::query()->firstOrCreate(['code' => UserRole::PROCUREMENT_OFFICER->value], ['name' => 'Procurement Officer']);

        $office = Office::query()->create(['name' => 'Health Office', 'code' => 'MHO', 'status' => 'ACTIVE']);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $procUser = User::factory()->create(['role_id' => $roleProc->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'MedSupply', 'status' => 'ACTIVE']);

        $category = \App\Models\ItemCategory::query()->create(['category_name' => 'Medical Supplies']);
        $item = \App\Models\Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_name' => 'Digital Thermometer',
            'default_unit' => 'piece',
            'status' => 'active',
        ]);

        $ppmp = Ppmp::query()->create([
            'office_id' => $office->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $officeUser->id,
            'date_prepared' => now()->toDateString(),
            'total_estimated_budget' => 3000.00,
        ]);

        $ppmpItem = PpmpItem::query()->create([
            'ppmp_id' => $ppmp->ppmp_id,
            'item_id' => $item->item_id,
            'item_category_id' => $category->item_category_id,
            'quantity' => 10,
            'unit_cost' => 300.00,
            'estimated_budget' => 3000.00,
            'procurement_schedule' => 'Q1',
            'fund_source' => 'General Fund',
            'remarks' => 'Infrared non-contact',
        ]);

        $sr = SupplyRequest::query()->create([
            'request_code' => 'SR-MHO-2026-009',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'MEDICAL',
            'purpose' => 'Clinic checkups',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'ppmp_id' => $ppmp->ppmp_id,
        ]);

        $srItem = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'ppmp_item_id' => $ppmpItem->ppmp_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Digital Thermometer',
            'item_specification' => 'Infrared non-contact',
            'quantity_requested' => 10,
        ]);

        $claimPr = PurchaseRequest::query()->create([
            'supply_request_id' => $sr->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $procUser->id,
            'status' => 'PROCESSING',
            'source_type' => 'CLAIM_REPLACEMENT_FOLLOW_UP',
            'source_claim_id' => null,
        ]);

        // Old buggy zero-cost PO as seen in the user's screenshot
        $oldPo = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-9999',
            'purchase_request_id' => $claimPr->purchase_request_id,
            'supply_request_id' => $sr->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $procUser->id,
            'status' => 'PLACED',
            'total_amount' => 0.00,
            'procurement_method' => 'SHOPPING',
            'terms_notes' => 'Zero-cost supplier warranty rectification under RA 9184.',
        ]);

        $poItem = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $oldPo->purchase_order_id,
            'supply_request_item_id' => $srItem->supply_request_item_id,
            'item_id' => $item->item_id,
            'item_name_snapshot' => 'Digital Thermometer',
            'specification' => 'Replacement item under supplier warranty rectification',
            'quantity_ordered' => 10,
            'unit' => 'piece',
            'unit_price' => 0.00,
            'total_price' => 0.00,
        ]);

        // Run the repair migration
        $migration = require database_path('migrations/2026_09_28_000001_fix_claim_replacement_purchase_orders_zero_cost.php');
        $migration->up();

        $oldPo->refresh();
        $poItem->refresh();

        $this->assertEquals(300.00, (float) $poItem->unit_price);
        $this->assertEquals(3000.00, (float) $poItem->total_price);
        $this->assertSame('Infrared non-contact', $poItem->specification);
        $this->assertEquals(3000.00, (float) $oldPo->total_amount);
        $this->assertNull($oldPo->terms_notes);
        $this->assertFalse((bool) $oldPo->is_zero_cost_replacement);
    }
}
