<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryVerification;
use App\Models\DistributionItem;
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
use App\Models\StatusHistory;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionalDataRefreshSeeder extends Seeder
{
    /**
     * Resets all working transactional tables and re-seeds clean, category-aligned operational workflows.
     * Preserves master tables: Users, Roles, Offices, Item Categories, Item Catalog, and PPMPs.
     */
    public function run(): void
    {
        $this->command?->info('================================================================');
        $this->command?->info('GovSupply Connect: Category-Driven Transactional Data Refresh');
        $this->command?->info('================================================================');

        // -------------------------------------------------------------------------
        // 1. Truncate Working / Transactional Tables (Preserving Master Records)
        // -------------------------------------------------------------------------
        $this->command?->info('1/6 Resetting working transactional tables...');

        $tablesToTruncate = [
            'public_disclosures',
            'public_disclosure_sources',
            'claims',
            'distribution_items',
            'distribution_records',
            'delivery_verifications',
            'delivery_items',
            'deliveries',
            'purchase_order_items',
            'purchase_orders',
            'purchase_request_items',
            'purchase_requests',
            'supply_request_items',
            'supply_requests',
            'status_history',
            'approval_history',
            'anomalies',
            'alerts',
            'documents',
            'inventory_movements',
            'supplier_item_categories',
            'suppliers',
        ];

        $existingTables = array_filter($tablesToTruncate, fn (string $table) => Schema::hasTable($table));
        if (! empty($existingTables)) {
            DB::statement('TRUNCATE TABLE ' . implode(', ', $existingTables) . ' RESTART IDENTITY CASCADE;');
        }

        // -------------------------------------------------------------------------
        // 2. Reset Inventory Balances to Clean Baseline Levels
        // -------------------------------------------------------------------------
        $this->command?->info('2/6 Restoring central warehouse inventory baseline levels...');

        $stockProfiles = [
            // Plentiful / Stock-Ready items:
            'Ballpen, Black'                => ['on_hand' => 45.0,  'available' => 45.0,  'threshold' => 10.0],
            'Ballpen, Blue'                 => ['on_hand' => 30.0,  'available' => 30.0,  'threshold' => 10.0],
            'Ballpen, Red'                  => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0],
            'Folder, Tagboard (Legal size)'  => ['on_hand' => 30.0,  'available' => 30.0,  'threshold' => 10.0],
            'Folder, Tagboard (A4 size)'     => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 10.0],
            'Staple Wire'                   => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 5.0],
            'Heavy Duty Stapler'            => ['on_hand' => 8.0,   'available' => 8.0,   'threshold' => 2.0],
            'Trash Bag, Black XL'           => ['on_hand' => 25.0,  'available' => 25.0,  'threshold' => 8.0],
            'Trash Bag, Yellow Biohazard XL' => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0],
            'Safety Gloves'                 => ['on_hand' => 12.0,  'available' => 12.0,  'threshold' => 4.0],
            'Utility Cutter'                => ['on_hand' => 15.0,  'available' => 15.0,  'threshold' => 5.0],
            'Logbook'                       => ['on_hand' => 10.0,  'available' => 10.0,  'threshold' => 4.0],
            'Flash Drive, 32GB'             => ['on_hand' => 10.0,  'available' => 10.0,  'threshold' => 4.0],
            'Printer Ink, Color Set'        => ['on_hand' => 12.0,  'available' => 12.0,  'threshold' => 4.0],

            // Low-Stock items (triggers active Proactive GSO Demand Forecast Signals):
            'Bond Paper, A4 (80 GSM)'       => ['on_hand' => 4.0,   'available' => 4.0,   'threshold' => 20.0],
            'Bond Paper, Legal (80 GSM)'    => ['on_hand' => 5.0,   'available' => 5.0,   'threshold' => 15.0],
            'Printer Ink, Black'            => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 10.0],
            'Toner Cartridge'               => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0],
            'Flash Drive, 64GB'             => ['on_hand' => 3.0,   'available' => 3.0,   'threshold' => 8.0],
            'Mouse, USB Optical'            => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 6.0],
            'HP 680 Tri-Color Ink'          => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0],
            'Rubbing Alcohol (70%)'         => ['on_hand' => 2.0,   'available' => 2.0,   'threshold' => 6.0],
            'Detergent Powder'              => ['on_hand' => 1.0,   'available' => 1.0,   'threshold' => 5.0],
        ];

        $items = Item::query()->with('specifications')->get()->keyBy('item_name');
        foreach ($items as $name => $item) {
            $profile = $stockProfiles[$name] ?? ['on_hand' => 10.0, 'available' => 10.0, 'threshold' => 5.0];
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

        // -------------------------------------------------------------------------
        // 3. Seed Realistic Suppliers with Category Accreditation
        // -------------------------------------------------------------------------
        $this->command?->info('3/6 Seeding 5 accredited suppliers with category alignments...');

        // Supplier 1: Office Supplies Specialist
        $supOffice = Supplier::query()->create([
            'supplier_name' => 'Metro Office Depot Trading',
            'contact_person' => 'Mina Solis',
            'email' => 'orders@metroofficedepot.example',
            'phone' => '09171234567',
            'address' => 'Poblacion District, City Proper',
            'status' => 'ACTIVE',
        ]);
        $supOffice->categories()->sync([1]); // Office Supplies

        // Supplier 2: IT Equipment Specialist
        $supIT = Supplier::query()->create([
            'supplier_name' => 'ByteCore IT Solutions & Electronics',
            'contact_person' => 'Engr. Ronald Tan',
            'email' => 'enterprise@bytecore-it.example',
            'phone' => '09182345678',
            'address' => 'Cyberzone Park, Technology Corridor',
            'status' => 'ACTIVE',
        ]);
        $supIT->categories()->sync([2]); // IT Equipment

        // Supplier 3: Cleaning Supplies Specialist
        $supCleaning = Supplier::query()->create([
            'supplier_name' => 'CleanTech Sanitation & Hygiene Supplies',
            'contact_person' => 'Carmela Gomez',
            'email' => 'institutional@cleantech-supplies.example',
            'phone' => '09193456789',
            'address' => 'San Isidro Industrial Estate, Metro',
            'status' => 'ACTIVE',
        ]);
        $supCleaning->categories()->sync([3]); // Cleaning Supplies

        // Supplier 4: Hardware & Maintenance Specialist
        $supHardware = Supplier::query()->create([
            'supplier_name' => 'Apex Maintenance & Hardware Depot',
            'contact_person' => 'Danilo Cruz',
            'email' => 'supply@apexhardware.example',
            'phone' => '09204567890',
            'address' => 'Highway 4 Construction Hub, West District',
            'status' => 'ACTIVE',
        ]);
        $supHardware->categories()->sync([4]); // Maintenance Supplies

        // Supplier 5: Multi-Category General Merchandiser
        $supGeneral = Supplier::query()->create([
            'supplier_name' => 'Grand Alliance Commercial Corp.',
            'contact_person' => 'Beatrice Lim',
            'email' => 'tenders@grandalliance.example',
            'phone' => '09215678901',
            'address' => 'Commercial Center Blvd, East District',
            'status' => 'ACTIVE',
        ]);
        $supGeneral->categories()->sync([1, 3]); // Office Supplies & Cleaning Supplies

        // -------------------------------------------------------------------------
        // 4. Seed Multi-Year Historical Procurement (FY 2024 & FY 2025)
        // -------------------------------------------------------------------------
        $this->command?->info('4/6 Seeding multi-year historical dataset with category-scoped supply requests (2024-2025)...');
        $this->call(MultiYearHistoricalProcurementSeeder::class);

        // -------------------------------------------------------------------------
        // 5. Seed Clean Category-Pure Operational Workflows for FY 2026
        // -------------------------------------------------------------------------
        $this->command?->info('5/6 Seeding clean FY 2026 category-pure operational demonstration workflows...');

        // Resolve key actors
        $execUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))->first()
            ?? User::query()->firstOrFail();
        $gsoUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))->first()
            ?? $execUser;
        $procUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PROC_OFFICER'))->first()
            ?? $execUser;

        // Resolve operating offices
        $mpdo = Office::query()->where('code', 'MPDO')->firstOrFail();
        $mswdo = Office::query()->where('code', 'MSWDO')->firstOrFail();
        $eng = Office::query()->where('code', 'ENG')->firstOrFail();
        $mho = Office::query()->where('code', 'MHO')->firstOrFail();
        $gsoOffice = Office::query()->where('code', 'GSO')->firstOrFail();

        $mpdoUser = User::query()->where('office_id', $mpdo->id)->first() ?? $execUser;
        $mswdoUser = User::query()->where('office_id', $mswdo->id)->first() ?? $execUser;
        $engUser = User::query()->where('office_id', $eng->id)->first() ?? $execUser;
        $mhoUser = User::query()->where('office_id', $mho->id)->first() ?? $execUser;

        // Ensure 2026 PPMPs exist for the 4 offices
        $ppmpOfficeConfigs = [
            ['office' => $mpdo, 'user' => $mpdoUser, 'title' => 'Planning & Spatial Data Consumables', 'budget' => 225000.00],
            ['office' => $mswdo, 'user' => $mswdoUser, 'title' => 'Social Services & Community Support Supplies', 'budget' => 310000.00],
            ['office' => $eng, 'user' => $engUser, 'title' => 'Engineering Infrastructure Project Documents & Materials', 'budget' => 260000.00],
            ['office' => $mho, 'user' => $mhoUser, 'title' => 'Health Office Record-Keeping & Clinical Consumables', 'budget' => 195000.00],
        ];

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

        $ppmps = [];
        $ppmpItemsByOffice = [];

        foreach ($ppmpOfficeConfigs as $cfg) {
            $ppmp = Ppmp::query()->firstOrCreate(
                [
                    'office_id' => $cfg['office']->id,
                    'fiscal_year' => 2026,
                ],
                [
                    'prepared_by' => $cfg['user']->id,
                    'approved_by' => $execUser->id,
                    'fund_source' => 'APPROPRIATION_ORDINANCE',
                    'total_estimated_budget' => $cfg['budget'],
                    'status' => 'APPROVED',
                    'date_prepared' => Carbon::parse('2026-01-05'),
                    'date_approved' => Carbon::parse('2026-01-12'),
                    'remarks' => sprintf('Approved FY 2026 PPMP for %s.', $cfg['office']->name),
                ]
            );
            $ppmps[$cfg['office']->code] = $ppmp;

            foreach ($standardPpmpCatalog as $pItem) {
                if (! isset($items[$pItem['name']])) continue;
                $catItem = $items[$pItem['name']];

                $spec = $catItem->specifications->first()?->specification ?? sprintf('Standard %s specification', $pItem['name']);

                $createdPpmpItem = PpmpItem::query()->firstOrCreate(
                    [
                        'ppmp_id' => $ppmp->ppmp_id,
                        'item_id' => $catItem->item_id,
                    ],
                    [
                        'item_category_id' => $catItem->item_category_id,
                        'quantity' => $pItem['qty'],
                        'unit_cost' => $pItem['cost'],
                        'estimated_budget' => round($pItem['qty'] * $pItem['cost'], 2),
                        'procurement_schedule' => $pItem['sched'],
                        'remarks' => $spec,
                    ]
                );

                $ppmpItemsByOffice[$cfg['office']->code][$pItem['name']] = $createdPpmpItem;
            }
        }

        // =========================================================================
        // WORKFLOW 1: Completed Office Supplies Cycle (Requisition -> PO -> Delivery -> Release)
        // Commodity: Office Supplies (Category 1) | Supplier: Metro Office Depot Trading
        // =========================================================================
        $srCompleted = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-03-0001',
            'office_id' => $mpdo->id,
            'requested_by' => $mpdoUser->id,
            'ppmp_id' => $ppmps['MPDO']->ppmp_id,
            'item_category_id' => 1,
            'request_category' => 'Office Supplies',
            'request_date' => Carbon::parse('2026-03-02'),
            'needed_by_date' => Carbon::parse('2026-03-12'),
            'purpose' => 'First quarter municipal planning socio-economic profile and zoning documentation.',
            'priority_level' => 'NORMAL',
            'status' => 'COMPLETED',
            'approved_by' => $gsoUser->id,
            'approved_at' => Carbon::parse('2026-03-03 09:30:00'),
            'review_notes' => 'Approved and fulfilled via PO-2026-03-0001.',
        ]);

        $sriCompletedPaper = SupplyRequestItem::query()->create([
            'supply_request_id' => $srCompleted->supply_request_id,
            'item_id' => $items['Bond Paper, A4 (80 GSM)']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Bond Paper, A4 (80 GSM)']->ppmp_item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_requested' => 20.0,
        ]);

        $sriCompletedPens = SupplyRequestItem::query()->create([
            'supply_request_id' => $srCompleted->supply_request_id,
            'item_id' => $items['Ballpen, Black']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Ballpen, Black']->ppmp_item_id,
            'item_name_snapshot' => 'Ballpen, Black',
            'quantity_requested' => 10.0,
        ]);

        $prCompleted = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-03-0001',
            'supply_request_id' => $srCompleted->supply_request_id,
            'office_id' => $mpdo->id,
            'requested_by' => $gsoUser->id,
            'status' => 'COMPLETED',
            'remarks' => 'Procurement cycle completed with delivery and distribution.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prCompleted->purchase_request_id,
            'item_id' => $items['Bond Paper, A4 (80 GSM)']->item_id,
            'quantity_requested' => 20.0,
            'remarks' => 'A4 Bond Paper for MPDO Q1 documentation.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prCompleted->purchase_request_id,
            'item_id' => $items['Ballpen, Black']->item_id,
            'quantity_requested' => 10.0,
            'remarks' => 'Black Ballpens for MPDO staff.',
        ]);

        $poCompleted = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-03-0001',
            'purchase_request_id' => $prCompleted->purchase_request_id,
            'supply_request_id' => $srCompleted->supply_request_id,
            'supplier_id' => $supOffice->supplier_id,
            'created_by' => $procUser->id,
            'approved_by' => $procUser->id,
            'date_approved' => Carbon::parse('2026-03-06'),
            'status' => 'RECEIVED',
            'total_amount' => 29500.00,
            'procurement_method' => 'SHOPPING',
        ]);

        $poiPaper = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poCompleted->purchase_order_id,
            'supply_request_item_id' => $sriCompletedPaper->supply_request_item_id,
            'item_id' => $items['Bond Paper, A4 (80 GSM)']->item_id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'unit' => 'box',
            'quantity_ordered' => 20.0,
            'unit_price' => 1250.00,
            'total_price' => 25000.00,
        ]);

        $poiPens = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poCompleted->purchase_order_id,
            'supply_request_item_id' => $sriCompletedPens->supply_request_item_id,
            'item_id' => $items['Ballpen, Black']->item_id,
            'item_name_snapshot' => 'Ballpen, Black',
            'unit' => 'box',
            'quantity_ordered' => 10.0,
            'unit_price' => 450.00,
            'total_price' => 4500.00,
        ]);

        $delCompleted = Delivery::query()->create([
            'purchase_order_id' => $poCompleted->purchase_order_id,
            'delivery_code' => 'DEL-2026-03-0001',
            'supplier_id' => $supOffice->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => Carbon::parse('2026-03-15'),
            'expected_arrival_date' => Carbon::parse('2026-03-15'),
            'delivery_receipt_no' => 'DR-2026-0312',
            'sales_invoice_no' => 'SI-2026-0988',
            'sales_invoice_date' => Carbon::parse('2026-03-15'),
            'sales_invoice_amount' => 29500.00,
            'receipt_status' => 'VERIFIED',
            'remarks' => 'Full delivery inspected and accepted in good order and condition.',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delCompleted->delivery_id,
            'purchase_order_item_id' => $poiPaper->purchase_order_item_id,
            'expected_quantity' => 20.0,
            'received_quantity' => 20.0,
            'damaged_quantity' => 0.0,
            'missing_quantity' => 0.0,
            'item_condition' => 'GOOD',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delCompleted->delivery_id,
            'purchase_order_item_id' => $poiPens->purchase_order_item_id,
            'expected_quantity' => 10.0,
            'received_quantity' => 10.0,
            'damaged_quantity' => 0.0,
            'missing_quantity' => 0.0,
            'item_condition' => 'GOOD',
        ]);

        DeliveryVerification::query()->create([
            'delivery_id' => $delCompleted->delivery_id,
            'verified_by' => $gsoUser->id,
            'verification_date' => Carbon::parse('2026-03-15'),
            'verification_status' => 'VERIFIED',
            'remarks' => 'Inspection and Acceptance Report (IAR) completed. Commodities match PO specifications.',
        ]);

        $distCompleted = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-03-0001',
            'supply_request_id' => $srCompleted->supply_request_id,
            'delivery_id' => $delCompleted->delivery_id,
            'office_id' => $mpdo->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $mpdoUser->id,
            'release_date' => Carbon::parse('2026-03-18'),
            'storage_location' => 'Main Warehouse',
            'status' => 'RECEIVED',
            'notes' => 'Full requisition distributed and accepted by MPDO requisitioner.',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distCompleted->distribution_record_id,
            'item_id' => $items['Bond Paper, A4 (80 GSM)']->item_id,
            'received_by' => $mpdoUser->id,
            'item_name_snapshot' => 'Bond Paper, A4 (80 GSM)',
            'quantity_released' => 20.0,
            'quantity_received' => 20.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'box',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distCompleted->distribution_record_id,
            'item_id' => $items['Ballpen, Black']->item_id,
            'received_by' => $mpdoUser->id,
            'item_name_snapshot' => 'Ballpen, Black',
            'quantity_released' => 10.0,
            'quantity_received' => 10.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'box',
        ]);

        // =========================================================================
        // WORKFLOW 2: Active IT Equipment Procurement Cycle (PO Placed, Awaiting Delivery)
        // Commodity: IT Equipment (Category 2) | Supplier: ByteCore IT Solutions & Electronics
        // =========================================================================
        $srIT = SupplyRequest::query()->create([
            'request_code' => 'SR-ENG-2026-09-0001',
            'office_id' => $eng->id,
            'requested_by' => $engUser->id,
            'ppmp_id' => $ppmps['ENG']->ppmp_id,
            'item_category_id' => 2,
            'request_category' => 'IT Equipment',
            'request_date' => Carbon::parse('2026-09-04'),
            'needed_by_date' => Carbon::parse('2026-09-20'),
            'purpose' => 'Digital archiving of infrastructure project CAD blueprints and GIS road network survey records.',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'review_notes' => 'Warehouse stock insufficient. Forwarded for BAC procurement.',
        ]);

        $sriITUsb = SupplyRequestItem::query()->create([
            'supply_request_id' => $srIT->supply_request_id,
            'item_id' => $items['Flash Drive, 32GB']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Flash Drive, 32GB']->ppmp_item_id,
            'item_name_snapshot' => 'Flash Drive, 32GB',
            'quantity_requested' => 5.0,
        ]);

        $sriITInk = SupplyRequestItem::query()->create([
            'supply_request_id' => $srIT->supply_request_id,
            'item_id' => $items['Printer Ink, Black']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['ENG']['Printer Ink, Black']->ppmp_item_id,
            'item_name_snapshot' => 'Printer Ink, Black',
            'quantity_requested' => 4.0,
        ]);

        $prIT = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0001',
            'supply_request_id' => $srIT->supply_request_id,
            'office_id' => $eng->id,
            'requested_by' => $gsoUser->id,
            'status' => 'PROCESSING',
            'remarks' => 'Canvassed and awarded to accredited IT supplier ByteCore IT Solutions.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prIT->purchase_request_id,
            'item_id' => $items['Flash Drive, 32GB']->item_id,
            'quantity_requested' => 5.0,
            'remarks' => '32GB USB drives for field surveying teams.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prIT->purchase_request_id,
            'item_id' => $items['Printer Ink, Black']->item_id,
            'quantity_requested' => 4.0,
            'remarks' => 'Black ink cartridges for engineering plan plotters.',
        ]);

        $poIT = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-09-0001',
            'purchase_request_id' => $prIT->purchase_request_id,
            'supply_request_id' => $srIT->supply_request_id,
            'supplier_id' => $supIT->supplier_id,
            'created_by' => $procUser->id,
            'approved_by' => $procUser->id,
            'date_approved' => Carbon::parse('2026-09-08'),
            'status' => 'PLACED',
            'total_amount' => 10650.00,
            'procurement_method' => 'SHOPPING',
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poIT->purchase_order_id,
            'supply_request_item_id' => $sriITUsb->supply_request_item_id,
            'item_id' => $items['Flash Drive, 32GB']->item_id,
            'item_name_snapshot' => 'Flash Drive, 32GB',
            'unit' => 'box',
            'quantity_ordered' => 5.0,
            'unit_price' => 650.00,
            'total_price' => 3250.00,
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poIT->purchase_order_id,
            'supply_request_item_id' => $sriITInk->supply_request_item_id,
            'item_id' => $items['Printer Ink, Black']->item_id,
            'item_name_snapshot' => 'Printer Ink, Black',
            'unit' => 'box',
            'quantity_ordered' => 4.0,
            'unit_price' => 1850.00,
            'total_price' => 7400.00,
        ]);

        StatusHistory::query()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $poIT->purchase_order_id,
            'old_status' => null,
            'new_status' => 'PLACED',
            'changed_by' => $procUser->id,
            'remarks' => 'Purchase Order served to ByteCore IT Solutions & Electronics. Expected lead time: 14 days.',
            'changed_at' => Carbon::parse('2026-09-08 14:00:00'),
        ]);

        // =========================================================================
        // WORKFLOW 3: Direct Warehouse Stock Fulfillment (Zero PO / Distribution Ready)
        // Commodity: Office Supplies (Category 1) | Requisitioner: MSWDO
        // =========================================================================
        $srStockReady = SupplyRequest::query()->create([
            'request_code' => 'SR-MSWDO-2026-09-0001',
            'office_id' => $mswdo->id,
            'requested_by' => $mswdoUser->id,
            'ppmp_id' => $ppmps['MSWDO']->ppmp_id,
            'item_category_id' => 1,
            'request_category' => 'Office Supplies',
            'request_date' => Carbon::parse('2026-09-08'),
            'needed_by_date' => Carbon::parse('2026-09-15'),
            'purpose' => 'Social Case Records filing folders and fastener supplies for Senior Citizen payout processing.',
            'priority_level' => 'NORMAL',
            'status' => 'SUBMITTED',
            'review_notes' => '100% available in warehouse stock. Ready for issuance.',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srStockReady->supply_request_id,
            'item_id' => $items['Folder, Tagboard (Legal size)']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MSWDO']['Folder, Tagboard (Legal size)']->ppmp_item_id,
            'item_name_snapshot' => 'Folder, Tagboard (Legal size)',
            'quantity_requested' => 15.0,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srStockReady->supply_request_id,
            'item_id' => $items['Staple Wire']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MSWDO']['Staple Wire']->ppmp_item_id,
            'item_name_snapshot' => 'Staple Wire',
            'quantity_requested' => 5.0,
        ]);

        // Active distribution record ready for MSWDO pickup
        $distActive = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-09-0001',
            'supply_request_id' => $srStockReady->supply_request_id,
            'office_id' => $mswdo->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $mswdoUser->id,
            'release_date' => Carbon::parse('2026-09-09'),
            'storage_location' => 'GSO Requisition Bay 1',
            'status' => 'RELEASED',
            'notes' => 'Stocks prepared and staged for pickup by MSWDO representative.',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distActive->distribution_record_id,
            'item_id' => $items['Folder, Tagboard (Legal size)']->item_id,
            'received_by' => $mswdoUser->id,
            'item_name_snapshot' => 'Folder, Tagboard (Legal size)',
            'quantity_released' => 15.0,
            'quantity_received' => 0.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'pack',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distActive->distribution_record_id,
            'item_id' => $items['Staple Wire']->item_id,
            'received_by' => $mswdoUser->id,
            'item_name_snapshot' => 'Staple Wire',
            'quantity_released' => 5.0,
            'quantity_received' => 0.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'box',
        ]);

        // =========================================================================
        // WORKFLOW 4: Cleaning Supplies Delivery Discrepancy & Warranty Claim
        // Commodity: Cleaning Supplies (Category 3) | Supplier: CleanTech Sanitation
        // =========================================================================
        $srCleaning = SupplyRequest::query()->create([
            'request_code' => 'SR-MHO-2026-08-0001',
            'office_id' => $mho->id,
            'requested_by' => $mhoUser->id,
            'ppmp_id' => $ppmps['MHO']->ppmp_id,
            'item_category_id' => 3,
            'request_category' => 'Cleaning Supplies',
            'request_date' => Carbon::parse('2026-08-10'),
            'needed_by_date' => Carbon::parse('2026-08-20'),
            'purpose' => 'Rural Health Unit infection control, clinical sanitization, and biohazard waste containment.',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'review_notes' => 'Warehouse stock exhausted. Emergency sanitation replenishment.',
        ]);

        $sriAlcohol = SupplyRequestItem::query()->create([
            'supply_request_id' => $srCleaning->supply_request_id,
            'item_id' => $items['Rubbing Alcohol (70%)']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Rubbing Alcohol (70%)']->ppmp_item_id,
            'item_name_snapshot' => 'Rubbing Alcohol (70%)',
            'quantity_requested' => 15.0,
        ]);

        $sriTrashBag = SupplyRequestItem::query()->create([
            'supply_request_id' => $srCleaning->supply_request_id,
            'item_id' => $items['Trash Bag, Black XL']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MHO']['Trash Bag, Black XL']->ppmp_item_id,
            'item_name_snapshot' => 'Trash Bag, Black XL',
            'quantity_requested' => 20.0,
        ]);

        $prCleaning = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-08-0001',
            'supply_request_id' => $srCleaning->supply_request_id,
            'office_id' => $mho->id,
            'requested_by' => $gsoUser->id,
            'status' => 'COMPLETED',
            'remarks' => 'Canvassed and awarded to CleanTech Sanitation & Hygiene Supplies.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prCleaning->purchase_request_id,
            'item_id' => $items['Rubbing Alcohol (70%)']->item_id,
            'quantity_requested' => 15.0,
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prCleaning->purchase_request_id,
            'item_id' => $items['Trash Bag, Black XL']->item_id,
            'quantity_requested' => 20.0,
        ]);

        $poCleaning = PurchaseOrder::query()->create([
            'po_number' => 'PO-2026-08-0001',
            'purchase_request_id' => $prCleaning->purchase_request_id,
            'supply_request_id' => $srCleaning->supply_request_id,
            'supplier_id' => $supCleaning->supplier_id,
            'created_by' => $procUser->id,
            'approved_by' => $procUser->id,
            'date_approved' => Carbon::parse('2026-08-14'),
            'status' => 'RECEIVED',
            'total_amount' => 20100.00,
            'procurement_method' => 'SHOPPING',
        ]);

        $poiAlcohol = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poCleaning->purchase_order_id,
            'supply_request_item_id' => $sriAlcohol->supply_request_item_id,
            'item_id' => $items['Rubbing Alcohol (70%)']->item_id,
            'item_name_snapshot' => 'Rubbing Alcohol (70%)',
            'unit' => 'box',
            'quantity_ordered' => 15.0,
            'unit_price' => 780.00,
            'total_price' => 11700.00,
        ]);

        $poiTrashBag = PurchaseOrderItem::query()->create([
            'purchase_order_id' => $poCleaning->purchase_order_id,
            'supply_request_item_id' => $sriTrashBag->supply_request_item_id,
            'item_id' => $items['Trash Bag, Black XL']->item_id,
            'item_name_snapshot' => 'Trash Bag, Black XL',
            'unit' => 'box',
            'quantity_ordered' => 20.0,
            'unit_price' => 420.00,
            'total_price' => 8400.00,
        ]);

        $delCleaning = Delivery::query()->create([
            'purchase_order_id' => $poCleaning->purchase_order_id,
            'delivery_code' => 'DEL-2026-08-0001',
            'supplier_id' => $supCleaning->supplier_id,
            'received_by' => $gsoUser->id,
            'actual_delivery_date' => Carbon::parse('2026-08-22'),
            'expected_arrival_date' => Carbon::parse('2026-08-20'),
            'delivery_receipt_no' => 'DR-2026-0844',
            'sales_invoice_no' => 'SI-2026-0419',
            'sales_invoice_date' => Carbon::parse('2026-08-22'),
            'sales_invoice_amount' => 20100.00,
            'receipt_status' => 'DISCREPANCY',
            'remarks' => 'Delivery accepted with discrepancy: 1 box of Rubbing Alcohol damaged by puncture leakage.',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delCleaning->delivery_id,
            'purchase_order_item_id' => $poiAlcohol->purchase_order_item_id,
            'expected_quantity' => 15.0,
            'received_quantity' => 14.0,
            'damaged_quantity' => 1.0,
            'missing_quantity' => 0.0,
            'item_condition' => 'DAMAGED',
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delCleaning->delivery_id,
            'purchase_order_item_id' => $poiTrashBag->purchase_order_item_id,
            'expected_quantity' => 20.0,
            'received_quantity' => 20.0,
            'damaged_quantity' => 0.0,
            'missing_quantity' => 0.0,
            'item_condition' => 'GOOD',
        ]);

        DeliveryVerification::query()->create([
            'delivery_id' => $delCleaning->delivery_id,
            'verified_by' => $gsoUser->id,
            'verification_date' => Carbon::parse('2026-08-22'),
            'verification_status' => 'DISCREPANCY',
            'discrepancy_notes' => '1 box (12 bottles) Rubbing Alcohol sustained container seal rupture during shipment.',
            'remarks' => 'Warranty replacement claim initiated with CleanTech Sanitation.',
        ]);

        // Create distribution record for the good items released to MHO
        $distCleaning = DistributionRecord::query()->create([
            'distribution_code' => 'DIST-2026-08-0001',
            'supply_request_id' => $srCleaning->supply_request_id,
            'delivery_id' => $delCleaning->delivery_id,
            'office_id' => $mho->id,
            'prepared_by' => $gsoUser->id,
            'received_by' => $mhoUser->id,
            'release_date' => Carbon::parse('2026-08-23'),
            'storage_location' => 'Main Warehouse',
            'status' => 'RECEIVED',
            'notes' => 'Partial distribution of good stock released to MHO. 1 damaged alcohol container subject to replacement claim.',
        ]);

        $distItemAlcohol = DistributionItem::query()->create([
            'distribution_record_id' => $distCleaning->distribution_record_id,
            'item_id' => $items['Rubbing Alcohol (70%)']->item_id,
            'received_by' => $mhoUser->id,
            'item_name_snapshot' => 'Rubbing Alcohol (70%)',
            'quantity_released' => 14.0,
            'quantity_received' => 14.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'box',
        ]);

        DistributionItem::query()->create([
            'distribution_record_id' => $distCleaning->distribution_record_id,
            'item_id' => $items['Trash Bag, Black XL']->item_id,
            'received_by' => $mhoUser->id,
            'item_name_snapshot' => 'Trash Bag, Black XL',
            'quantity_released' => 20.0,
            'quantity_received' => 20.0,
            'receipt_condition' => 'GOOD',
            'unit' => 'box',
        ]);

        // File warranty replacement claim linked to the distribution record & item
        Claim::query()->create([
            'claim_code' => 'CLM-2026-08-0001',
            'distribution_record_id' => $distCleaning->distribution_record_id,
            'distribution_item_id' => $distItemAlcohol->distribution_item_id,
            'claimed_by' => $mhoUser->id,
            'office_id' => $mho->id,
            'claim_type' => 'DAMAGED_ITEM',
            'claim_date' => Carbon::parse('2026-08-23'),
            'claim_location' => 'GSO Receiving Dock',
            'quantity_disputed' => 1.0,
            'quantity_damaged' => 1.0,
            'quantity_missing' => 0.0,
            'resolution_type' => 'REPLACEMENT_PENDING',
            'replacement_quantity' => 1.0,
            'remarks' => 'Supplier CleanTech Sanitation notified for warranty replacement of 1 damaged box of 70% Rubbing Alcohol.',
            'status' => 'IN_REVIEW',
            'source_type' => 'DISTRIBUTION',
        ]);

        // =========================================================================
        // WORKFLOW 5: Multi-Office Shortage Consolidation Ready for BAC Review
        // Commodity: Office Supplies (Category 1) | Requisitioners: MPDO & MSWDO
        // =========================================================================
        $srShortageMpdo = SupplyRequest::query()->create([
            'request_code' => 'SR-MPDO-2026-09-0002',
            'office_id' => $mpdo->id,
            'requested_by' => $mpdoUser->id,
            'ppmp_id' => $ppmps['MPDO']->ppmp_id,
            'item_category_id' => 1,
            'request_category' => 'Office Supplies',
            'request_date' => Carbon::parse('2026-09-08'),
            'needed_by_date' => Carbon::parse('2026-09-18'),
            'purpose' => 'Comprehensive Land Use Plan documentation and municipal boundary survey printing.',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'review_notes' => 'Stock shortage consolidated into PR-2026-09-0002 for BAC review.',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srShortageMpdo->supply_request_id,
            'item_id' => $items['Bond Paper, Legal (80 GSM)']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Bond Paper, Legal (80 GSM)']->ppmp_item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 8.0,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srShortageMpdo->supply_request_id,
            'item_id' => $items['Ballpen, Blue']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MPDO']['Ballpen, Blue']->ppmp_item_id,
            'item_name_snapshot' => 'Ballpen, Blue',
            'quantity_requested' => 6.0,
        ]);

        $srShortageMswdo = SupplyRequest::query()->create([
            'request_code' => 'SR-MSWDO-2026-09-0002',
            'office_id' => $mswdo->id,
            'requested_by' => $mswdoUser->id,
            'ppmp_id' => $ppmps['MSWDO']->ppmp_id,
            'item_category_id' => 1,
            'request_category' => 'Office Supplies',
            'request_date' => Carbon::parse('2026-09-09'),
            'needed_by_date' => Carbon::parse('2026-09-19'),
            'purpose' => 'Quarterly Social Welfare intake forms and livelihood survey documents.',
            'priority_level' => 'NORMAL',
            'status' => 'FOR_PROCUREMENT',
            'review_notes' => 'Stock shortage consolidated into PR-2026-09-0002 for BAC review.',
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srShortageMswdo->supply_request_id,
            'item_id' => $items['Bond Paper, Legal (80 GSM)']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MSWDO']['Bond Paper, Legal (80 GSM)']->ppmp_item_id,
            'item_name_snapshot' => 'Bond Paper, Legal (80 GSM)',
            'quantity_requested' => 6.0,
        ]);

        SupplyRequestItem::query()->create([
            'supply_request_id' => $srShortageMswdo->supply_request_id,
            'item_id' => $items['Ballpen, Blue']->item_id,
            'ppmp_item_id' => $ppmpItemsByOffice['MSWDO']['Ballpen, Blue']->ppmp_item_id,
            'item_name_snapshot' => 'Ballpen, Blue',
            'quantity_requested' => 4.0,
        ]);

        // Single Consolidated PR forwarded to BAC
        $prConsolidated = PurchaseRequest::query()->create([
            'pr_number' => 'PR-2026-09-0002',
            'supply_request_id' => $srShortageMpdo->supply_request_id,
            'office_id' => $mpdo->id,
            'requested_by' => $gsoUser->id,
            'status' => 'OPEN',
            'remarks' => sprintf(
                'Consolidated Office Supplies PR from 2 supply requests: %s, %s.',
                $srShortageMpdo->request_code,
                $srShortageMswdo->request_code
            ),
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prConsolidated->purchase_request_id,
            'item_id' => $items['Bond Paper, Legal (80 GSM)']->item_id,
            'quantity_requested' => 14.0, // 8 + 6
            'remarks' => 'Consolidated Legal Bond Paper demand for Planning and Social Welfare.',
        ]);

        PurchaseRequestItem::query()->create([
            'purchase_request_id' => $prConsolidated->purchase_request_id,
            'item_id' => $items['Ballpen, Blue']->item_id,
            'quantity_requested' => 10.0, // 6 + 4
            'remarks' => 'Consolidated Blue Ballpen demand for Planning and Social Welfare.',
        ]);

        StatusHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => $prConsolidated->purchase_request_id,
            'old_status' => null,
            'new_status' => 'OPEN',
            'changed_by' => $gsoUser->id,
            'remarks' => 'Consolidated purchase request forwarded to BAC for canvassing and vendor assignment.',
            'changed_at' => Carbon::parse('2026-09-10 10:00:00'),
        ]);

        // =========================================================================
        // 6. Executive Anomaly Detection & AI Governance Alerts
        // =========================================================================
        $this->command?->info('6/6 Seeding AI governance anomalies, predictive alerts, and system backups...');

        $printerInk = $items['Printer Ink, Black'];
        $bondPaper = $items['Bond Paper, A4 (80 GSM)'];

        // 1. High Severity Price Z-Score Anomaly
        $priceAnomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PRICE_Z_SCORE,
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $poIT->purchase_order_id,
            'item_id' => $printerInk->item_id,
            'office_id' => $eng->id,
            'detected_value' => 2450.00,
            'expected_value' => 1850.00,
            'threshold_value' => 2100.00,
            'z_score' => 2.45,
            'detection_method' => 'STATISTICAL_Z_SCORE',
            'severity' => 'MEDIUM',
            'status' => 'IN_REVIEW',
            'detected_at' => Carbon::parse('2026-09-08 14:30:00'),
            'remarks' => 'Supplier quotation unit cost deviates +32.4% from historical average procurement benchmark.',
        ]);

        // 2. Excessive Quantity Anomaly
        $qtyAnomaly = Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_EXCESSIVE_QUANTITY,
            'reference_type' => SupplyRequest::class,
            'reference_id' => $srStockReady->supply_request_id,
            'item_id' => $bondPaper->item_id,
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
        Anomaly::query()->create([
            'anomaly_type' => Anomaly::TYPE_PPMP_SCHEDULE_MISMATCH,
            'reference_type' => Ppmp::class,
            'reference_id' => $ppmps['ENG']->ppmp_id,
            'item_id' => $printerInk->item_id,
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

        // Seed corresponding Alerts
        Alert::query()->create([
            'alert_type' => 'LOW_STOCK',
            'severity' => 'HIGH',
            'supplier_id' => $supIT->supplier_id,
            'office_id' => $gsoOffice->id,
            'alert_message' => 'Central Warehouse stock for Printer Ink is below critical safety threshold (2 units remaining, threshold 10).',
            'detected_at' => Carbon::parse('2026-09-08 10:00:00'),
            'status' => 'TRIGGERED',
        ]);

        Alert::query()->create([
            'alert_type' => 'DELAYED_DELIVERY',
            'severity' => 'MEDIUM',
            'purchase_order_id' => $poIT->purchase_order_id,
            'supplier_id' => $supIT->supplier_id,
            'office_id' => $eng->id,
            'alert_message' => 'ByteCore IT Solutions PO-2026-09-0001 delivery status pending vendor tracking confirmation.',
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
            'office_id' => $eng->id,
            'alert_message' => 'Price Deviation Flag: Quotation unit cost deviates +32.4% above statistical benchmark.',
            'detected_at' => Carbon::parse('2026-09-08 14:35:00'),
            'status' => 'TRIGGERED',
        ]);

        // Seed governance backups and yearly archive
        $this->call(GovernanceOpsDemoSeeder::class);

        $this->command?->info('================================================================');
        $this->command?->info('Category-Driven Transactional Data Refresh Completed Successfully!');
        $this->command?->info('================================================================');
    }
}
