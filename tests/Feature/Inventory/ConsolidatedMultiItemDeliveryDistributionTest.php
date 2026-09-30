<?php

namespace Tests\Feature\Inventory;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemCategory;
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
use App\Services\Inventory\DistributionRecordService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedMultiItemDeliveryDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_multi_sr_deliveries_never_vanish_and_all_reach_100_percent(): void
    {
        $this->withoutVite();

        $roleGso = Role::query()->firstOrCreate(['code' => UserRole::GSO_OFFICER->value], ['name' => 'GSO Officer']);
        $roleOffice = Role::query()->firstOrCreate(['code' => UserRole::OFFICE_USER->value], ['name' => 'Office User']);

        $gsoOffice = Office::query()->create(['code' => 'GSO', 'name' => 'General Services Office', 'is_active' => true]);
        $mpdoOffice = Office::query()->create(['code' => 'MPDO', 'name' => 'Municipal Planning and Development Office', 'is_active' => true]);

        $gsoOfficer = User::factory()->create(['office_id' => $gsoOffice->id, 'role_id' => $roleGso->id]);
        $receiver = User::factory()->create(['office_id' => $mpdoOffice->id, 'role_id' => $roleOffice->id]);

        $category = ItemCategory::query()->create(['category_name' => 'Supplies', 'code' => 'SUP', 'status' => 'ACTIVE']);
        $supplier = Supplier::query()->create(['supplier_name' => 'Mega Supplier', 'status' => 'ACTIVE']);

        // Create 6 distinct items
        $paperA4 = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'PAPER-A4', 'item_name' => 'Bond Paper, A4 (80 GSM)', 'default_unit' => 'box', 'status' => 'ACTIVE']);
        $paperLegal = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'PAPER-LEGAL', 'item_name' => 'Bond Paper, Legal (80 GSM)', 'default_unit' => 'box', 'status' => 'ACTIVE']);
        $penBlack = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'PEN-BLK', 'item_name' => 'Ballpen, Black', 'default_unit' => 'box', 'status' => 'ACTIVE']);
        $penBlue = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'PEN-BLU', 'item_name' => 'Ballpen, Blue', 'default_unit' => 'box', 'status' => 'ACTIVE']);
        $folderLegal = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'FOLD-LEG', 'item_name' => 'Folder, Tagboard (Legal size)', 'default_unit' => 'pack', 'status' => 'ACTIVE']);
        $folderA4 = Item::query()->create(['item_category_id' => $category->item_category_id, 'item_code' => 'FOLD-A4', 'item_name' => 'Folder, Tagboard (A4 size)', 'default_unit' => 'pack', 'status' => 'ACTIVE']);

        foreach ([$paperA4, $paperLegal, $penBlack, $penBlue, $folderLegal, $folderA4] as $it) {
            Inventory::query()->create([
                'item_id' => $it->item_id,
                'stock_on_hand' => 0,
                'available_stock' => 0,
                'reserved_quantity' => 0,
            ]);
        }

        $ppmp = Ppmp::query()->create([
            'office_id' => $mpdoOffice->id,
            'fiscal_year' => 2026,
            'status' => 'APPROVED',
            'prepared_by' => $receiver->id,
            'date_prepared' => now()->toDateString(),
        ]);
        $ppmpA4 = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $paperA4->item_id, 'item_category_id' => $paperA4->item_category_id, 'quantity' => 500, 'unit' => 'box', 'unit_cost' => 100, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);
        $ppmpLegal = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $paperLegal->item_id, 'item_category_id' => $paperLegal->item_category_id, 'quantity' => 500, 'unit' => 'box', 'unit_cost' => 100, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);
        $ppmpBlack = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $penBlack->item_id, 'item_category_id' => $penBlack->item_category_id, 'quantity' => 500, 'unit' => 'box', 'unit_cost' => 50, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);
        $ppmpBlue = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $penBlue->item_id, 'item_category_id' => $penBlue->item_category_id, 'quantity' => 500, 'unit' => 'box', 'unit_cost' => 50, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);
        $ppmpFoldLeg = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $folderLegal->item_id, 'item_category_id' => $folderLegal->item_category_id, 'quantity' => 500, 'unit' => 'pack', 'unit_cost' => 40, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);
        $ppmpFoldA4 = PpmpItem::query()->create(['ppmp_id' => $ppmp->ppmp_id, 'item_id' => $folderA4->item_id, 'item_category_id' => $folderA4->item_category_id, 'quantity' => 500, 'unit' => 'pack', 'unit_cost' => 40, 'estimated_budget' => 50000, 'procurement_schedule' => 'Q1']);

        // 1. SR-7: Paper only (50 A4, 50 Legal)
        $sr7 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0007',
            'office_id' => $mpdoOffice->id,
            'requested_by' => $receiver->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office paper needs',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sriPaperA4 = SupplyRequestItem::query()->create(['supply_request_id' => $sr7->supply_request_id, 'item_id' => $paperA4->item_id, 'ppmp_item_id' => $ppmpA4->ppmp_item_id, 'item_name_snapshot' => $paperA4->item_name, 'quantity_requested' => 50]);
        $sriPaperLegal = SupplyRequestItem::query()->create(['supply_request_id' => $sr7->supply_request_id, 'item_id' => $paperLegal->item_id, 'ppmp_item_id' => $ppmpLegal->ppmp_item_id, 'item_name_snapshot' => $paperLegal->item_name, 'quantity_requested' => 50]);

        // 2. SR-8: Pens only (50 Black, 50 Blue)
        $sr8 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0008',
            'office_id' => $mpdoOffice->id,
            'requested_by' => $receiver->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office pens needs',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sriPenBlack = SupplyRequestItem::query()->create(['supply_request_id' => $sr8->supply_request_id, 'item_id' => $penBlack->item_id, 'ppmp_item_id' => $ppmpBlack->ppmp_item_id, 'item_name_snapshot' => $penBlack->item_name, 'quantity_requested' => 50]);
        $sriPenBlue = SupplyRequestItem::query()->create(['supply_request_id' => $sr8->supply_request_id, 'item_id' => $penBlue->item_id, 'ppmp_item_id' => $ppmpBlue->ppmp_item_id, 'item_name_snapshot' => $penBlue->item_name, 'quantity_requested' => 50]);

        // 3. SR-9: Folders only (20 Legal, 25 A4)
        $sr9 = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0009',
            'office_id' => $mpdoOffice->id,
            'requested_by' => $receiver->id,
            'ppmp_id' => $ppmp->ppmp_id,
            'status' => 'APPROVED',
            'priority_level' => 'NORMAL',
            'request_category' => 'SUPPLIES',
            'purpose' => 'Office folders needs',
            'request_date' => now()->toDateString(),
            'needed_by_date' => now()->addDays(5)->toDateString(),
        ]);
        $sriFolderLegal = SupplyRequestItem::query()->create(['supply_request_id' => $sr9->supply_request_id, 'item_id' => $folderLegal->item_id, 'ppmp_item_id' => $ppmpFoldLeg->ppmp_item_id, 'item_name_snapshot' => $folderLegal->item_name, 'quantity_requested' => 20]);
        $sriFolderA4 = SupplyRequestItem::query()->create(['supply_request_id' => $sr9->supply_request_id, 'item_id' => $folderA4->item_id, 'ppmp_item_id' => $ppmpFoldA4->ppmp_item_id, 'item_name_snapshot' => $folderA4->item_name, 'quantity_requested' => 25]);

        // Consolidated PR #108
        $pr = PurchaseRequest::query()->create([
            'office_id' => $mpdoOffice->id,
            'supply_request_id' => $sr7->supply_request_id,
            'requested_by' => $gsoOfficer->id,
            'pr_number' => 'PR-2026-09-0108',
            'status' => 'APPROVED',
            'remarks' => sprintf('Consolidated procurement for requests %s, %s, %s', $sr7->request_code, $sr8->request_code, $sr9->request_code),
        ]);

        $priPaperA4 = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $paperA4->item_id, 'item_name_snapshot' => $paperA4->item_name, 'quantity_requested' => 50, 'unit' => 'box', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 5000]);
        $priPaperLegal = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $paperLegal->item_id, 'item_name_snapshot' => $paperLegal->item_name, 'quantity_requested' => 50, 'unit' => 'box', 'estimated_unit_cost' => 100, 'estimated_total_cost' => 5000]);
        $priPenBlack = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $penBlack->item_id, 'item_name_snapshot' => $penBlack->item_name, 'quantity_requested' => 50, 'unit' => 'box', 'estimated_unit_cost' => 50, 'estimated_total_cost' => 2500]);
        $priPenBlue = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $penBlue->item_id, 'item_name_snapshot' => $penBlue->item_name, 'quantity_requested' => 50, 'unit' => 'box', 'estimated_unit_cost' => 50, 'estimated_total_cost' => 2500]);
        $priFolderLegal = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $folderLegal->item_id, 'item_name_snapshot' => $folderLegal->item_name, 'quantity_requested' => 20, 'unit' => 'pack', 'estimated_unit_cost' => 40, 'estimated_total_cost' => 800]);
        $priFolderA4 = PurchaseRequestItem::query()->create(['purchase_request_id' => $pr->purchase_request_id, 'item_id' => $folderA4->item_id, 'item_name_snapshot' => $folderA4->item_name, 'quantity_requested' => 25, 'unit' => 'pack', 'estimated_unit_cost' => 40, 'estimated_total_cost' => 1000]);

        $po = PurchaseOrder::query()->create([
            'purchase_request_id' => $pr->purchase_request_id,
            'supply_request_id' => $sr7->supply_request_id,
            'po_number' => 'PO-2026-09-0108',
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoOfficer->id,
            'procurement_method' => 'PUBLIC_BIDDING',
            'status' => 'PLACED',
            'total_amount' => 16800,
        ]);
        $poiPaperA4 = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $paperA4->item_id, 'supply_request_item_id' => $sriPaperA4->supply_request_item_id, 'item_name_snapshot' => $paperA4->item_name, 'quantity_ordered' => 50, 'unit' => 'box', 'unit_price' => 100, 'total_price' => 5000]);
        $poiPaperLegal = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $paperLegal->item_id, 'supply_request_item_id' => $sriPaperLegal->supply_request_item_id, 'item_name_snapshot' => $paperLegal->item_name, 'quantity_ordered' => 50, 'unit' => 'box', 'unit_price' => 100, 'total_price' => 5000]);
        $poiPenBlack = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $penBlack->item_id, 'supply_request_item_id' => $sriPenBlack->supply_request_item_id, 'item_name_snapshot' => $penBlack->item_name, 'quantity_ordered' => 50, 'unit' => 'box', 'unit_price' => 50, 'total_price' => 2500]);
        $poiPenBlue = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $penBlue->item_id, 'supply_request_item_id' => $sriPenBlue->supply_request_item_id, 'item_name_snapshot' => $penBlue->item_name, 'quantity_ordered' => 50, 'unit' => 'box', 'unit_price' => 50, 'total_price' => 2500]);
        $poiFolderLegal = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $folderLegal->item_id, 'supply_request_item_id' => $sriFolderLegal->supply_request_item_id, 'item_name_snapshot' => $folderLegal->item_name, 'quantity_ordered' => 20, 'unit' => 'pack', 'unit_price' => 40, 'total_price' => 800]);
        $poiFolderA4 = PurchaseOrderItem::query()->create(['purchase_order_id' => $po->purchase_order_id, 'item_id' => $folderA4->item_id, 'supply_request_item_id' => $sriFolderA4->supply_request_item_id, 'item_name_snapshot' => $folderA4->item_name, 'quantity_ordered' => 25, 'unit' => 'pack', 'unit_price' => 40, 'total_price' => 1000]);

        // Delivery 1: Partial delivery
        $del1 = Delivery::query()->create([
            'purchase_order_id' => $po->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'delivery_code' => 'DEL-2026-09-0004',
            'receipt_status' => 'PARTIALLY_RECEIVED',
            'status' => 'VERIFIED',
            'received_by' => $gsoOfficer->id,
            'actual_delivery_date' => now()->toDateString(),
        ]);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiPaperA4->purchase_order_item_id, 'expected_quantity' => 50, 'received_quantity' => 10, 'accepted_quantity' => 10, 'missing_quantity' => 40, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiPaperLegal->purchase_order_item_id, 'expected_quantity' => 50, 'received_quantity' => 10, 'accepted_quantity' => 10, 'missing_quantity' => 40, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiPenBlack->purchase_order_item_id, 'expected_quantity' => 50, 'received_quantity' => 5, 'accepted_quantity' => 5, 'missing_quantity' => 45, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiPenBlue->purchase_order_item_id, 'expected_quantity' => 50, 'received_quantity' => 10, 'accepted_quantity' => 10, 'missing_quantity' => 40, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiFolderLegal->purchase_order_item_id, 'expected_quantity' => 20, 'received_quantity' => 10, 'accepted_quantity' => 10, 'missing_quantity' => 10, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);
        DeliveryItem::query()->create(['delivery_id' => $del1->delivery_id, 'purchase_order_item_id' => $poiFolderA4->purchase_order_item_id, 'expected_quantity' => 25, 'received_quantity' => 10, 'accepted_quantity' => 10, 'missing_quantity' => 15, 'damaged_quantity' => 0, 'item_status' => 'VERIFIED']);

        Inventory::query()->where('item_id', $paperA4->item_id)->update(['stock_on_hand' => 10, 'available_stock' => 10]);
        Inventory::query()->where('item_id', $paperLegal->item_id)->update(['stock_on_hand' => 10, 'available_stock' => 10]);
        Inventory::query()->where('item_id', $penBlack->item_id)->update(['stock_on_hand' => 5, 'available_stock' => 5]);
        Inventory::query()->where('item_id', $penBlue->item_id)->update(['stock_on_hand' => 10, 'available_stock' => 10]);
        Inventory::query()->where('item_id', $folderLegal->item_id)->update(['stock_on_hand' => 10, 'available_stock' => 10]);
        Inventory::query()->where('item_id', $folderA4->item_id)->update(['stock_on_hand' => 10, 'available_stock' => 10]);

        $service = app(DistributionRecordService::class);

        // Step A: Check GSO release rows from Delivery 1
        $this->actingAs($gsoOfficer);
        $response = $this->get(route('inventory.distribution-records.create'));
        $response->assertOk();
        $releaseRows = collect($response->viewData('page')['props']['eligibleRows']['data'] ?? []);

        // Assert all 3 SRs are initially available
        $this->assertTrue($releaseRows->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr7->supply_request_id), 'SR-7 must be releasable');
        $this->assertTrue($releaseRows->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr8->supply_request_id), 'SR-8 must be releasable');
        $this->assertTrue($releaseRows->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr9->supply_request_id), 'SR-9 must be releasable');

        // Step B: Release SR-8 (Pens: 5 Black, 10 Blue)
        $dist8 = $service->createRecordWithAutomaticRelease($gsoOfficer, [
            'supply_request_id' => (int) $sr8->supply_request_id,
            'office_id' => (int) $sr8->office_id,
            'delivery_id' => (int) $del1->delivery_id,
            'release_date' => now()->toDateString(),
        ]);
        $this->actingAs($receiver)->post(route('office.distribution-records.receive', $dist8->distribution_record_id));

        // Check create page again: SR-7 and SR-9 MUST STILL BE RELEASABLE! They must NOT vanish!
        $this->actingAs($gsoOfficer);
        $response2 = $this->get(route('inventory.distribution-records.create'));
        $releaseRows2 = collect($response2->viewData('page')['props']['eligibleRows']['data'] ?? []);
        $this->assertTrue($releaseRows2->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr7->supply_request_id), 'SR-7 must NOT vanish after SR-8 released!');
        $this->assertTrue($releaseRows2->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr9->supply_request_id), 'SR-9 must NOT vanish after SR-8 released!');

        // Step C: Release SR-9 (Folders: 10 Legal, 10 A4)
        $dist9 = $service->createRecordWithAutomaticRelease($gsoOfficer, [
            'supply_request_id' => (int) $sr9->supply_request_id,
            'office_id' => (int) $sr9->office_id,
            'delivery_id' => (int) $del1->delivery_id,
            'release_date' => now()->toDateString(),
        ]);
        $this->actingAs($receiver)->post(route('office.distribution-records.receive', $dist9->distribution_record_id));

        // Check create page again: SR-7 MUST STILL BE RELEASABLE! It must NOT vanish!
        $this->actingAs($gsoOfficer);
        $response3 = $this->get(route('inventory.distribution-records.create'));
        $releaseRows3 = collect($response3->viewData('page')['props']['eligibleRows']['data'] ?? []);
        $this->assertTrue($releaseRows3->contains(fn ($r) => (int) $r['supply_request_id'] === (int) $sr7->supply_request_id), 'SR-7 must NOT vanish after SR-8 and SR-9 released!');

        // Step D: Release SR-7 (Paper: 10 A4, 10 Legal)
        $dist7 = $service->createRecordWithAutomaticRelease($gsoOfficer, [
            'supply_request_id' => (int) $sr7->supply_request_id,
            'office_id' => (int) $sr7->office_id,
            'delivery_id' => (int) $del1->delivery_id,
            'release_date' => now()->toDateString(),
        ]);
        $this->actingAs($receiver)->post(route('office.distribution-records.receive', $dist7->distribution_record_id));

        // Check create page again: Now Delivery 1 is completely distributed for all 3 SRs!
        $this->actingAs($gsoOfficer);
        $response4 = $this->get(route('inventory.distribution-records.create'));
        $releaseRows4 = collect($response4->viewData('page')['props']['eligibleRows']['data'] ?? []);
        $this->assertFalse($releaseRows4->contains(fn ($r) => (int) ($r['delivery_id'] ?? 0) === (int) $del1->delivery_id), 'Delivery 1 should no longer have unreleased stock');

        // Step E: Create Follow-Up PR from Delivery 1 discrepancies
        $prService = app(PurchaseRequestService::class);
        $followUpPr = $prService->createFromPartialDelivery($del1, $gsoOfficer, 'Follow-up for Del 1 discrepancies');
        $childSr = $followUpPr->supplyRequest;
        $this->assertNotNull($childSr, 'Follow-up PR must have a child supply request');

        // Follow-up PO
        $followUpPo = PurchaseOrder::query()->create([
            'purchase_request_id' => $followUpPr->purchase_request_id,
            'supply_request_id' => $childSr->supply_request_id,
            'po_number' => 'PO-2026-09-0109',
            'supplier_id' => $supplier->supplier_id,
            'created_by' => $gsoOfficer->id,
            'procurement_method' => 'DIRECT_CONTRACTING',
            'status' => 'PLACED',
            'total_amount' => 10000,
        ]);
        foreach ($followUpPr->items as $fItem) {
            $childSri = $childSr->items->firstWhere('item_id', $fItem->item_id);
            $realItem = Item::find($fItem->item_id);
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $followUpPo->purchase_order_id,
                'item_id' => $fItem->item_id,
                'supply_request_item_id' => $childSri?->supply_request_item_id,
                'item_name_snapshot' => $fItem->item_name_snapshot ?: ($realItem?->item_name ?? 'Item'),
                'quantity_ordered' => $fItem->quantity_requested,
                'unit' => $fItem->unit ?: ($realItem?->default_unit ?? 'box'),
                'unit_price' => 100,
                'total_price' => 100 * (float) $fItem->quantity_requested,
                'purchase_request_item_id' => $fItem->purchase_request_item_id,
            ]);
        }

        // Delivery 2: 100% complete of follow-up
        $del2 = Delivery::query()->create([
            'purchase_order_id' => $followUpPo->purchase_order_id,
            'supplier_id' => $supplier->supplier_id,
            'delivery_code' => 'DEL-2026-09-0005',
            'receipt_status' => 'VERIFIED',
            'status' => 'VERIFIED',
            'received_by' => $gsoOfficer->id,
            'actual_delivery_date' => now()->toDateString(),
        ]);
        foreach ($followUpPo->items as $poi) {
            DeliveryItem::query()->create([
                'delivery_id' => $del2->delivery_id,
                'purchase_order_item_id' => $poi->purchase_order_item_id,
                'expected_quantity' => $poi->quantity_ordered,
                'received_quantity' => $poi->quantity_ordered,
                'accepted_quantity' => $poi->quantity_ordered,
                'missing_quantity' => 0,
                'damaged_quantity' => 0,
                'item_status' => 'VERIFIED',
            ]);
        }
        foreach ($followUpPo->items as $poi) {
            $inv = Inventory::query()->where('item_id', $poi->item_id)->first();
            $inv->increment('stock_on_hand', (float) $poi->quantity_ordered);
            $inv->increment('available_stock', (float) $poi->quantity_ordered);
        }

        // Step F: Release Delivery 2 to child follow-up SR
        $distFollowUp = $service->createRecordWithAutomaticRelease($gsoOfficer, [
            'supply_request_id' => (int) $childSr->supply_request_id,
            'office_id' => (int) $childSr->office_id,
            'delivery_id' => (int) $del2->delivery_id,
            'release_date' => now()->toDateString(),
        ]);
        $this->actingAs($receiver)->post(route('office.distribution-records.receive', $distFollowUp->distribution_record_id));

        // Step G: Refresh and assert that ALL supply requests reached 100% and RECEIVED status!
        $sr7->refresh();
        $sr8->refresh();
        $sr9->refresh();
        $childSr->refresh();

        $this->assertSame('RECEIVED', (string) $sr7->status, 'SR-7 must be RECEIVED');
        $this->assertSame('RECEIVED', (string) $sr8->status, 'SR-8 must be RECEIVED');
        $this->assertSame('RECEIVED', (string) $sr9->status, 'SR-9 must be RECEIVED');
        $this->assertSame('RECEIVED', (string) $childSr->status, 'Child follow-up SR must be RECEIVED');
    }
}
