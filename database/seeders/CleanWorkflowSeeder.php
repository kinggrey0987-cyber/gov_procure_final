<?php

namespace Database\Seeders;

use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CleanWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding clean, realistic operational workflow data for GovSupply Connect...');

        // 1. Resolve key actors
        $execUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))->first()
            ?? User::query()->firstOrFail();
        $gsoUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))->first()
            ?? $execUser;
        $procUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PROC_OFFICER'))->first()
            ?? $execUser;

        // Resolve offices
        $mpdo = Office::query()->where('code', 'MPDO')->first();
        $mswdo = Office::query()->where('code', 'MSWDO')->first();
        $eng = Office::query()->where('code', 'ENG')->first();
        $mho = Office::query()->where('code', 'MHO')->first();
        $gsoOffice = Office::query()->where('code', 'GSO')->first();

        // Office requisitioners
        $mpdoUser = ($mpdo ? User::query()->where('office_id', $mpdo->id)->first() : null) ?? $execUser;
        $mswdoUser = ($mswdo ? User::query()->where('office_id', $mswdo->id)->first() : null) ?? $execUser;
        $engUser = ($eng ? User::query()->where('office_id', $eng->id)->first() : null) ?? $execUser;
        $mhoUser = ($mho ? User::query()->where('office_id', $mho->id)->first() : null) ?? $execUser;

        // Resolve supplier
        $supplier = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Metro Office Depot Trading'],
            [
                'contact_person' => 'Mina Solis',
                'email' => 'orders@metroofficedepot.example',
                'phone' => '09171234567',
                'address' => 'Poblacion District, E.B. Magalona, Negros Occidental',
                'status' => 'ACTIVE',
            ]
        );

        $items = Item::query()->with('category')->get()->keyBy('item_name');
        if ($items->isEmpty()) {
            $this->command?->error('No items found in items table. Please run ItemCatalogSeeder first.');
            return;
        }

        // =========================================================================
        // 2. Central Warehouse Baseline Inventory
        // =========================================================================
        $this->command?->info('1/5 Initializing GSO Central Warehouse inventory baseline...');

        // Realistic stock profiles:
        // Stock-ready items (plentiful for direct distribution):
        // Shortage items (low stock so office requests trigger shortage & consolidation):
        $stockProfiles = [
            'Ballpen'              => ['on_hand' => 45.0,  'available' => 45.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Folder'               => ['on_hand' => 30.0,  'available' => 30.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Staple Wire'          => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Rubbing Alcohol'      => ['on_hand' => 20.0,  'available' => 20.0,  'threshold' => 6.0,  'reorder' => 10.0],
            'Detergent Powder'     => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Trash Bag'            => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 8.0,  'reorder' => 12.0],
            'Safety Gloves'        => ['on_hand' => 12.0,  'available' => 12.0,  'threshold' => 4.0,  'reorder' => 6.0],
            'Utility Cutter'       => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Logbook'              => ['on_hand' => 10.0,  'available' => 10.0,  'threshold' => 4.0,  'reorder' => 6.0],
            // Low stock / shortages:
            'Bond Paper'           => ['on_hand' => 4.0,   'available' => 4.0,   'threshold' => 20.0, 'reorder' => 25.0],
            'Printer Ink'          => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 10.0, 'reorder' => 12.0],
            'Toner Cartridge'      => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0,  'reorder' => 6.0],
            'Flash Drive'          => ['on_hand' => 3.0,   'available' => 3.0,   'threshold' => 8.0,  'reorder' => 10.0],
            'Mouse'                => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 6.0,  'reorder' => 8.0],
            'HP 680 Tri-Color Ink' => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0,  'reorder' => 6.0],
        ];

        foreach ($items as $name => $item) {
            $profile = $stockProfiles[$name] ?? ['on_hand' => 10.0, 'available' => 10.0, 'threshold' => 5.0, 'reorder' => 8.0];
            Inventory::query()->updateOrCreate(
                ['item_id' => $item->item_id],
                [
                    'stock_on_hand' => $profile['on_hand'],
                    'available_stock' => $profile['available'],
                    'reserved_quantity' => 0.0,
                    'released_quantity' => 0.0,
                    'low_stock_threshold' => $profile['threshold'],
                    'last_updated' => now(),
                ]
            );
        }

        // =========================================================================
        // 2. Historical Multi-Year Workflows (FY 2024 & FY 2025 - Closed Cycles)
        // =========================================================================
        $this->command?->info('2/6 Seeding completed multi-year historical procurement workflows (FY 2024 & FY 2025)...');
        $this->call(MultiYearHistoricalProcurementSeeder::class);

        // =========================================================================
        // 3. Approved FY 2026 PPMPs for Operating Offices
        // =========================================================================
        $this->command?->info('3/6 Creating approved FY 2026 PPMPs for offices...');

        $ppmpOfficeConfigs = [
            ['office' => $mpdo, 'user' => $mpdoUser, 'title' => 'Planning & Spatial Data Consumables', 'budget' => 225000.00],
            ['office' => $mswdo, 'user' => $mswdoUser, 'title' => 'Social Services & Community Support Supplies', 'budget' => 310000.00],
            ['office' => $eng, 'user' => $engUser, 'title' => 'Engineering Infrastructure Project Documents & Materials', 'budget' => 260000.00],
            ['office' => $mho, 'user' => $mhoUser, 'title' => 'Health Office Record-Keeping & Clinical Consumables', 'budget' => 195000.00],
        ];

        $ppmps = [];
        $ppmpItemsByOffice = [];

        foreach ($ppmpOfficeConfigs as $cfg) {
            if (! $cfg['office']) continue;

            $ppmp = Ppmp::query()->create([
                'office_id' => $cfg['office']->id,
                'prepared_by' => $cfg['user']->id,
                'fiscal_year' => 2026,
                'fund_source' => 'APPROPRIATION_ORDINANCE',
                'total_estimated_budget' => $cfg['budget'],
                'status' => 'APPROVED',
                'date_prepared' => Carbon::parse('2026-01-05'),
                'date_approved' => Carbon::parse('2026-01-12'),
                'remarks' => sprintf('Approved FY 2026 PPMP for %s.', $cfg['office']->name),
            ]);
            $ppmps[$cfg['office']->code] = $ppmp;

            // Seed standard PPMP items with realistic unit costs & schedules
            $standardPpmpCatalog = [
                ['name' => 'Bond Paper', 'qty' => 120, 'cost' => 1250.00, 'sched' => 'Q1'],
                ['name' => 'Ballpen', 'qty' => 60, 'cost' => 450.00, 'sched' => 'Q1'],
                ['name' => 'Folder', 'qty' => 40, 'cost' => 380.00, 'sched' => 'Q2'],
                ['name' => 'Printer Ink', 'qty' => 24, 'cost' => 1850.00, 'sched' => 'Q1'],
                ['name' => 'Rubbing Alcohol', 'qty' => 30, 'cost' => 780.00, 'sched' => 'Q2'],
                ['name' => 'Flash Drive', 'qty' => 15, 'cost' => 650.00, 'sched' => 'Q3'],
                ['name' => 'Trash Bag', 'qty' => 40, 'cost' => 420.00, 'sched' => 'Q2'],
                ['name' => 'Staple Wire', 'qty' => 20, 'cost' => 180.00, 'sched' => 'Q1'],
            ];

            foreach ($standardPpmpCatalog as $pItem) {
                if (! isset($items[$pItem['name']])) continue;
                $catItem = $items[$pItem['name']];

                $createdPpmpItem = PpmpItem::query()->create([
                    'ppmp_id' => $ppmp->ppmp_id,
                    'item_id' => $catItem->item_id,
                    'item_category_id' => $catItem->item_category_id,
                    'quantity' => $pItem['qty'],
                    'unit_cost' => $pItem['cost'],
                    'estimated_budget' => round($pItem['qty'] * $pItem['cost'], 2),
                    'procurement_schedule' => $pItem['sched'],
                    'remarks' => sprintf('Annual allocation for %s', $pItem['name']),
                ]);

                $ppmpItemsByOffice[$cfg['office']->code][$pItem['name']] = $createdPpmpItem;
            }
        }

        // =========================================================================
        // 4. Realistic Active Supply Requests (Current Operational State)
        // =========================================================================
        $this->command?->info('3/5 Creating active supply request workflows...');

        // Scenario A: STOCK READY Supply Request (MSWDO) -> for immediate distribution testing
        if ($mswdo) {
            $srDistributionReady = SupplyRequest::query()->create([
                'request_code' => 'SR-MSWDO-202609-01',
                'office_id' => $mswdo->id,
                'requested_by' => $mswdoUser->id,
                'ppmp_id' => $ppmps['MSWDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-08'),
                'needed_by_date' => Carbon::parse('2026-09-15'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Social Case Records & Field Staff Administrative Supplies.',
                'priority_level' => 'NORMAL',
                'status' => 'SUBMITTED',
                'review_notes' => null,
            ]);

            $readyItems = [
                ['name' => 'Ballpen', 'qty' => 3.0],
                ['name' => 'Rubbing Alcohol', 'qty' => 2.0],
                ['name' => 'Trash Bag', 'qty' => 2.0],
            ];
            foreach ($readyItems as $r) {
                if (isset($items[$r['name']])) {
                    SupplyRequestItem::query()->create([
                        'supply_request_id' => $srDistributionReady->supply_request_id,
                        'item_id' => $items[$r['name']]->item_id,
                        'ppmp_item_id' => $ppmpItemsByOffice['MSWDO'][$r['name']]->ppmp_item_id ?? null,
                        'item_name_snapshot' => $r['name'],
                        'quantity_requested' => $r['qty'],
                    ]);
                }
            }
        }

        // Scenario B: SHORTAGE Supply Requests (Ready for GSO Consolidation)
        // Two requests from MPDO and MSWDO requesting items that exceed warehouse stock
        if ($mpdo) {
            $srShortage1 = SupplyRequest::query()->create([
                'request_code' => 'SR-MPDO-202609-02',
                'office_id' => $mpdo->id,
                'requested_by' => $mpdoUser->id,
                'ppmp_id' => $ppmps['MPDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-08'),
                'needed_by_date' => Carbon::parse('2026-09-18'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Comprehensive Land Use Plan documentation and map printing.',
                'priority_level' => 'NORMAL',
                'status' => 'SUBMITTED',
                'review_notes' => null,
            ]);

            $shortageItems1 = [
                ['name' => 'Bond Paper', 'qty' => 10.0], // Available: 4 -> Shortage: 6
                ['name' => 'Printer Ink', 'qty' => 4.0],  // Available: 2 -> Shortage: 2
            ];
            foreach ($shortageItems1 as $r) {
                if (isset($items[$r['name']])) {
                    SupplyRequestItem::query()->create([
                        'supply_request_id' => $srShortage1->supply_request_id,
                        'item_id' => $items[$r['name']]->item_id,
                        'ppmp_item_id' => $ppmpItemsByOffice['MPDO'][$r['name']]->ppmp_item_id ?? null,
                        'item_name_snapshot' => $r['name'],
                        'quantity_requested' => $r['qty'],
                    ]);
                }
            }
        }

        if ($mswdo) {
            $srShortage2 = SupplyRequest::query()->create([
                'request_code' => 'SR-MSWDO-202609-03',
                'office_id' => $mswdo->id,
                'requested_by' => $mswdoUser->id,
                'ppmp_id' => $ppmps['MSWDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-09'),
                'needed_by_date' => Carbon::parse('2026-09-19'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Quarterly Social Welfare intake forms and livelihood survey documents.',
                'priority_level' => 'NORMAL',
                'status' => 'SUBMITTED',
                'review_notes' => null,
            ]);

            $shortageItems2 = [
                ['name' => 'Bond Paper', 'qty' => 8.0], // Available: 4 -> Shortage: 4
                ['name' => 'Printer Ink', 'qty' => 3.0], // Available: 2 -> Shortage: 1
            ];
            foreach ($shortageItems2 as $r) {
                if (isset($items[$r['name']])) {
                    SupplyRequestItem::query()->create([
                        'supply_request_id' => $srShortage2->supply_request_id,
                        'item_id' => $items[$r['name']]->item_id,
                        'ppmp_item_id' => $ppmpItemsByOffice['MSWDO'][$r['name']]->ppmp_item_id ?? null,
                        'item_name_snapshot' => $r['name'],
                        'quantity_requested' => $r['qty'],
                    ]);
                }
            }
        }

        // =========================================================================
        // 5. Clean Consolidated PR Already Forwarded to BAC (in BAC Review Queue)
        // =========================================================================
        $this->command?->info('4/5 Creating clean consolidated PR for BAC Review queue...');

        if ($eng && $mpdo) {
            // Source request 1 (MPDO Q3 supplies)
            $srConsolidatedSource1 = SupplyRequest::query()->create([
                'request_code' => 'SR-MPDO-202609-01',
                'office_id' => $mpdo->id,
                'requested_by' => $mpdoUser->id,
                'ppmp_id' => $ppmps['MPDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-02'),
                'needed_by_date' => Carbon::parse('2026-09-12'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Q3 municipal geospatial and statistical quarterly reports.',
                'priority_level' => 'NORMAL',
                'status' => 'FOR_PROCUREMENT',
                'review_notes' => 'Supply request consolidated into PR-000001 for procurement.',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource1->supply_request_id,
                'item_id' => $items['Bond Paper']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Bond Paper']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Bond Paper',
                'quantity_requested' => 12.0,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource1->supply_request_id,
                'item_id' => $items['Flash Drive']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Flash Drive']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Flash Drive',
                'quantity_requested' => 3.0,
            ]);

            // Source request 2 (ENG engineering survey supplies)
            $srConsolidatedSource2 = SupplyRequest::query()->create([
                'request_code' => 'SR-ENG-202609-01',
                'office_id' => $eng->id,
                'requested_by' => $engUser->id,
                'ppmp_id' => $ppmps['ENG']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-03'),
                'needed_by_date' => Carbon::parse('2026-09-14'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Road network and drainage infrastructure project blueprints.',
                'priority_level' => 'NORMAL',
                'status' => 'FOR_PROCUREMENT',
                'review_notes' => 'Supply request consolidated into PR-000001 for procurement.',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource2->supply_request_id,
                'item_id' => $items['Bond Paper']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Bond Paper']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Bond Paper',
                'quantity_requested' => 8.0,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource2->supply_request_id,
                'item_id' => $items['Flash Drive']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Flash Drive']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Flash Drive',
                'quantity_requested' => 2.0,
            ]);

            // Exactly ONE Consolidated Purchase Request forwarded to BAC
            $consolidatedPr = PurchaseRequest::query()->create([
                'supply_request_id' => $srConsolidatedSource1->supply_request_id,
                'office_id' => $mpdo->id,
                'requested_by' => $gsoUser->id,
                'status' => 'OPEN',
                'remarks' => sprintf(
                    'Consolidated PR for %s from 2 supply request(s): %s, %s.',
                    $mpdo->name,
                    $srConsolidatedSource1->request_code,
                    $srConsolidatedSource2->request_code,
                ),
            ]);

            // Merged lines: 12 + 8 = 20 Bond Paper, 3 + 2 = 5 Flash Drive
            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $consolidatedPr->purchase_request_id,
                'item_id' => $items['Bond Paper']->item_id,
                'quantity_requested' => 20.0,
                'remarks' => 'Consolidated demand for Planning & Engineering.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $consolidatedPr->purchase_request_id,
                'item_id' => $items['Flash Drive']->item_id,
                'quantity_requested' => 5.0,
                'remarks' => 'Consolidated demand for Planning & Engineering.',
            ]);

            StatusHistory::query()->create([
                'reference_type' => PurchaseRequest::class,
                'reference_id' => $consolidatedPr->purchase_request_id,
                'old_status' => null,
                'new_status' => 'OPEN',
                'changed_by' => $gsoUser->id,
                'remarks' => 'Consolidated purchase request forwarded to BAC.',
                'changed_at' => Carbon::parse('2026-09-04 10:00:00'),
            ]);
        }

        // =========================================================================
        // 6. Active Purchase Order Placed & Completed Historical Procurement
        // =========================================================================
        $this->command?->info('5/5 Creating active PO and completed procurement baseline...');

        if ($mho) {
            // Requisition for MHO
            $srMho = SupplyRequest::query()->create([
                'request_code' => 'SR-MHO-202608-01',
                'office_id' => $mho->id,
                'requested_by' => $mhoUser->id,
                'ppmp_id' => $ppmps['MHO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-08-15'),
                'needed_by_date' => Carbon::parse('2026-08-25'),
                'request_category' => 'Medical & Cleaning Supplies',
                'purpose' => 'Rural Health Unit sanitization and patient records maintenance.',
                'priority_level' => 'NORMAL',
                'status' => 'FOR_PROCUREMENT',
                'review_notes' => 'Queued by GSO due to stock shortage.',
            ]);

            $sriAlcohol = SupplyRequestItem::query()->create([
                'supply_request_id' => $srMho->supply_request_id,
                'item_id' => $items['Rubbing Alcohol']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Rubbing Alcohol']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Rubbing Alcohol',
                'quantity_requested' => 15.0,
            ]);

            $sriDetergent = SupplyRequestItem::query()->create([
                'supply_request_id' => $srMho->supply_request_id,
                'item_id' => $items['Detergent Powder']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Detergent Powder']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Detergent Powder',
                'quantity_requested' => 10.0,
            ]);

            // Purchase Request
            $prMho = PurchaseRequest::query()->create([
                'supply_request_id' => $srMho->supply_request_id,
                'office_id' => $mho->id,
                'requested_by' => $gsoUser->id,
                'status' => 'PROCESSING',
                'remarks' => 'Purchase request approved by BAC and canvassed.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prMho->purchase_request_id,
                'item_id' => $items['Rubbing Alcohol']->item_id,
                'quantity_requested' => 15.0,
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prMho->purchase_request_id,
                'item_id' => $items['Detergent Powder']->item_id,
                'quantity_requested' => 10.0,
            ]);

            // Active Purchase Order (Placed with supplier)
            $poActive = PurchaseOrder::query()->create([
                'po_number' => 'PO-2026-09-001',
                'purchase_request_id' => $prMho->purchase_request_id,
                'supply_request_id' => $srMho->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $procUser->id,
                'approved_by' => $execUser->id,
                'date_approved' => Carbon::parse('2026-09-05'),
                'status' => 'PLACED',
                'total_amount' => 16200.00,
                'procurement_method' => 'SHOPPING',
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poActive->purchase_order_id,
                'supply_request_item_id' => $sriAlcohol->supply_request_item_id,
                'item_id' => $items['Rubbing Alcohol']->item_id,
                'item_name_snapshot' => 'Rubbing Alcohol',
                'unit' => $items['Rubbing Alcohol']->default_unit ?? 'box',
                'quantity_ordered' => 15.0,
                'unit_price' => 780.00,
                'total_price' => 11700.00,
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poActive->purchase_order_id,
                'supply_request_item_id' => $sriDetergent->supply_request_item_id,
                'item_id' => $items['Detergent Powder']->item_id,
                'item_name_snapshot' => 'Detergent Powder',
                'unit' => $items['Detergent Powder']->default_unit ?? 'box',
                'quantity_ordered' => 10.0,
                'unit_price' => 450.00,
                'total_price' => 4500.00,
            ]);
        }

        // Completed historical procurement (Q2) for Analytics, Timeline & Disclosures
        if ($mpdo) {
            $srHistorical = SupplyRequest::query()->create([
                'request_code' => 'SR-MPDO-202604-01',
                'office_id' => $mpdo->id,
                'requested_by' => $mpdoUser->id,
                'ppmp_id' => $ppmps['MPDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-04-10'),
                'needed_by_date' => Carbon::parse('2026-04-20'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Second quarter municipal planning stationery supplies.',
                'priority_level' => 'NORMAL',
                'status' => 'COMPLETED',
                'review_notes' => 'Completed and distributed.',
            ]);

            $sriFolder = SupplyRequestItem::query()->create([
                'supply_request_id' => $srHistorical->supply_request_id,
                'item_id' => $items['Folder']->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Folder']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Folder',
                'quantity_requested' => 10.0,
            ]);

            $prHistorical = PurchaseRequest::query()->create([
                'supply_request_id' => $srHistorical->supply_request_id,
                'office_id' => $mpdo->id,
                'requested_by' => $gsoUser->id,
                'status' => 'COMPLETED',
                'remarks' => 'Completed procurement cycle.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prHistorical->purchase_request_id,
                'item_id' => $items['Folder']->item_id,
                'quantity_requested' => 10.0,
            ]);

            $poHistorical = PurchaseOrder::query()->create([
                'po_number' => 'PO-2026-05-001',
                'purchase_request_id' => $prHistorical->purchase_request_id,
                'supply_request_id' => $srHistorical->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $procUser->id,
                'approved_by' => $execUser->id,
                'date_approved' => Carbon::parse('2026-05-02'),
                'status' => 'RECEIVED',
                'total_amount' => 3800.00,
                'procurement_method' => 'SHOPPING',
            ]);

            $poiHistorical = PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poHistorical->purchase_order_id,
                'supply_request_item_id' => $sriFolder->supply_request_item_id,
                'item_id' => $items['Folder']->item_id,
                'item_name_snapshot' => 'Folder',
                'unit' => $items['Folder']->default_unit ?? 'pack',
                'quantity_ordered' => 10.0,
                'unit_price' => 380.00,
                'total_price' => 3800.00,
            ]);

            // Delivery with realistic DR & Invoice tracking
            $deliveryHistorical = Delivery::query()->create([
                'purchase_order_id' => $poHistorical->purchase_order_id,
                'delivery_code' => 'DEL-2026-05-001',
                'supplier_id' => $supplier->supplier_id,
                'received_by' => $gsoUser->id,
                'actual_delivery_date' => Carbon::parse('2026-05-15'),
                'expected_arrival_date' => Carbon::parse('2026-05-15'),
                'delivery_receipt_no' => 'DR-2026-0588',
                'sales_invoice_no' => 'SI-2026-1249',
                'sales_invoice_date' => Carbon::parse('2026-05-15'),
                'sales_invoice_amount' => 3800.00,
                'receipt_status' => 'VERIFIED',
                'remarks' => 'Inspected and accepted in good order and condition.',
            ]);

            DeliveryItem::query()->create([
                'delivery_id' => $deliveryHistorical->delivery_id,
                'purchase_order_item_id' => $poiHistorical->purchase_order_item_id,
                'expected_quantity' => 10.0,
                'received_quantity' => 10.0,
                'damaged_quantity' => 0.0,
                'missing_quantity' => 0.0,
                'item_condition' => 'GOOD',
            ]);

            // Distribution to MPDO
            $distributionHistorical = DistributionRecord::query()->create([
                'distribution_code' => 'DIST-2026-05-001',
                'supply_request_id' => $srHistorical->supply_request_id,
                'delivery_id' => $deliveryHistorical->delivery_id,
                'office_id' => $mpdo->id,
                'prepared_by' => $gsoUser->id,
                'received_by' => $mpdoUser->id,
                'release_date' => Carbon::parse('2026-05-18'),
                'storage_location' => 'Main Warehouse',
                'status' => 'RECEIVED',
                'notes' => 'Supplies turned over to Planning Office.',
            ]);

            DistributionItem::query()->create([
                'distribution_record_id' => $distributionHistorical->distribution_record_id,
                'item_id' => $items['Folder']->item_id,
                'received_by' => $mpdoUser->id,
                'item_name_snapshot' => 'Folder',
                'quantity_released' => 10.0,
                'quantity_received' => 10.0,
                'receipt_condition' => 'GOOD',
                'unit' => 'pack',
            ]);
        }

        // Active Distribution ready for Office Acknowledgment Demo (ENG Office)
        if ($eng && isset($srConsolidatedSource2)) {
            $distActive = DistributionRecord::query()->create([
                'distribution_code' => 'DIST-2026-09-001',
                'supply_request_id' => $srConsolidatedSource2->supply_request_id,
                'office_id' => $eng->id,
                'prepared_by' => $gsoUser->id,
                'received_by' => $engUser->id,
                'release_date' => Carbon::parse('2026-09-08'),
                'storage_location' => 'GSO Requisition Bay 2',
                'status' => 'RELEASED',
                'notes' => 'Stocks prepared and staged for pickup by Engineering Office representative.',
            ]);

            DistributionItem::query()->create([
                'distribution_record_id' => $distActive->distribution_record_id,
                'item_id' => $items['Safety Gloves']->item_id,
                'received_by' => $engUser->id,
                'item_name_snapshot' => 'Safety Gloves',
                'quantity_released' => 5.0,
                'quantity_received' => 0.0,
                'receipt_condition' => 'GOOD',
                'unit' => $items['Safety Gloves']->default_unit ?? 'pair',
            ]);
        }

        // =========================================================================
        // 6. System Governance Ops (Backups and Yearly Archive)
        // =========================================================================
        $this->command?->info('6/6 Seeding system governance backups and yearly archive logs...');
        $this->call(GovernanceOpsDemoSeeder::class);

        $this->command?->info('Workflow data successfully populated with clean, realistic operational dataset spanning 2024, 2025, and 2026!');
    }
}
