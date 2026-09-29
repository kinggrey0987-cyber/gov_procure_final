<?php

namespace Tests\Feature\Procurement;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Office;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Inventory\SupplyRequestFollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairPrematurelyCompletedFollowUpProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_migration_repairs_corrupted_follow_up_linkage_and_reverts_premature_completed_statuses(): void
    {
        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);
        $office = Office::query()->create(['name' => 'Municipal Planning and Development Office', 'code' => 'MPDO', 'status' => 'ACTIVE']);
        $gsoUser = User::factory()->create(['role_id' => $roleGso->id, 'office_id' => $office->id]);
        $officeUser = User::factory()->create(['role_id' => $roleOffice->id, 'office_id' => $office->id]);
        $supplier = Supplier::query()->create(['supplier_name' => 'Government Paper Depot', 'status' => 'ACTIVE']);

        $category = ItemCategory::query()->create(['category_name' => 'Office Supplies', 'code' => 'OFF-SUP', 'status' => 'ACTIVE']);

        $itemA4 = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'A4-BOND',
            'item_name' => 'Bond Paper, A4 (80 GSM)',
            'default_unit' => 'ream',
            'status' => 'ACTIVE',
        ]);

        $itemLegal = Item::query()->create([
            'item_category_id' => $category->item_category_id,
            'item_code' => 'LEGAL-BOND',
            'item_name' => 'Bond Paper, Legal (80 GSM)',
            'default_unit' => 'ream',
            'status' => 'ACTIVE',
        ]);

        // Requisition 6 (200 A4, 200 Legal) - Received
        $sr6 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0006',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office paper batch 1',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sr6A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr6->supply_request_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 200,
        ]);
        $sr6Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr6->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 200,
        ]);

        // Requisition 7 (100 A4, 100 Legal) - Still stuck in FOR_PROCUREMENT / PROCUREMENT PLACED
        $sr7 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'FOR_PROCUREMENT',
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Office paper batch 2',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        $sr7A4 = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr7->supply_request_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 100,
        ]);
        $sr7Legal = SupplyRequestItem::query()->create([
            'supply_request_id' => $sr7->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 100,
        ]);

        // Consolidated PR-6 (falsely marked COMPLETED)
        $pr6 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0006',
            'supply_request_id' => $sr6->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'status' => 'COMPLETED', // Prematurely COMPLETED
            'remarks' => sprintf('Consolidated PR combining %s and %s.', $sr6->request_code, $sr7->request_code),
        ]);
        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr6->purchase_request_id,
            'item_id' => $itemA4->item_id,
            'quantity_requested' => 300,
        ]);
        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $pr6->purchase_request_id,
            'item_id' => $itemLegal->item_id,
            'quantity_requested' => 300,
        ]);

        // PO for PR-6 (falsely marked COMPLETED)
        $po = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0004',
            'purchase_request_id' => $pr6->purchase_request_id,
            'supply_request_id' => $sr6->supply_request_id,
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoUser->id,
            'status' => 'COMPLETED', // Prematurely COMPLETED
            'total_amount' => 60000,
            'procurement_method' => 'PUBLIC_BIDDING',
        ]);
        $poiA4 = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr6A4->supply_request_item_id,
            'source_request_item_ids' => [$sr6A4->supply_request_item_id, $sr7A4->supply_request_item_id],
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_ordered' => 300,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);
        $poiLegal = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supply_request_item_id' => $sr6Legal->supply_request_item_id,
            'source_request_item_ids' => [$sr6Legal->supply_request_item_id, $sr7Legal->supply_request_item_id],
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_ordered' => 300,
            'unit' => 'ream',
            'unit_price' => 100,
            'total_price' => 30000,
        ]);

        // Delivery 1 (200 accepted, 100 missing)
        $delivery = Delivery::query()->create([
            'delivery_code' => 'DEL-2026-09-0004',
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'received_by' => $gsoUser->id,
            'receipt_status' => 'PARTIALLY_RECEIVED',
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poiA4->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
        ]);
        DeliveryItem::query()->create([
            'delivery_id' => $delivery->delivery_id,
            'purchase_order_item_id' => $poiLegal->purchase_order_item_id,
            'expected_quantity' => 300,
            'received_quantity' => 200,
            'damaged_quantity' => 0,
            'missing_quantity' => 100,
        ]);

        // Distribution to SR-6
        $dr6 = DistributionRecord::query()->create([
            'distribution_code' => 'DR-2026-09-0006',
            'supply_request_id' => $sr6->supply_request_id,
            'delivery_id' => $delivery->delivery_id,
            'office_id' => $office->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $officeUser->id,
            'status' => 'RECEIVED',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr6->distribution_record_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'ream',
        ]);
        DistributionItem::query()->create([
            'distribution_record_id' => $dr6->distribution_record_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_released' => 200,
            'quantity_received' => 200,
            'unit' => 'ream',
        ]);

        // Corrupted Follow-up Requisition SR-8 (falsely linked to SR-6 and falsely marked RECEIVED)
        $sr8 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $office->id,
            'requested_by' => $officeUser->id,
            'status' => 'RECEIVED', // Prematurely RECEIVED!
            'request_category' => 'GENERAL',
            'priority_level' => 'NORMAL',
            'purpose' => 'Follow-up for parent requisition SR-MPDO-2026-09-0006',
            'review_notes' => 'Generated from discrepancy follow-up of delivery DEL-2026-09-0004 replenishing parent requisition SR-MPDO-2026-09-0006.',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(7)->toDateString(),
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr8->supply_request_id,
            'item_id' => $itemA4->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 100,
        ]);
        SupplyRequestItem::query()->create([
            'supply_request_id' => $sr8->supply_request_id,
            'item_id' => $itemLegal->item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 100,
        ]);

        // Corrupted Follow-up PR-7 (falsely linked to SR-6 and falsely marked COMPLETED)
        $pr7 = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0007',
            'supply_request_id' => $sr8->supply_request_id,
            'office_id' => $office->id,
            'requested_by' => $gsoUser->id,
            'source_delivery_id' => $delivery->delivery_id,
            'source_supply_request_id' => $sr6->supply_request_id, // Wrong parent!
            'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
            'status' => 'COMPLETED', // Prematurely COMPLETED!
            'remarks' => 'Follow-up procurement generated from delivery DEL-2026-09-0004 for SR-MPDO-2026-09-0006.',
        ]);

        // EXECUTE: Run the repair migration logic
        $migration = require database_path('migrations/2026_09_30_000001_repair_prematurely_completed_follow_up_procurement.php');
        $migration->up();

        // ASSERTIONS:
        // 1. Follow-up PR (PR-7) source_supply_request_id must be updated to SR-7!
        $this->assertEquals((int) $sr7->supply_request_id, (int) $pr7->fresh()->source_supply_request_id);

        // 2. PR-7 status must be reverted from COMPLETED to OPEN!
        $this->assertEquals('OPEN', (string) $pr7->fresh()->status);

        // 3. Child supply request (SR-8) status must be reverted from RECEIVED to SUBMITTED!
        $this->assertEquals('SUBMITTED', (string) $sr8->fresh()->status);

        // 4. Child SR-8 review notes must be updated to reference SR-7!
        $this->assertStringContainsString('SR-MPDO-2026-09-0007', (string) $sr8->fresh()->review_notes);

        // 5. Consolidated PR (PR-6) status must be reverted from COMPLETED to APPROVED!
        $this->assertEquals('APPROVED', (string) $pr6->fresh()->status);

        // 6. PO status must be reverted from COMPLETED to PLACED!
        $this->assertEquals('PLACED', (string) $po->fresh()->status);

        // 7. Context resolution: SR-7 must now show SR-8 as its follow-up child!
        $followUpService = app(SupplyRequestFollowUpService::class);
        $sr7Context = $followUpService->resolveContext($sr7->fresh());
        $this->assertEquals('PARENT_REQUISITION', $sr7Context['type']);
        $this->assertCount(1, $sr7Context['children']);
        $this->assertEquals((int) $sr8->supply_request_id, (int) $sr7Context['children'][0]['supply_request_id']);

        // 8. SR-6 context must NOT have follow-up children!
        $sr6Context = $followUpService->resolveContext($sr6->fresh());
        $this->assertEquals('STANDARD', $sr6Context['type']);
        $this->assertEmpty($sr6Context['children']);
    }
}
