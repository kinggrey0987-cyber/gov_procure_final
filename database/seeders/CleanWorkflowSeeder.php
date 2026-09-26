<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Anomaly;
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

        // Resolve offices defensively
        $mpdo = Office::query()->firstOrCreate(
            ['code' => 'MPDO'],
            ['name' => 'Municipal Planning and Development Office', 'description' => 'Planning and development office for municipal programs and PPMP ownership.']
        );
        $mswdo = Office::query()->firstOrCreate(
            ['code' => 'MSWDO'],
            ['name' => 'Municipal Social Welfare and Development Office', 'description' => 'Social welfare and development office for municipal services.']
        );
        $eng = Office::query()->firstOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Municipal Engineering Office', 'description' => 'Municipal infrastructure and engineering office.']
        );
        $mho = Office::query()->firstOrCreate(
            ['code' => 'MHO'],
            ['name' => 'Municipal Health Office', 'description' => 'Public healthcare and community medical services.']
        );
        $gsoOffice = Office::query()->firstOrCreate(
            ['code' => 'GSO'],
            ['name' => 'General Services Office', 'description' => 'Handles inventory, deliveries, and supply distribution.']
        );

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

        $items = Item::query()->with(['category', 'specifications'])->get()->keyBy('item_name');
        if ($items->isEmpty()) {
            $this->command?->error('No items found in items table. Please run ItemCatalogSeeder first.');
            return;
        }

        $resolveItem = function (string $lookup) use ($items): Item {
            if ($items->has($lookup)) {
                return $items->get($lookup);
            }
            $lower = strtolower(trim($lookup));
            foreach ($items as $name => $item) {
                $iLower = strtolower(trim($name));
                if ($iLower === $lower || str_starts_with($iLower, $lower) || str_starts_with($lower, $iLower)) {
                    return $item;
                }
            }
            foreach ($items as $item) {
                foreach ($item->specifications as $spec) {
                    $sLower = strtolower(trim($spec->specification));
                    if (str_contains($sLower, $lower) || str_contains($lower, $sLower)) {
                        return $item;
                    }
                }
            }
            return $items->first();
        };

        // =========================================================================
        // 2. Central Warehouse Baseline Inventory
        // =========================================================================
        $this->command?->info('1/5 Initializing GSO Central Warehouse inventory baseline...');

        // Realistic stock profiles for all 23 discrete physical items:
        $stockProfiles = [
            // Plentiful / Stock-Ready items:
            'Ballpen, Black'                => ['on_hand' => 45.0,  'available' => 45.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Ballpen, Blue'                 => ['on_hand' => 30.0,  'available' => 30.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Ballpen, Red'                  => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Folder, Tagboard (Legal size)'  => ['on_hand' => 30.0,  'available' => 30.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Folder, Tagboard (A4 size)'     => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 10.0, 'reorder' => 15.0],
            'Staple Wire'                   => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Heavy Duty Stapler'            => ['on_hand' => 8.0,   'available' => 8.0,   'threshold' => 2.0,  'reorder' => 4.0],
            'Trash Bag, Black XL'           => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 8.0,  'reorder' => 12.0],
            'Trash Bag, Yellow Biohazard XL' => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Safety Gloves'                 => ['on_hand' => 12.0,  'available' => 12.0,  'threshold' => 4.0,  'reorder' => 6.0],
            'Utility Cutter'                => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0,  'reorder' => 8.0],
            'Logbook'                       => ['on_hand' => 10.0,  'available' => 10.0,  'threshold' => 4.0,  'reorder' => 6.0],
            'Flash Drive, 32GB'             => ['on_hand' => 10.0,  'available' => 10.0,  'threshold' => 4.0,  'reorder' => 6.0],
            'Printer Ink, Color Set'        => ['on_hand' => 12.0,  'available' => 12.0,  'threshold' => 4.0,  'reorder' => 6.0],

            // Low-Stock items (triggers active Proactive GSO Demand Forecast Signals):
            'Bond Paper, A4 (80 GSM)'       => ['on_hand' => 4.0,   'available' => 4.0,   'threshold' => 20.0, 'reorder' => 25.0],
            'Bond Paper, Legal (80 GSM)'    => ['on_hand' => 5.0,   'available' => 5.0,   'threshold' => 15.0, 'reorder' => 20.0],
            'Printer Ink, Black'            => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 10.0, 'reorder' => 12.0],
            'Toner Cartridge'               => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0,  'reorder' => 6.0],
            'Flash Drive, 64GB'             => ['on_hand' => 3.0,   'available' => 3.0,   'threshold' => 8.0,  'reorder' => 10.0],
            'Mouse, USB Optical'            => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 6.0,  'reorder' => 8.0],
            'HP 680 Tri-Color Ink'          => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0,  'reorder' => 6.0],
            'Rubbing Alcohol (70%)'         => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 6.0,  'reorder' => 10.0],
            'Detergent Powder'              => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0,  'reorder' => 8.0],
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
                ['name' => 'Bond Paper, A4 (80 GSM)', 'qty' => 120, 'cost' => 1250.00, 'sched' => 'Q1'],
                ['name' => 'Bond Paper, Legal (80 GSM)', 'qty' => 80, 'cost' => 1350.00, 'sched' => 'Q1'],
                ['name' => 'Ballpen, Black', 'qty' => 60, 'cost' => 450.00, 'sched' => 'Q1'],
                ['name' => 'Ballpen, Blue', 'qty' => 40, 'cost' => 450.00, 'sched' => 'Q1'],
                ['name' => 'Folder, Tagboard (Legal size)', 'qty' => 40, 'cost' => 380.00, 'sched' => 'Q2'],
                ['name' => 'Folder, Tagboard (A4 size)', 'qty' => 30, 'cost' => 350.00, 'sched' => 'Q2'],
                ['name' => 'Printer Ink, Black', 'qty' => 24, 'cost' => 1850.00, 'sched' => 'Q1'],
                ['name' => 'Rubbing Alcohol (70%)', 'qty' => 30, 'cost' => 780.00, 'sched' => 'Q2'],
                ['name' => 'Flash Drive, 32GB', 'qty' => 15, 'cost' => 650.00, 'sched' => 'Q3'],
                ['name' => 'Trash Bag, Black XL', 'qty' => 40, 'cost' => 420.00, 'sched' => 'Q2'],
                ['name' => 'Staple Wire', 'qty' => 20, 'cost' => 180.00, 'sched' => 'Q1'],
            ];

            foreach ($standardPpmpCatalog as $pItem) {
                if (! isset($items[$pItem['name']])) continue;
                $catItem = $items[$pItem['name']];

                $spec = $catItem->specifications->first()?->specification ?? sprintf('Standard %s specification', $pItem['name']);

                $createdPpmpItem = PpmpItem::query()->create([
                    'ppmp_id' => $ppmp->ppmp_id,
                    'item_id' => $catItem->item_id,
                    'item_category_id' => $catItem->item_category_id,
                    'quantity' => $pItem['qty'],
                    'unit_cost' => $pItem['cost'],
                    'estimated_budget' => round($pItem['qty'] * $pItem['cost'], 2),
                    'procurement_schedule' => $pItem['sched'],
                    'remarks' => $spec,
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
                'request_code' => 'SR-MSWDO-2026-09-0001',
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
                ['name' => 'Ballpen, Black', 'qty' => 3.0],
                ['name' => 'Rubbing Alcohol (70%)', 'qty' => 2.0],
                ['name' => 'Trash Bag, Black XL', 'qty' => 2.0],
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
                'request_code' => 'SR-MPDO-2026-09-0002',
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
                ['name' => 'Bond Paper, A4 (80 GSM)', 'qty' => 10.0], // Available: 4 -> Shortage: 6
                ['name' => 'Printer Ink, Black', 'qty' => 4.0],        // Available: 2 -> Shortage: 2
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
                'request_code' => 'SR-MSWDO-2026-09-0002',
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
                ['name' => 'Bond Paper, A4 (80 GSM)', 'qty' => 8.0], // Available: 4 -> Shortage: 4
                ['name' => 'Printer Ink, Black', 'qty' => 3.0],      // Available: 2 -> Shortage: 1
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
                'request_code' => 'SR-MPDO-2026-09-0001',
                'office_id' => $mpdo->id,
                'requested_by' => $mpdoUser->id,
                'ppmp_id' => $ppmps['MPDO']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-02'),
                'needed_by_date' => Carbon::parse('2026-09-12'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Q3 municipal geospatial and statistical quarterly reports.',
                'priority_level' => 'NORMAL',
                'status' => 'FOR_PROCUREMENT',
                'review_notes' => 'Supply request consolidated into PR-2026-09-0001 for procurement.',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource1->supply_request_id,
                'item_id' => $resolveItem('Bond Paper, A4 (80 GSM)')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Bond Paper, A4 (80 GSM)']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'quantity_requested' => 12.0,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource1->supply_request_id,
                'item_id' => $resolveItem('Flash Drive, 32GB')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Flash Drive, 32GB']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Flash Drive, 32GB',
                'quantity_requested' => 3.0,
            ]);

            // Source request 2 (ENG engineering survey supplies)
            $srConsolidatedSource2 = SupplyRequest::query()->create([
                'request_code' => 'SR-ENG-2026-09-0001',
                'office_id' => $eng->id,
                'requested_by' => $engUser->id,
                'ppmp_id' => $ppmps['ENG']->ppmp_id ?? null,
                'request_date' => Carbon::parse('2026-09-03'),
                'needed_by_date' => Carbon::parse('2026-09-14'),
                'request_category' => 'Office Supplies',
                'purpose' => 'Road network and drainage infrastructure project blueprints.',
                'priority_level' => 'NORMAL',
                'status' => 'FOR_PROCUREMENT',
                'review_notes' => 'Supply request consolidated into PR-2026-09-0001 for procurement.',
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource2->supply_request_id,
                'item_id' => $resolveItem('Bond Paper, A4 (80 GSM)')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Bond Paper, A4 (80 GSM)']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
                'quantity_requested' => 8.0,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => $srConsolidatedSource2->supply_request_id,
                'item_id' => $resolveItem('Flash Drive, 32GB')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Flash Drive, 32GB']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Flash Drive, 32GB',
                'quantity_requested' => 2.0,
            ]);

            // Exactly ONE Consolidated Purchase Request forwarded to BAC
            $consolidatedPr = PurchaseRequest::query()->create([
                'pr_number' => 'PR-2026-09-0001',
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
                'item_id' => $resolveItem('Bond Paper, A4 (80 GSM)')->item_id,
                'quantity_requested' => 20.0,
                'remarks' => 'Consolidated demand for Planning & Engineering.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $consolidatedPr->purchase_request_id,
                'item_id' => $resolveItem('Flash Drive, 32GB')->item_id,
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
                'request_code' => 'SR-MHO-2026-08-0001',
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
                'item_id' => $resolveItem('Rubbing Alcohol (70%)')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Rubbing Alcohol (70%)']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Rubbing Alcohol (70%)',
                'quantity_requested' => 15.0,
            ]);

            $sriDetergent = SupplyRequestItem::query()->create([
                'supply_request_id' => $srMho->supply_request_id,
                'item_id' => $resolveItem('Detergent Powder')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Detergent Powder']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Detergent Powder',
                'quantity_requested' => 10.0,
            ]);

            // Purchase Request
            $prMho = PurchaseRequest::query()->create([
                'pr_number' => 'PR-2026-08-0001',
                'supply_request_id' => $srMho->supply_request_id,
                'office_id' => $mho->id,
                'requested_by' => $gsoUser->id,
                'status' => 'PROCESSING',
                'remarks' => 'Purchase request approved by BAC and canvassed.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prMho->purchase_request_id,
                'item_id' => $resolveItem('Rubbing Alcohol (70%)')->item_id,
                'quantity_requested' => 15.0,
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prMho->purchase_request_id,
                'item_id' => $resolveItem('Detergent Powder')->item_id,
                'quantity_requested' => 10.0,
            ]);

            // Active Purchase Order (Placed with supplier)
            $poActive = PurchaseOrder::query()->create([
                'po_number' => 'PO-2026-09-0001',
                'purchase_request_id' => $prMho->purchase_request_id,
                'supply_request_id' => $srMho->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $procUser->id,
                'approved_by' => $procUser->id,
                'date_approved' => Carbon::parse('2026-09-05'),
                'status' => 'PLACED',
                'total_amount' => 16200.00,
                'procurement_method' => 'SHOPPING',
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poActive->purchase_order_id,
                'supply_request_item_id' => $sriAlcohol->supply_request_item_id,
                'item_id' => $resolveItem('Rubbing Alcohol (70%)')->item_id,
                'item_name_snapshot' => 'Rubbing Alcohol (70%)',
                'unit' => $resolveItem('Rubbing Alcohol (70%)')->default_unit ?? 'box',
                'quantity_ordered' => 15.0,
                'unit_price' => 780.00,
                'total_price' => 11700.00,
            ]);

            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poActive->purchase_order_id,
                'supply_request_item_id' => $sriDetergent->supply_request_item_id,
                'item_id' => $resolveItem('Detergent Powder')->item_id,
                'item_name_snapshot' => 'Detergent Powder',
                'unit' => $resolveItem('Detergent Powder')->default_unit ?? 'box',
                'quantity_ordered' => 10.0,
                'unit_price' => 450.00,
                'total_price' => 4500.00,
            ]);
        }

        // Completed historical procurement (Q2) for Analytics, Timeline & Disclosures
        if ($mpdo) {
            $srHistorical = SupplyRequest::query()->create([
                'request_code' => 'SR-MPDO-2026-04-0001',
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
                'item_id' => $resolveItem('Folder, Tagboard (Legal size)')->item_id,
                'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Folder, Tagboard (Legal size)']->ppmp_item_id ?? null,
                'item_name_snapshot' => 'Folder, Tagboard (Legal size)',
                'quantity_requested' => 10.0,
            ]);

            $prHistorical = PurchaseRequest::query()->create([
                'pr_number' => 'PR-2026-04-0001',
                'supply_request_id' => $srHistorical->supply_request_id,
                'office_id' => $mpdo->id,
                'requested_by' => $gsoUser->id,
                'status' => 'COMPLETED',
                'remarks' => 'Completed procurement cycle.',
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => $prHistorical->purchase_request_id,
                'item_id' => $resolveItem('Folder, Tagboard (Legal size)')->item_id,
                'quantity_requested' => 10.0,
            ]);

            $poHistorical = PurchaseOrder::query()->create([
                'po_number' => 'PO-2026-05-0001',
                'purchase_request_id' => $prHistorical->purchase_request_id,
                'supply_request_id' => $srHistorical->supply_request_id,
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $procUser->id,
                'approved_by' => $procUser->id,
                'date_approved' => Carbon::parse('2026-05-02'),
                'status' => 'RECEIVED',
                'total_amount' => 3800.00,
                'procurement_method' => 'SHOPPING',
            ]);

            $poiHistorical = PurchaseOrderItem::query()->create([
                'purchase_order_id' => $poHistorical->purchase_order_id,
                'supply_request_item_id' => $sriFolder->supply_request_item_id,
                'item_id' => $resolveItem('Folder, Tagboard (Legal size)')->item_id,
                'item_name_snapshot' => 'Folder, Tagboard (Legal size)',
                'unit' => $resolveItem('Folder, Tagboard (Legal size)')->default_unit ?? 'pack',
                'quantity_ordered' => 10.0,
                'unit_price' => 380.00,
                'total_price' => 3800.00,
            ]);

            // Delivery with realistic DR & Invoice tracking
            $deliveryHistorical = Delivery::query()->create([
                'purchase_order_id' => $poHistorical->purchase_order_id,
                'delivery_code' => 'DEL-2026-05-0001',
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
                'distribution_code' => 'DIST-2026-05-0001',
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
                'item_id' => $resolveItem('Folder, Tagboard (Legal size)')->item_id,
                'received_by' => $mpdoUser->id,
                'item_name_snapshot' => 'Folder, Tagboard (Legal size)',
                'quantity_released' => 10.0,
                'quantity_received' => 10.0,
                'receipt_condition' => 'GOOD',
                'unit' => 'pack',
            ]);
        }

        // Active Distribution ready for Office Acknowledgment Demo (ENG Office)
        if ($eng && isset($srConsolidatedSource2)) {
            $distActive = DistributionRecord::query()->create([
                'distribution_code' => 'DIST-2026-09-0001',
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
                'item_id' => $resolveItem('Safety Gloves')->item_id,
                'received_by' => $engUser->id,
                'item_name_snapshot' => 'Safety Gloves',
                'quantity_released' => 5.0,
                'quantity_received' => 0.0,
                'receipt_condition' => 'GOOD',
                'unit' => $resolveItem('Safety Gloves')->default_unit ?? 'pair',
            ]);
        }

        // =========================================================================
        // 6. Executive Anomaly Detection & AI Governance Alerts
        // =========================================================================
        $this->command?->info('6/7 Seeding executive anomaly detection records and governance alerts...');

        $printerInk = $resolveItem('Printer Ink, Black');
        $bondPaper = $resolveItem('Bond Paper, A4 (80 GSM)');

        // 1. High Severity Price Z-Score Anomaly
        $priceAnomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PRICE_Z_SCORE,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $poPlaced->purchase_order_id ?? 1,
            'item_id' => $printerInk?->item_id,
            'office_id' => $mho->id,
            'detected_value' => 2450.00,
            'expected_value' => 1850.00,
            'threshold_value' => 2100.00,
            'z_score' => 2.45,
            'detection_method' => 'STATISTICAL_Z_SCORE',
            'severity' => 'MEDIUM',
            'status' => 'IN_REVIEW',
            'detected_at' => Carbon::parse('2026-09-08 14:30:00'),
            'remarks' => 'Supplier quotation unit cost deviates +32.4% from historical average procurement cost.',
        ]);

        // 2. Excessive Quantity Anomaly
        $qtyAnomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_EXCESSIVE_QUANTITY,
            'reference_type' => SupplyRequest::class,
            'reference_id' => $srInStock->supply_request_id ?? 1,
            'item_id' => $bondPaper?->item_id,
            'office_id' => $mswdo->id,
            'detected_value' => 120.00,
            'expected_value' => 25.00,
            'threshold_value' => 50.00,
            'z_score' => 3.12,
            'detection_method' => 'HISTORICAL_SPIKE_DETECTOR',
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'detected_at' => Carbon::parse('2026-09-08 16:15:00'),
            'remarks' => 'Requested quantity exceeds office historical consumption velocity by 380%.',
        ]);

        // 3. Resolved PPMP Schedule Mismatch Anomaly
        $scheduleAnomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PPMP_SCHEDULE_MISMATCH,
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmps['ENG']->ppmp_id ?? 1,
            'item_id' => $printerInk?->item_id,
            'office_id' => $eng->id,
            'detected_value' => 1.00,
            'expected_value' => 3.00,
            'threshold_value' => 0.00,
            'z_score' => 1.00,
            'detection_method' => 'SCHEDULE_TIMING_AUDIT',
            'severity' => 'LOW',
            'status' => 'RESOLVED',
            'detected_at' => Carbon::parse('2026-09-05 09:00:00'),
            'remarks' => 'Requisition timing realigned with approved quarterly schedule.',
        ]);

        // Seed corresponding Alerts for Executive and Procurement
        Alert::query()->create([
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'supplier_id' => $supplier->id,
            'office_id' => $gsoOffice->id,
            'alert_message' => 'Central Warehouse stock for Printer Ink is below critical safety threshold (3 boxes remaining, threshold 8).',
            'detected_at' => Carbon::parse('2026-09-08 10:00:00'),
            'status' => 'TRIGGERED',
        ]);

        Alert::query()->create([
            'alert_type' => 'DELAYED_DELIVERY',
            'severity' => 'MEDIUM',
            'purchase_order_id' => $poPlaced->purchase_order_id ?? null,
            'supplier_id' => $supplier->id,
            'office_id' => $mho->id,
            'alert_message' => 'Supplier Metro Office Depot Trading PO-2026-09-0001 delivery status pending follow-up.',
            'detected_at' => Carbon::parse('2026-09-08 11:30:00'),
            'status' => 'TRIGGERED',
        ]);

        Alert::query()->create([
            'alert_type' => 'ANOMALY_DETECTED',
            'severity' => 'HIGH',
            'anomaly_id' => $qtyAnomaly->anomaly_id,
            'office_id' => $mswdo->id,
            'alert_message' => 'High Severity Anomaly: Excessive requisition quantity detected for Municipal Social Welfare and Development Office.',
            'detected_at' => Carbon::parse('2026-09-08 16:20:00'),
            'status' => 'TRIGGERED',
        ]);

        Alert::query()->create([
            'alert_type' => 'PRICE_DEVIATION',
            'severity' => 'MEDIUM',
            'anomaly_id' => $priceAnomaly->anomaly_id,
            'office_id' => $mho->id,
            'alert_message' => 'Price Deviation Flag: Quotation unit cost deviates +32.4% above statistical benchmark.',
            'detected_at' => Carbon::parse('2026-09-08 14:35:00'),
            'status' => 'TRIGGERED',
        ]);

        // =========================================================================
        // 7. System Governance Ops (Backups and Yearly Archive)
        // =========================================================================
        $this->command?->info('7/7 Seeding system governance backups and yearly archive logs...');
        $this->call(GovernanceOpsDemoSeeder::class);

        $this->command?->info('Workflow data successfully populated with clean, realistic operational dataset spanning 2024, 2025, and 2026!');
    }
}
