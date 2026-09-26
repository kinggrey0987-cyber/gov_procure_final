<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryVerification;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Item;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PublicDisclosure;
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
use Illuminate\Support\Facades\Storage;

class MultiYearHistoricalProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $execUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))->first();
        $gsoUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))->first();
        $procUser = User::query()->whereHas('role', fn ($q) => $q->where('code', 'PROC_OFFICER'))->first();

        if (! $execUser || ! $gsoUser || ! $procUser) {
            $this->command?->warn('MultiYearHistoricalProcurementSeeder skipped: Essential role users not found.');
            return;
        }

        $officeDefs = [
            'MAYOR_OFFICE' => ['name' => "Municipal Mayor's Office", 'description' => 'Executive office overseeing municipal operations.'],
            'MPDO' => ['name' => 'Municipal Planning and Development Office', 'description' => 'Planning and development office for municipal programs and PPMP ownership.'],
            'MSWDO' => ['name' => 'Municipal Social Welfare and Development Office', 'description' => 'Social welfare and development office for municipal services.'],
            'GSO' => ['name' => 'General Services Office', 'description' => 'Handles inventory, deliveries, and supply distribution.'],
            'ENG' => ['name' => 'Municipal Engineering Office', 'description' => 'Municipal infrastructure and engineering office.'],
            'MHO' => ['name' => 'Municipal Health Office', 'description' => 'Public healthcare and community medical services.'],
        ];

        $offices = collect();
        foreach ($officeDefs as $code => $info) {
            $offices[$code] = Office::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $info['name'], 'description' => $info['description']]
            );
        }

        $officeUsers = [
            'MAYOR_OFFICE' => User::query()->where('office_id', $offices['MAYOR_OFFICE']->id)->first() ?? $execUser,
            'MPDO' => User::query()->where('office_id', $offices['MPDO']->id)->first() ?? $execUser,
            'MSWDO' => User::query()->where('office_id', $offices['MSWDO']->id)->first() ?? $execUser,
            'GSO' => User::query()->where('office_id', $offices['GSO']->id)->first() ?? $gsoUser,
            'ENG' => User::query()->where('office_id', $offices['ENG']->id)->first() ?? $execUser,
            'MHO' => User::query()->where('office_id', $offices['MHO']->id)->first() ?? $execUser,
        ];

        $supplier1 = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Metro Office Depot Trading'],
            [
                'contact_person' => 'Mina Solis',
                'email' => 'orders@metroofficedepot.example',
                'phone' => '09171234567',
                'address' => 'Poblacion District, City Proper',
                'status' => 'ACTIVE',
            ]
        );
        $supplier1->categories()->syncWithoutDetaching([1]);

        $supplier2 = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'ByteCore IT Solutions & Electronics'],
            [
                'contact_person' => 'Engr. Ronald Tan',
                'email' => 'enterprise@bytecore-it.example',
                'phone' => '09182345678',
                'address' => 'Cyberzone Park, Technology Corridor',
                'status' => 'ACTIVE',
            ]
        );
        $supplier2->categories()->syncWithoutDetaching([2]);

        $supplier3 = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'CleanTech Sanitation & Hygiene Supplies'],
            [
                'contact_person' => 'Carmela Gomez',
                'email' => 'institutional@cleantech-supplies.example',
                'phone' => '09193456789',
                'address' => 'San Isidro Industrial Estate, Metro',
                'status' => 'ACTIVE',
            ]
        );
        $supplier3->categories()->syncWithoutDetaching([3]);

        $supplier4 = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Apex Maintenance & Hardware Depot'],
            [
                'contact_person' => 'Danilo Cruz',
                'email' => 'supply@apexhardware.example',
                'phone' => '09204567890',
                'address' => 'Highway 4 Construction Hub, West District',
                'status' => 'ACTIVE',
            ]
        );
        $supplier4->categories()->syncWithoutDetaching([4]);

        $supplier5 = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Grand Alliance Commercial Corp.'],
            [
                'contact_person' => 'Beatrice Lim',
                'email' => 'tenders@grandalliance.example',
                'phone' => '09215678901',
                'address' => 'Commercial Center Blvd, East District',
                'status' => 'ACTIVE',
            ]
        );
        $supplier5->categories()->syncWithoutDetaching([1, 3]);

        $suppliers = [$supplier1, $supplier2, $supplier3, $supplier4, $supplier5];

        $items = Item::query()->with('specifications')->get()->keyBy('item_name');
        if ($items->isEmpty()) {
            $this->command?->warn('MultiYearHistoricalProcurementSeeder skipped: No items found in item catalog.');
            return;
        }

        $this->command?->info('Seeding FY 2024 Expanded Historical Procurement Dataset (50 PPMPs, 50 SRs, 50 PRs, 50 POs)...');
        $this->seedFiscalYear(
            year: 2024,
            execUser: $execUser,
            gsoUser: $gsoUser,
            procUser: $procUser,
            officeUsers: $officeUsers,
            offices: $offices,
            suppliers: $suppliers,
            items: $items,
            priceScale: 0.89, // 2024 baseline (~11% lower than 2026)
        );

        $this->command?->info('Seeding FY 2025 Expanded Historical Procurement Dataset (50 PPMPs, 50 SRs, 50 PRs, 50 POs)...');
        $this->seedFiscalYear(
            year: 2025,
            execUser: $execUser,
            gsoUser: $gsoUser,
            procUser: $procUser,
            officeUsers: $officeUsers,
            offices: $offices,
            suppliers: $suppliers,
            items: $items,
            priceScale: 0.95, // 2025 baseline (~5% lower than 2026)
        );

        $this->command?->info('MultiYearHistoricalProcurementSeeder completed successfully.');
    }

    /**
     * @param array<string, User> $officeUsers
     * @param \Illuminate\Support\Collection<string, Office> $offices
     * @param array<int, Supplier> $suppliers
     * @param \Illuminate\Database\Eloquent\Collection<string, Item> $items
     */
    private function seedFiscalYear(
        int $year,
        User $execUser,
        User $gsoUser,
        User $procUser,
        array $officeUsers,
        $offices,
        array $suppliers,
        $items,
        float $priceScale
    ): void {
        DB::transaction(function () use ($year, $execUser, $gsoUser, $procUser, $officeUsers, $offices, $suppliers, $items, $priceScale) {
            // -------------------------------------------------------------------------
            // 1. Define 50 Distinct, Authentic PPMP Configurations Across 6 Departments
            // -------------------------------------------------------------------------
            $ppmpDefs = $this->getPpmpDefinitions($year);

            $seededPpmps = []; // keyed by ppmp index 1..50
            $ppmpItemsByPlan = []; // keyed by ppmp index => array of ['ppmp_item' => ..., 'item' => ..., 'unit_cost' => ...]

            foreach ($ppmpDefs as $index => $def) {
                $officeCode = $def['office_code'];
                $office = $offices->get($officeCode);
                if (! $office) {
                    continue;
                }

                $user = $officeUsers[$officeCode] ?? $execUser;
                $monthPrepared = $def['prep_month'];
                $dayPrepared = min(28, 5 + ($index % 20));

                $datePrepared = Carbon::create($year, $monthPrepared, $dayPrepared);
                $dateApproved = $datePrepared->copy()->addDays(5);

                $ppmp = Ppmp::query()->updateOrCreate(
                    [
                        'office_id' => $office->id,
                        'fiscal_year' => $year,
                        'remarks' => $def['title'],
                    ],
                    [
                        'prepared_by' => $user->id,
                        'approved_by' => $execUser->id,
                        'fund_source' => $def['fund_source'],
                        'status' => $year < 2026 ? 'CLOSED' : 'APPROVED',
                        'total_estimated_budget' => 0,
                        'date_prepared' => $datePrepared,
                        'date_approved' => $dateApproved,
                        'created_at' => $datePrepared,
                        'updated_at' => $dateApproved,
                    ]
                );

                $totalBudget = 0;
                $seededItems = [];

                foreach ($def['items'] as $itemDefIndex => $itemDef) {
                    $catalogName = match ($itemDef['name']) {
                        'Bond Paper' => (($index + $itemDefIndex) % 2 === 0) ? 'Bond Paper, A4 (80 GSM)' : 'Bond Paper, Legal (80 GSM)',
                        'Ballpen' => match (($index + $itemDefIndex) % 3) {
                            0 => 'Ballpen, Black',
                            1 => 'Ballpen, Blue',
                            default => 'Ballpen, Red',
                        },
                        'Folder' => (($index + $itemDefIndex) % 2 === 0) ? 'Folder, Tagboard (Legal size)' : 'Folder, Tagboard (A4 size)',
                        'Flash Drive' => (($index + $itemDefIndex) % 2 === 0) ? 'Flash Drive, 32GB' : 'Flash Drive, 64GB',
                        'Printer Ink' => (($index + $itemDefIndex) % 2 === 0) ? 'Printer Ink, Black' : 'Printer Ink, Color Set',
                        'Trash Bag' => (($index + $itemDefIndex) % 2 === 0) ? 'Trash Bag, Black XL' : 'Trash Bag, Yellow Biohazard XL',
                        'Mouse' => 'Mouse, USB Optical',
                        'Rubbing Alcohol' => 'Rubbing Alcohol (70%)',
                        default => $itemDef['name'],
                    };

                    $itemModel = $items->get($catalogName) ?? $items->get($itemDef['name']) ?? $items->first();
                    if (! $itemModel) {
                        continue;
                    }

                    $unitCost = round($itemDef['base_cost'] * $priceScale, 2);
                    $qty = $itemDef['qty'];
                    $estBudget = round($qty * $unitCost, 2);
                    $totalBudget += $estBudget;

                    $specs = $itemModel->specifications->pluck('specification')->all();
                    $spec = ! empty($specs)
                        ? $specs[($index + $itemDefIndex) % count($specs)]
                        : sprintf('Standard %s specification', $itemModel->item_name);

                    $ppmpItem = PpmpItem::query()->updateOrCreate(
                        [
                            'ppmp_id' => $ppmp->ppmp_id,
                            'item_id' => $itemModel->item_id,
                        ],
                        [
                            'item_category_id' => $itemModel->item_category_id,
                            'quantity' => $qty,
                            'unit_cost' => $unitCost,
                            'estimated_budget' => $estBudget,
                            'procurement_schedule' => $itemDef['sched'],
                            'remarks' => $spec,
                            'created_at' => $datePrepared,
                            'updated_at' => $dateApproved,
                        ]
                    );

                    $seededItems[] = [
                        'ppmp_item' => $ppmpItem,
                        'item' => $itemModel,
                        'unit_cost' => $unitCost,
                    ];
                }

                $ppmp->update(['total_estimated_budget' => $totalBudget]);
                $seededPpmps[$index] = $ppmp;
                $ppmpItemsByPlan[$index] = $seededItems;
            }

            // -------------------------------------------------------------------------
            // 2. Generate 50 Complete Closed Cycles (SR -> PR -> PO -> Delivery -> Dist)
            //    Evenly distributed: 4 cycles/month (Jan-Oct) + 5 cycles (Nov) + 5 cycles (Dec)
            // -------------------------------------------------------------------------
            $methods = ['SHOPPING', 'SMALL_VALUE_PROCUREMENT', 'SMALL_VALUE_PROCUREMENT', 'PUBLIC_BIDDING'];
            $priorityLevels = ['NORMAL', 'NORMAL', 'NORMAL', 'URGENT'];

            for ($i = 1; $i <= 50; $i++) {
                // Determine calendar month (1..12)
                if ($i <= 40) {
                    $month = (int) ceil($i / 4); // 4 per month for months 1..10
                    $subIndex = ($i - 1) % 4;
                    $day = 5 + ($subIndex * 6); // days 5, 11, 17, 23
                } elseif ($i <= 45) {
                    $month = 11; // 5 in November
                    $subIndex = $i - 41;
                    $day = 3 + ($subIndex * 5); // days 3, 8, 13, 18, 23
                } else {
                    $month = 12; // 5 in December
                    $subIndex = $i - 46;
                    $day = 1 + ($subIndex * 3); // days 1, 4, 7, 10, 13
                }

                // Match to the corresponding PPMP
                $ppmpIndex = $i;
                $ppmp = $seededPpmps[$ppmpIndex] ?? $seededPpmps[1];
                $planDef = $ppmpDefs[$ppmpIndex] ?? $ppmpDefs[1];
                $officeCode = $planDef['office_code'];
                $office = $offices->get($officeCode);
                $officeUser = $officeUsers[$officeCode] ?? $execUser;
                $availableItems = $ppmpItemsByPlan[$ppmpIndex] ?? [];

                if (! $office || empty($availableItems)) {
                    continue;
                }

                // Timestamps properly anchored in the fiscal year
                $reqDate = Carbon::create($year, $month, min(20, $day));
                $neededDate = $reqDate->copy()->addDays(12);
                $approvedDate = $reqDate->copy()->addDays(2);
                $prDate = $reqDate->copy()->addDays(3);
                $poDate = $reqDate->copy()->addDays(5);
                $delDate = $poDate->copy()->addDays(6);
                $distDate = $delDate->copy()->addDays(2);

                $category = $planDef['category'];
                $categoryId = match ($category) {
                    'Office Supplies & Consumables', 'Office Supplies' => 1,
                    'IT Equipment' => 2,
                    'Janitorial Supplies', 'Medical & Safety', 'Cleaning Supplies' => 3,
                    'Hardware & Maintenance', 'Maintenance Supplies' => 4,
                    default => 1,
                };

                // Select supplier realistically based on accredited commodity category
                if ($categoryId === 2) {
                    $supplier = $suppliers[1]; // ByteCore IT Solutions
                } elseif ($categoryId === 3) {
                    $supplier = ($i % 2 === 0) ? $suppliers[2] : $suppliers[4]; // CleanTech or Grand Alliance
                } elseif ($categoryId === 4) {
                    $supplier = $suppliers[3]; // Apex Maintenance Depot
                } else {
                    $supplier = ($i % 2 === 0) ? $suppliers[0] : $suppliers[4]; // Metro Office Depot or Grand Alliance
                }

                $method = $methods[$i % count($methods)];
                $priority = $priorityLevels[$i % count($priorityLevels)];

                // 2.1 Supply Request (SR)
                $requestCode = sprintf('SR-%s-%d-%02d-%04d', $officeCode, $year, $month, $i);

                $supplyRequest = SupplyRequest::query()->updateOrCreate(
                    ['request_code' => $requestCode],
                    [
                        'office_id' => $office->id,
                        'requested_by' => $officeUser->id,
                        'ppmp_id' => $ppmp->ppmp_id,
                        'item_category_id' => $categoryId,
                        'request_date' => $reqDate,
                        'needed_by_date' => $neededDate,
                        'request_category' => $category,
                        'purpose' => sprintf('%s Requisition for %s (FY %d Batch %02d).', $officeCode, $planDef['title'], $year, $i),
                        'priority_level' => $priority,
                        'status' => 'COMPLETED',
                        'approved_by' => $gsoUser->id,
                        'approved_at' => $approvedDate,
                        'created_at' => $reqDate,
                        'updated_at' => $distDate,
                    ]
                );

                $srItems = [];
                foreach ($availableItems as $itemData) {
                    $pItem = $itemData['ppmp_item'];
                    $itemObj = $itemData['item'];
                    $reqQty = max(1, (int) round($pItem->quantity / 2));

                    $srItem = SupplyRequestItem::query()->updateOrCreate(
                        [
                            'supply_request_id' => $supplyRequest->supply_request_id,
                            'item_id' => $itemObj->item_id,
                        ],
                        [
                            'ppmp_item_id' => $pItem->ppmp_item_id,
                            'item_name_snapshot' => $itemObj->item_name,
                            'quantity_requested' => $reqQty,
                            'created_at' => $reqDate,
                            'updated_at' => $reqDate,
                        ]
                    );

                    $srItems[] = [
                        'sr_item' => $srItem,
                        'item' => $itemObj,
                        'unit_cost' => $itemData['unit_cost'],
                        'qty' => $reqQty,
                    ];
                }

                // 2.2 Purchase Request (PR)
                $prNumber = sprintf('PR-%d-%02d-%04d', $year, $month, $i);

                $pr = PurchaseRequest::query()->updateOrCreate(
                    ['pr_number' => $prNumber],
                    [
                        'supply_request_id' => $supplyRequest->supply_request_id,
                        'office_id' => $office->id,
                        'requested_by' => $officeUser->id,
                        'status' => 'COMPLETED',
                        'remarks' => sprintf('Official BAC approved PR for %s (%s, FY %d).', $officeCode, $planDef['title'], $year),
                        'created_at' => $prDate,
                        'updated_at' => $poDate,
                    ]
                );

                foreach ($srItems as $srItemData) {
                    PurchaseRequestItem::query()->updateOrCreate(
                        [
                            'purchase_request_id' => $pr->purchase_request_id,
                            'item_id' => $srItemData['item']->item_id,
                        ],
                        [
                            'quantity_requested' => $srItemData['qty'],
                            'remarks' => sprintf('Allocation for %s under %s.', $srItemData['item']->item_name, $prNumber),
                            'created_at' => $prDate,
                            'updated_at' => $poDate,
                        ]
                    );
                }

                // 2.3 Purchase Order (PO)
                $poNumber = sprintf('PO-%d-%02d-%04d', $year, $month, $i);
                $poTotal = 0;

                $po = PurchaseOrder::query()->updateOrCreate(
                    ['po_number' => $poNumber],
                    [
                        'purchase_request_id' => $pr->purchase_request_id,
                        'supply_request_id' => $supplyRequest->supply_request_id,
                        'supplier_id' => $supplier->supplier_id,
                        'created_by' => $procUser->id,
                        'approved_by' => $procUser->id,
                        'procurement_method' => $method,
                        'status' => 'COMPLETED',
                        'expected_delivery_date' => $delDate,
                        'date_approved' => $poDate,
                        'total_amount' => 0,
                        'terms_notes' => 'Standard 30-day payment terms upon COA inspection and acceptance.',
                        'remarks' => sprintf('Delivered Purchase Order for %s (%s, FY %d).', $officeCode, $planDef['title'], $year),
                        'created_at' => $poDate,
                        'updated_at' => $delDate,
                    ]
                );

                $poItemsList = [];
                foreach ($srItems as $srItemData) {
                    $sItem = $srItemData['sr_item'];
                    $itObj = $srItemData['item'];
                    $lineTotal = round($srItemData['qty'] * $srItemData['unit_cost'], 2);
                    $poTotal += $lineTotal;

                    $poItem = PurchaseOrderItem::query()->updateOrCreate(
                        [
                            'purchase_order_id' => $po->purchase_order_id,
                            'supply_request_item_id' => $sItem->supply_request_item_id,
                        ],
                        [
                            'item_id' => $itObj->item_id,
                            'item_name_snapshot' => $itObj->item_name,
                            'specification' => (string) ($sItem->ppmpItem?->remarks ?? $itObj->specifications->first()?->specification ?? sprintf('%s - Standard Certified Grade (%s)', $itObj->item_name, $itObj->default_unit)),
                            'quantity_ordered' => $srItemData['qty'],
                            'unit' => $itObj->default_unit,
                            'unit_price' => $srItemData['unit_cost'],
                            'total_price' => $lineTotal,
                            'created_at' => $poDate,
                            'updated_at' => $poDate,
                        ]
                    );

                    $poItemsList[] = [
                        'po_item' => $poItem,
                        'item' => $itObj,
                        'qty' => $srItemData['qty'],
                        'unit_cost' => $srItemData['unit_cost'],
                    ];
                }

                $po->update(['total_amount' => $poTotal]);

                // 2.4 Delivery & Verification (IAR)
                $deliveryCode = sprintf('DEL-%d-%02d-%04d', $year, $month, $i);
                $drNumber = sprintf('DR-%d-%02d-%04d', $year, $month, $i);
                $siNumber = sprintf('SI-%d-%02d-%04d', $year, $month, $i);

                $delivery = Delivery::query()->updateOrCreate(
                    ['delivery_code' => $deliveryCode],
                    [
                        'purchase_order_id' => $po->purchase_order_id,
                        'supplier_id' => $supplier->supplier_id,
                        'actual_delivery_date' => $delDate,
                        'expected_arrival_date' => $delDate,
                        'delivery_receipt_no' => $drNumber,
                        'sales_invoice_no' => $siNumber,
                        'sales_invoice_date' => $delDate,
                        'sales_invoice_amount' => $poTotal,
                        'received_by' => $gsoUser->id,
                        'receipt_status' => 'VERIFIED',
                        'remarks' => sprintf('Full delivery received, verified, and accepted by GSO Property for FY %d.', $year),
                        'created_at' => $delDate,
                        'updated_at' => $delDate,
                    ]
                );

                foreach ($poItemsList as $poLine) {
                    DeliveryItem::query()->updateOrCreate(
                        [
                            'delivery_id' => $delivery->delivery_id,
                            'purchase_order_item_id' => $poLine['po_item']->purchase_order_item_id,
                        ],
                        [
                            'expected_quantity' => $poLine['qty'],
                            'received_quantity' => $poLine['qty'],
                            'damaged_quantity' => 0,
                            'missing_quantity' => 0,
                            'item_condition' => 'GOOD',
                            'remarks' => 'Verified intact commercial packaging.',
                            'created_at' => $delDate,
                            'updated_at' => $delDate,
                        ]
                    );
                }

                DeliveryVerification::query()->updateOrCreate(
                    ['delivery_id' => $delivery->delivery_id],
                    [
                        'verified_by' => $gsoUser->id,
                        'verification_date' => $delDate,
                        'verification_status' => 'APPROVED',
                        'remarks' => 'COA Inspection & Acceptance Report (IAR) verified complete and compliant.',
                        'created_at' => $delDate,
                        'updated_at' => $delDate,
                    ]
                );

                // 2.5 Distribution Record to Office
                $distCode = sprintf('DIST-%d-%02d-%04d', $year, $month, $i);

                $distribution = DistributionRecord::query()->updateOrCreate(
                    ['distribution_code' => $distCode],
                    [
                        'supply_request_id' => $supplyRequest->supply_request_id,
                        'delivery_id' => $delivery->delivery_id,
                        'office_id' => $office->id,
                        'prepared_by' => $gsoUser->id,
                        'received_by' => $officeUser->id,
                        'release_date' => $distDate,
                        'storage_location' => 'Main Warehouse Storage',
                        'status' => 'RECEIVED',
                        'notes' => sprintf('Issued and formally acknowledged by %s representative for FY %d.', $officeCode, $year),
                        'created_at' => $distDate,
                        'updated_at' => $distDate,
                    ]
                );

                foreach ($poItemsList as $poLine) {
                    DistributionItem::query()->updateOrCreate(
                        [
                            'distribution_record_id' => $distribution->distribution_record_id,
                            'item_id' => $poLine['item']->item_id,
                        ],
                        [
                            'received_by' => $officeUser->id,
                            'item_name_snapshot' => $poLine['item']->item_name,
                            'quantity_released' => $poLine['qty'],
                            'quantity_received' => $poLine['qty'],
                            'quantity_damaged' => 0,
                            'quantity_missing' => 0,
                            'receipt_condition' => 'GOOD',
                            'receipt_notes' => 'Acknowledged in good order and condition.',
                            'unit' => $poLine['item']->default_unit,
                            'remarks' => 'Issued against approved PPMP allocation.',
                            'created_at' => $distDate,
                            'updated_at' => $distDate,
                        ]
                    );
                }

                // 2.6 Audit Status History & Approval History
                $this->recordWorkflowHistories(
                    supplyRequest: $supplyRequest,
                    purchaseRequest: $pr,
                    purchaseOrder: $po,
                    delivery: $delivery,
                    distribution: $distribution,
                    officeUser: $officeUser,
                    gsoUser: $gsoUser,
                    procUser: $procUser,
                    reqDate: $reqDate,
                    approvedDate: $approvedDate,
                    prDate: $prDate,
                    poDate: $poDate,
                    delDate: $delDate,
                    distDate: $distDate
                );
            }

            // -------------------------------------------------------------------------
            // 3. Public Transparency Disclosures for the Fiscal Year
            // -------------------------------------------------------------------------
            $pubDate = Carbon::create($year, 12, 28);
            $yearPpmp = Ppmp::query()->where('fiscal_year', $year)->first();
            $yearPo = PurchaseOrder::query()->whereYear('created_at', $year)->first();
            $packageService = app(\App\Services\Executive\PublicDisclosurePackageService::class);

            if ($yearPpmp instanceof Ppmp) {
                $ppmpTitle = sprintf('FY %d Annual Procurement Transparency Package', $year);
                $reportPath = $this->writeDisclosureMainFile(
                    title: $ppmpTitle,
                    type: 'ANNUAL_PROCUREMENT_PLAN',
                    referenceType: Ppmp::class,
                    referenceId: (int) $yearPpmp->ppmp_id,
                );

                $disclosurePpmp = PublicDisclosure::query()->updateOrCreate(
                    ['title' => $ppmpTitle],
                    [
                        'type' => 'ANNUAL_PROCUREMENT_PLAN',
                        'reference_type' => Ppmp::class,
                        'reference_id' => (int) $yearPpmp->ppmp_id,
                        'description' => sprintf('Certified Annual Procurement & Physical Property Report for Fiscal Year %d in compliance with COA Circular & RA 9184.', $year),
                        'file_path' => $reportPath,
                        'status' => 'PUBLISHED',
                        'published_by' => $execUser->id,
                        'published_at' => $pubDate,
                        'created_at' => $pubDate,
                        'updated_at' => $pubDate,
                    ]
                );

                $this->syncDisclosureAttachments(
                    disclosure: $disclosurePpmp,
                    actor: $execUser,
                    attachments: [
                        [
                            'title' => sprintf('FY %d PPMP Annual Procurement Plan Matrix', $year),
                            'suffix' => sprintf('fy-%d-ppmp-matrix.csv', $year),
                            'body' => "fiscal_year,office_id,total_budget,status\n{$year},{$yearPpmp->office_id},{$yearPpmp->total_estimated_budget},APPROVED\n",
                        ],
                        [
                            'title' => sprintf('FY %d Executive Procurement Certification', $year),
                            'suffix' => sprintf('fy-%d-executive-certification.txt', $year),
                            'body' => "Republic of the Philippines\nMunicipality of GovProcure\n\nExecutive Certification of Compliance\nFiscal Year: {$year}\nSigned by Executive Officer\nStatus: APPROVED AND DISCLOSED\n",
                        ],
                    ],
                );

                try {
                    $packageService->ensureOfficialPackageAttachment($disclosurePpmp, $execUser);
                } catch (\Throwable $e) {
                    $packageService->syncSources($disclosurePpmp);
                }
            }

            if ($yearPo instanceof PurchaseOrder) {
                $poTitle = sprintf('FY %d Consolidated Purchase Order Award Summary', $year);
                $poReportPath = $this->writeDisclosureMainFile(
                    title: $poTitle,
                    type: 'PROCUREMENT_PACKAGE',
                    referenceType: PurchaseOrder::class,
                    referenceId: (int) $yearPo->purchase_order_id,
                );

                $disclosurePo = PublicDisclosure::query()->updateOrCreate(
                    ['title' => $poTitle],
                    [
                        'type' => 'PROCUREMENT_PACKAGE',
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => (int) $yearPo->purchase_order_id,
                        'description' => sprintf('Public transparency summary of all completed purchase orders and supplier disbursements for Fiscal Year %d.', $year),
                        'file_path' => $poReportPath,
                        'status' => 'PUBLISHED',
                        'published_by' => $execUser->id,
                        'published_at' => $pubDate->copy()->subDays(5),
                        'created_at' => $pubDate->copy()->subDays(5),
                        'updated_at' => $pubDate->copy()->subDays(5),
                    ]
                );

                $this->syncDisclosureAttachments(
                    disclosure: $disclosurePo,
                    actor: $execUser,
                    attachments: [
                        [
                            'title' => sprintf('FY %d Purchase Order Award Notice', $year),
                            'suffix' => sprintf('fy-%d-po-award-notice.txt', $year),
                            'body' => "Purchase Order Award Transparency Notice\nFiscal Year: {$year}\nPO Reference: {$yearPo->po_number}\nAmount: ₱".number_format((float) $yearPo->total_amount, 2)."\nStatus: COMPLETED\n",
                        ],
                    ],
                );

                try {
                    $packageService->ensureOfficialPackageAttachment($disclosurePo, $execUser);
                } catch (\Throwable $e) {
                    $packageService->syncSources($disclosurePo);
                }
            }

            // -------------------------------------------------------------------------
            // 4. Resolved Historical Claims for Realism
            // -------------------------------------------------------------------------
            $sampleDists = DistributionRecord::query()
                ->whereYear('created_at', $year)
                ->limit(2)
                ->get();

            foreach ($sampleDists as $idx => $sampleDist) {
                $sampleDistItem = $sampleDist->items()->first();
                if ($sampleDistItem) {
                    Claim::query()->updateOrCreate(
                        ['claim_code' => sprintf('CLM-%d-%05d', $year, $idx + 1)],
                        [
                            'distribution_record_id' => $sampleDist->distribution_record_id,
                            'distribution_item_id' => $sampleDistItem->distribution_item_id,
                            'claimed_by' => $sampleDist->received_by,
                            'office_id' => $sampleDist->office_id,
                            'claim_type' => $idx === 0 ? 'DAMAGED_ITEM' : 'DEFECTIVE_PACKAGING',
                            'claim_date' => Carbon::create($year, 3 + ($idx * 4), 14),
                            'claim_location' => 'Office Supply Receiving Station',
                            'quantity_disputed' => 1.0,
                            'quantity_damaged' => 1.0,
                            'quantity_missing' => 0,
                            'remarks' => sprintf('Disputed packaging inspected during FY %d Q%d fulfillment.', $year, $idx + 1),
                            'status' => 'RESOLVED',
                            'resolution_type' => 'REPLACEMENT_RELEASED',
                            'replacement_quantity' => 1.0,
                            'created_at' => Carbon::create($year, 3 + ($idx * 4), 14),
                            'updated_at' => Carbon::create($year, 3 + ($idx * 4), 16),
                        ]
                    );
                }
            }
        });
    }

    /**
     * Define 50 realistic, diverse PPMP allocations across the 6 municipal offices.
     *
     * @return array<int, array{office_code: string, prep_month: int, title: string, category: string, fund_source: FundSource, items: array<int, array{name: string, qty: int, sched: string, base_cost: float}>}>
     */
    private function getPpmpDefinitions(int $year): array
    {
        $fundSources = [
            FundSource::APPROPRIATION_ORDINANCE,
            FundSource::TRUST_FUND,
            FundSource::SPECIAL_PURPOSE_FUND,
            FundSource::IGI,
            FundSource::GAA_CURRENT,
        ];

        // 50 program blueprints covering all 6 municipal offices and all 12 calendar months
        $rawPrograms = [
            // Month 1 (January)
            1  => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Municipal Comprehensive Planning & Governance Consumables',
                   'items' => [['Bond Paper', 30, 'Q1', 950], ['Ballpen', 20, 'Q1', 300], ['Folder', 15, 'Q1', 420]]],
            2  => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Comprehensive Land Use & Spatial Planning Consumables',
                   'items' => [['Bond Paper', 25, 'Q1', 950], ['Logbook', 10, 'Q1', 160], ['Folder', 12, 'Q1', 420]]],
            3  => ['office_code' => 'MSWDO', 'cat' => 'Medical & Safety', 'title' => 'Community Social Welfare & Relief Operations Supplies',
                   'items' => [['Rubbing Alcohol', 30, 'Q1', 380], ['Safety Gloves', 25, 'Q1', 75], ['Detergent Powder', 20, 'Q1', 130]]],
            4  => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Rural Health Unit Clinical Sanitization & Protective Gear',
                   'items' => [['Rubbing Alcohol', 40, 'Q1', 380], ['Safety Gloves', 50, 'Q1', 75], ['Trash Bag', 30, 'Q1', 220]]],

            // Month 2 (February)
            5  => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Municipal Infrastructure Survey Drafting & Blueprint Documentation',
                   'items' => [['Bond Paper', 35, 'Q1', 950], ['Logbook', 15, 'Q1', 160], ['Staple Wire', 20, 'Q1', 180]]],
            6  => ['office_code' => 'MSWDO', 'cat' => 'Janitorial Supplies', 'title' => 'Community Center Custodial & Sanitation Logistics',
                   'items' => [['Detergent Powder', 45, 'Q1', 130], ['Trash Bag', 40, 'Q1', 220], ['Utility Cutter', 15, 'Q1', 95]]],
            7  => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Infrastructure Technical Working Committee Session Materials',
                   'items' => [['Bond Paper', 20, 'Q1', 950], ['Folder', 25, 'Q1', 420], ['Ballpen', 15, 'Q1', 300]]],
            8  => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Senior Citizens & PWD Assistance Program Registration Supplies',
                   'items' => [['Logbook', 20, 'Q1', 160], ['Ballpen', 25, 'Q1', 300], ['Folder', 30, 'Q1', 420]]],

            // Month 3 (March)
            9  => ['office_code' => 'MPDO', 'cat' => 'IT Equipment', 'title' => 'Geographic Information Systems (GIS) Mapping Consumables',
                   'items' => [['Flash Drive', 15, 'Q1', 480], ['Mouse', 10, 'Q1', 350], ['Printer Ink', 8, 'Q1', 650]]],
            10 => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Maternal & Neonatal Healthcare Community Outreach Supplies',
                   'items' => [['Rubbing Alcohol', 25, 'Q1', 380], ['Safety Gloves', 35, 'Q1', 75], ['Logbook', 12, 'Q1', 160]]],
            11 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Flood Mitigation & Drainage Inspection Project Documentation',
                   'items' => [['Bond Paper', 25, 'Q1', 950], ['Utility Cutter', 10, 'Q1', 95], ['Folder', 15, 'Q1', 420]]],
            12 => ['office_code' => 'MHO', 'cat' => 'Office Supplies', 'title' => 'Rural Health Archive Tagging, Records Audit & Registry Supplies',
                   'items' => [['Logbook', 25, 'Q1', 160], ['Staple Wire', 15, 'Q1', 180], ['Ballpen', 20, 'Q1', 300]]],

            // Month 4 (April)
            13 => ['office_code' => 'MPDO', 'cat' => 'Medical & Safety', 'title' => 'Disaster Risk Spatial Planning & Field Assessment Consumables',
                   'items' => [['Safety Gloves', 40, 'Q2', 75], ['Rubbing Alcohol', 30, 'Q2', 380], ['Flash Drive', 10, 'Q2', 480]]],
            14 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Annual Investment Program (AIP) Documentation Materials',
                   'items' => [['Bond Paper', 40, 'Q2', 950], ['Folder', 35, 'Q2', 420], ['Printer Ink', 10, 'Q2', 650]]],
            15 => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Child & Youth Development Welfare Assistance Supplies',
                   'items' => [['Bond Paper', 20, 'Q2', 950], ['Ballpen', 30, 'Q2', 300], ['Folder', 20, 'Q2', 420]]],
            16 => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Community Immunization Campaign & Clinical Hygiene Materials',
                   'items' => [['Rubbing Alcohol', 35, 'Q2', 380], ['Safety Gloves', 45, 'Q2', 75], ['Trash Bag', 25, 'Q2', 220]]],

            // Month 5 (May)
            17 => ['office_code' => 'ENG', 'cat' => 'IT Equipment', 'title' => 'Public Works CAD Design & Structural Calculation Materials',
                   'items' => [['Mouse', 12, 'Q2', 350], ['Flash Drive', 12, 'Q2', 480], ['Printer Ink', 10, 'Q2', 650]]],
            18 => ['office_code' => 'MSWDO', 'cat' => 'Janitorial Supplies', 'title' => 'Social Welfare Evacuation Facility Grounds & Sanitation Logistics',
                   'items' => [['Trash Bag', 60, 'Q2', 220], ['Safety Gloves', 30, 'Q2', 75], ['Detergent Powder', 35, 'Q2', 130]]],
            19 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Engineering Helpdesk, Citizen Inquiries & Permitting Supplies',
                   'items' => [['Bond Paper', 30, 'Q2', 950], ['Ballpen', 25, 'Q2', 300], ['Folder', 20, 'Q2', 420]]],
            20 => ['office_code' => 'MSWDO', 'cat' => 'Janitorial Supplies', 'title' => 'Crisis Center & Daycare Sanitation Facilities Support',
                   'items' => [['Detergent Powder', 30, 'Q2', 130], ['Rubbing Alcohol', 20, 'Q2', 380], ['Trash Bag', 25, 'Q2', 220]]],

            // Month 6 (June)
            21 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Socio-Economic Survey & Demographic Profiling Consumables',
                   'items' => [['Bond Paper', 30, 'Q2', 950], ['Ballpen', 40, 'Q2', 300], ['Folder', 30, 'Q2', 420]]],
            22 => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Tuberculosis & Infectious Disease Prevention Program Logistics',
                   'items' => [['Rubbing Alcohol', 40, 'Q2', 380], ['Safety Gloves', 40, 'Q2', 75], ['Logbook', 10, 'Q2', 160]]],
            23 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Bridge & Culvert Maintenance Inspection Consumables',
                   'items' => [['Logbook', 15, 'Q2', 160], ['Bond Paper', 20, 'Q2', 950], ['Utility Cutter', 8, 'Q2', 95]]],
            24 => ['office_code' => 'MHO', 'cat' => 'Office Supplies', 'title' => 'Clinical Supply Packaging, Sorting & Medical Dispatch Supplies',
                   'items' => [['Utility Cutter', 20, 'Q2', 95], ['Staple Wire', 25, 'Q2', 180], ['Logbook', 15, 'Q2', 160]]],

            // Month 7 (July)
            25 => ['office_code' => 'MPDO', 'cat' => 'IT Equipment', 'title' => 'Socio-Economic Database Infrastructure & GIS Technology Supplies',
                   'items' => [['Flash Drive', 15, 'Q3', 480], ['Mouse', 8, 'Q3', 350], ['Printer Ink', 8, 'Q3', 650]]],
            26 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Community-Based Monitoring System (CBMS) Field Supplies',
                   'items' => [['Bond Paper', 35, 'Q3', 950], ['Ballpen', 35, 'Q3', 300], ['Folder', 25, 'Q3', 420]]],
            27 => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Sustainable Livelihood & Community Skills Training Consumables',
                   'items' => [['Bond Paper', 25, 'Q3', 950], ['Folder', 30, 'Q3', 420], ['Ballpen', 20, 'Q3', 300]]],
            28 => ['office_code' => 'MHO', 'cat' => 'Janitorial Supplies', 'title' => 'Barangay Health Stations Cleanliness & Disinfection Logistics',
                   'items' => [['Detergent Powder', 40, 'Q3', 130], ['Rubbing Alcohol', 30, 'Q3', 380], ['Trash Bag', 35, 'Q3', 220]]],

            // Month 8 (August)
            29 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Municipal Water System Expansion Field Survey Materials',
                   'items' => [['Bond Paper', 25, 'Q3', 950], ['Logbook', 12, 'Q3', 160], ['Staple Wire', 15, 'Q3', 180]]],
            30 => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Inter-Agency Social Services Coordination Consumables',
                   'items' => [['Bond Paper', 25, 'Q3', 950], ['Folder', 20, 'Q3', 420], ['Ballpen', 20, 'Q3', 300]]],
            31 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Public Parks & Municipal Infrastructure Development Documentation',
                   'items' => [['Bond Paper', 25, 'Q3', 950], ['Folder', 20, 'Q3', 420], ['Flash Drive', 8, 'Q3', 480]]],
            32 => ['office_code' => 'MSWDO', 'cat' => 'Medical & Safety', 'title' => 'Emergency Relief Aid Stockpiling for Calamity Response',
                   'items' => [['Safety Gloves', 35, 'Q3', 75], ['Rubbing Alcohol', 25, 'Q3', 380], ['Detergent Powder', 25, 'Q3', 130]]],

            // Month 9 (September)
            33 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Municipal Monitoring & Evaluation System Reports Supplies',
                   'items' => [['Bond Paper', 30, 'Q3', 950], ['Folder', 25, 'Q3', 420], ['Printer Ink', 8, 'Q3', 650]]],
            34 => ['office_code' => 'MHO', 'cat' => 'Office Supplies', 'title' => 'Clinical Record-Keeping & Vital Statistics Data Registry',
                   'items' => [['Logbook', 25, 'Q3', 160], ['Bond Paper', 20, 'Q3', 950], ['Ballpen', 30, 'Q3', 300]]],
            35 => ['office_code' => 'ENG', 'cat' => 'Janitorial Supplies', 'title' => 'Engineering Motorpool & Heavy Equipment Maintenance Sanitation',
                   'items' => [['Trash Bag', 35, 'Q3', 220], ['Detergent Powder', 30, 'Q3', 130], ['Safety Gloves', 25, 'Q3', 75]]],
            36 => ['office_code' => 'MHO', 'cat' => 'IT Equipment', 'title' => 'Health Center Telehealth Terminal & Clinic IT Maintenance',
                   'items' => [['Mouse', 10, 'Q3', 350], ['Flash Drive', 12, 'Q3', 480], ['Toner Cartridge', 3, 'Q3', 2400]]],

            // Month 10 (October)
            37 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Mid-Term Comprehensive Development Plan Review Consumables',
                   'items' => [['Bond Paper', 35, 'Q4', 950], ['Folder', 25, 'Q4', 420], ['Ballpen', 20, 'Q4', 300]]],
            38 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Development Project Feasibility & Assessment Documentation',
                   'items' => [['Bond Paper', 25, 'Q4', 950], ['Folder', 20, 'Q4', 420], ['Logbook', 10, 'Q4', 160]]],
            39 => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Women Empowerment & Gender and Development (GAD) Plan Materials',
                   'items' => [['Bond Paper', 25, 'Q4', 950], ['Ballpen', 25, 'Q4', 300], ['Folder', 20, 'Q4', 420]]],
            40 => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Dental Health & Oral Hygiene Community Outreach Consumables',
                   'items' => [['Rubbing Alcohol', 30, 'Q4', 380], ['Safety Gloves', 40, 'Q4', 75], ['Trash Bag', 20, 'Q4', 220]]],

            // Month 11 (November)
            41 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Municipal Infrastructure Completion Reports & Blueprint Archiving',
                   'items' => [['Bond Paper', 35, 'Q4', 950], ['Folder', 30, 'Q4', 420], ['Staple Wire', 15, 'Q4', 180]]],
            42 => ['office_code' => 'MSWDO', 'cat' => 'Office Supplies', 'title' => 'Social Welfare Beneficiary Validation & Case Folder Consumables',
                   'items' => [['Logbook', 20, 'Q4', 160], ['Utility Cutter', 15, 'Q4', 95], ['Ballpen', 20, 'Q4', 300]]],
            43 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Barangay Infrastructure Assistance & Field Survey Supplies',
                   'items' => [['Bond Paper', 30, 'Q4', 950], ['Ballpen', 25, 'Q4', 300], ['Folder', 25, 'Q4', 420]]],
            44 => ['office_code' => 'MSWDO', 'cat' => 'Medical & Safety', 'title' => 'Year-End Calamity Relief & Vulnerable Sector Assistance Logistics',
                   'items' => [['Rubbing Alcohol', 25, 'Q4', 380], ['Safety Gloves', 30, 'Q4', 75], ['Detergent Powder', 25, 'Q4', 130]]],
            45 => ['office_code' => 'MHO', 'cat' => 'Office Supplies', 'title' => 'Community Nutrition Program & Supplementary Feeding Documentation',
                   'items' => [['Bond Paper', 20, 'Q4', 950], ['Logbook', 15, 'Q4', 160], ['Ballpen', 20, 'Q4', 300]]],

            // Month 12 (December)
            46 => ['office_code' => 'MPDO', 'cat' => 'IT Equipment', 'title' => 'Annual Planning Spatial Database Consolidation & Media Storage',
                   'items' => [['Flash Drive', 15, 'Q4', 480], ['Mouse', 8, 'Q4', 350], ['HP 680 Tri-Color Ink', 5, 'Q4', 850]]],
            47 => ['office_code' => 'ENG', 'cat' => 'Office Supplies', 'title' => 'Subdivision & Commercial Building Code Permitting Documentation',
                   'items' => [['Bond Paper', 30, 'Q4', 950], ['Folder', 25, 'Q4', 420], ['Staple Wire', 15, 'Q4', 180]]],
            48 => ['office_code' => 'MHO', 'cat' => 'Janitorial Supplies', 'title' => 'Year-End Health Facilities Deep Cleaning & Disinfection Consumables',
                   'items' => [['Detergent Powder', 40, 'Q4', 130], ['Trash Bag', 45, 'Q4', 220], ['Rubbing Alcohol', 25, 'Q4', 380]]],
            49 => ['office_code' => 'MPDO', 'cat' => 'Office Supplies', 'title' => 'Annual Development Accomplishment Report & Planning Assembly Supplies',
                   'items' => [['Bond Paper', 40, 'Q4', 950], ['Folder', 30, 'Q4', 420], ['Ballpen', 30, 'Q4', 300]]],
            50 => ['office_code' => 'MHO', 'cat' => 'Medical & Safety', 'title' => 'Municipal Health Office Annual Sanitary & First-Aid Replenishment',
                   'items' => [['Rubbing Alcohol', 35, 'Q4', 380], ['Safety Gloves', 45, 'Q4', 75], ['Trash Bag', 30, 'Q4', 220]]],
        ];

        $result = [];
        foreach ($rawPrograms as $id => $prog) {
            $month = (int) ceil($id / 4);
            if ($id > 48) {
                $month = 12;
            }

            $fundSource = $fundSources[($id - 1) % count($fundSources)];

            $items = [];
            foreach ($prog['items'] as $item) {
                $items[] = [
                    'name' => $item[0],
                    'qty' => $item[1],
                    'sched' => $item[2],
                    'base_cost' => (float) $item[3],
                ];
            }

            $result[$id] = [
                'office_code' => $prog['office_code'],
                'prep_month' => min(12, $month),
                'title' => sprintf('FY %d %s', $year, $prog['title']),
                'category' => $prog['cat'],
                'fund_source' => $fundSource,
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * Record comprehensive status histories and approvals for the transaction flow.
     */
    private function recordWorkflowHistories(
        SupplyRequest $supplyRequest,
        PurchaseRequest $purchaseRequest,
        PurchaseOrder $purchaseOrder,
        Delivery $delivery,
        DistributionRecord $distribution,
        User $officeUser,
        User $gsoUser,
        User $procUser,
        Carbon $reqDate,
        Carbon $approvedDate,
        Carbon $prDate,
        Carbon $poDate,
        Carbon $delDate,
        Carbon $distDate
    ): void {
        // Supply Request Audit
        StatusHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'old_status' => 'DRAFT',
            'new_status' => 'SUBMITTED',
            'changed_by' => $officeUser->id,
            'remarks' => 'Submitted by requesting office.',
            'changed_at' => $reqDate,
            'created_at' => $reqDate,
        ]);
        StatusHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'old_status' => 'SUBMITTED',
            'new_status' => 'COMPLETED',
            'changed_by' => $gsoUser->id,
            'remarks' => 'Requisition fulfilled and supplies issued.',
            'changed_at' => $distDate,
            'created_at' => $distDate,
        ]);
        ApprovalHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => $supplyRequest->supply_request_id,
            'approved_by' => $gsoUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Approved by GSO Officer against approved PPMP.',
            'approved_at' => $approvedDate,
            'created_at' => $approvedDate,
            'updated_at' => $approvedDate,
        ]);

        // Purchase Request Audit
        StatusHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => $purchaseRequest->purchase_request_id,
            'old_status' => 'DRAFT',
            'new_status' => 'OPEN',
            'changed_by' => $officeUser->id,
            'remarks' => 'Purchase request endorsed to BAC Secretariat.',
            'changed_at' => $prDate,
            'created_at' => $prDate,
        ]);
        StatusHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => $purchaseRequest->purchase_request_id,
            'old_status' => 'OPEN',
            'new_status' => 'COMPLETED',
            'changed_by' => $procUser->id,
            'remarks' => 'Procurement awarded and verified.',
            'changed_at' => $distDate,
            'created_at' => $distDate,
        ]);
        ApprovalHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => $purchaseRequest->purchase_request_id,
            'approved_by' => $procUser->id,
            'status' => 'APPROVED',
            'remarks' => 'Approved for purchase ordering by BAC Officer.',
            'approved_at' => $poDate,
            'created_at' => $poDate,
            'updated_at' => $poDate,
        ]);

        // Purchase Order Audit
        StatusHistory::query()->create([
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $purchaseOrder->purchase_order_id,
            'old_status' => 'DRAFT',
            'new_status' => 'COMPLETED',
            'changed_by' => $procUser->id,
            'remarks' => 'Purchase order delivered and inspected.',
            'changed_at' => $distDate,
            'created_at' => $distDate,
        ]);

        // Delivery Audit
        StatusHistory::query()->create([
            'reference_type' => Delivery::class,
            'reference_id' => $delivery->delivery_id,
            'old_status' => 'PENDING',
            'new_status' => 'RECEIVED',
            'changed_by' => $gsoUser->id,
            'remarks' => 'Supplier items physically accepted and verified.',
            'changed_at' => $delDate,
            'created_at' => $delDate,
        ]);

        // Distribution Audit
        StatusHistory::query()->create([
            'reference_type' => DistributionRecord::class,
            'reference_id' => $distribution->distribution_record_id,
            'old_status' => 'PENDING',
            'new_status' => 'RECEIVED',
            'changed_by' => $officeUser->id,
            'remarks' => 'Supplies officially acknowledged by receiving office.',
            'changed_at' => $distDate,
            'created_at' => $distDate,
        ]);
    }

    private function publicDisk(): string
    {
        return (string) config('filesystems.public_disk', 'public');
    }

    private function putSafely(string $path, string $content): void
    {
        $primaryDisk = $this->publicDisk();
        try {
            Storage::disk($primaryDisk)->put($path, $content);
            return;
        } catch (\Throwable) {
            if ($primaryDisk !== 'public') {
                try {
                    Storage::disk('public')->put($path, $content);
                    return;
                } catch (\Throwable) {
                    // Fallback
                }
            }
            Storage::disk('local')->put($path, $content);
        }
    }

    private function writeDisclosureMainFile(string $title, string $type, string $referenceType, int $referenceId): string
    {
        $slug = strtolower(str_replace(' ', '-', trim($title)));
        $path = sprintf('reports/public_disclosures/%s.txt', $slug);

        $this->putSafely(
            $path,
            implode("\n", [
                'GovSupply Connect Public Disclosure Report',
                'Title: '.$title,
                'Type: '.$type,
                'Reference Type: '.$referenceType,
                'Reference ID: '.$referenceId,
                'Generated At: '.Carbon::now()->toIso8601String(),
            ])."\n"
        );

        return $path;
    }

    /**
     * @param  array<int, array{title: string, suffix: string, body: string}>  $attachments
     */
    private function syncDisclosureAttachments(PublicDisclosure $disclosure, User $actor, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = sprintf(
                'documents/public_disclosures/%d/%s',
                (int) $disclosure->disclosure_id,
                (string) $attachment['suffix'],
            );

            $this->putSafely($path, (string) $attachment['body']);

            Document::query()->updateOrCreate(
                [
                    'related_module' => 'public_disclosures',
                    'related_reference_id' => (int) $disclosure->disclosure_id,
                    'title' => (string) $attachment['title'],
                ],
                [
                    'uploaded_by' => (int) $actor->id,
                    'file_name' => basename($path),
                    'document_kind' => 'PUBLIC_DISCLOSURE_ATTACHMENT',
                    'file_path' => $path,
                    'uploaded_at' => now(),
                ]
            );
        }
    }
}
