<?php

namespace Database\Seeders;

use App\Enums\FundSource;
use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryVerification;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\Document;
use App\Models\Item;
use App\Models\ItemCategory;
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
            'MPDO' => ['name' => 'Municipal Planning and Development Office', 'description' => 'Planning and development office for municipal programs and PPMP ownership.'],
            'MSWDO' => ['name' => 'Municipal Social Welfare and Development Office', 'description' => 'Social welfare and development office for municipal services.'],
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

        $mpdoUser = User::query()->where('office_id', $offices['MPDO']->id)->first() ?? $execUser;
        $mswdoUser = User::query()->where('office_id', $offices['MSWDO']->id)->first() ?? $execUser;
        $engUser = User::query()->where('office_id', $offices['ENG']->id)->first() ?? $execUser;
        $mhoUser = User::query()->where('office_id', $offices['MHO']->id)->first() ?? $execUser;

        $supplier = Supplier::query()->firstOrCreate(
            ['supplier_name' => 'Metro Office Depot Trading'],
            [
                'contact_person' => 'Mina Solis',
                'email' => 'orders@metroofficedepot.example',
                'phone' => '09171234567',
                'address' => 'Poblacion District, City Proper',
                'status' => 'ACTIVE',
            ]
        );

        $items = Item::query()->get()->keyBy('item_name');
        if ($items->isEmpty()) {
            $this->command?->warn('MultiYearHistoricalProcurementSeeder skipped: No items found in item catalog.');
            return;
        }

        $this->command?->info('Seeding FY 2024 Historical Procurement Dataset (4 quarters, all offices, closed workflows)...');
        $this->seedFiscalYear(
            year: 2024,
            execUser: $execUser,
            gsoUser: $gsoUser,
            procUser: $procUser,
            officeUsers: ['MPDO' => $mpdoUser, 'MSWDO' => $mswdoUser, 'ENG' => $engUser, 'MHO' => $mhoUser],
            offices: $offices,
            supplier: $supplier,
            items: $items,
            priceScale: 0.89, // 2024 baseline (~11% lower than 2026)
        );

        $this->command?->info('Seeding FY 2025 Historical Procurement Dataset (4 quarters, all offices, closed workflows)...');
        $this->seedFiscalYear(
            year: 2025,
            execUser: $execUser,
            gsoUser: $gsoUser,
            procUser: $procUser,
            officeUsers: ['MPDO' => $mpdoUser, 'MSWDO' => $mswdoUser, 'ENG' => $engUser, 'MHO' => $mhoUser],
            offices: $offices,
            supplier: $supplier,
            items: $items,
            priceScale: 0.95, // 2025 baseline (~5% lower than 2026)
        );

        $this->command?->info('MultiYearHistoricalProcurementSeeder completed successfully.');
    }

    /**
     * @param array<string, User> $officeUsers
     * @param \Illuminate\Database\Eloquent\Collection<string, Office> $offices
     * @param \Illuminate\Database\Eloquent\Collection<string, Item> $items
     */
    private function seedFiscalYear(
        int $year,
        User $execUser,
        User $gsoUser,
        User $procUser,
        array $officeUsers,
        $offices,
        Supplier $supplier,
        $items,
        float $priceScale
    ): void {
        DB::transaction(function () use ($year, $execUser, $gsoUser, $procUser, $officeUsers, $offices, $supplier, $items, $priceScale) {
            $plans = [];
            if (isset($offices['MPDO'])) {
                $plans['MPDO'] = [
                    'user' => $officeUsers['MPDO'] ?? $execUser,
                    'office' => $offices['MPDO'],
                    'items' => [
                        ['name' => 'Bond Paper', 'q1' => 10, 'q2' => 10, 'q3' => 10, 'q4' => 10, 'base_cost' => 950],
                        ['name' => 'Ballpen', 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'base_cost' => 300],
                        ['name' => 'Folder', 'q1' => 2, 'q2' => 2, 'q3' => 2, 'q4' => 2, 'base_cost' => 1200],
                    ],
                ];
            }
            if (isset($offices['MSWDO'])) {
                $plans['MSWDO'] = [
                    'user' => $officeUsers['MSWDO'] ?? $execUser,
                    'office' => $offices['MSWDO'],
                    'items' => [
                        ['name' => 'Rubbing Alcohol', 'q1' => 10, 'q2' => 10, 'q3' => 10, 'q4' => 10, 'base_cost' => 400],
                        ['name' => 'Detergent Powder', 'q1' => 15, 'q2' => 15, 'q3' => 15, 'q4' => 15, 'base_cost' => 120],
                        ['name' => 'Safety Gloves', 'q1' => 10, 'q2' => 10, 'q3' => 10, 'q4' => 10, 'base_cost' => 45],
                    ],
                ];
            }
            if (isset($offices['ENG'])) {
                $plans['ENG'] = [
                    'user' => $officeUsers['ENG'] ?? $execUser,
                    'office' => $offices['ENG'],
                    'items' => [
                        ['name' => 'Printer Ink', 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'base_cost' => 850],
                        ['name' => 'Mouse', 'q1' => 10, 'q2' => 10, 'q3' => 10, 'q4' => 10, 'base_cost' => 325],
                        ['name' => 'Logbook', 'q1' => 5, 'q2' => 5, 'q3' => 5, 'q4' => 5, 'base_cost' => 150],
                    ],
                ];
            }
            if (isset($offices['MHO'])) {
                $plans['MHO'] = [
                    'user' => $officeUsers['MHO'] ?? $execUser,
                    'office' => $offices['MHO'],
                    'items' => [
                        ['name' => 'Rubbing Alcohol', 'q1' => 12, 'q2' => 12, 'q3' => 12, 'q4' => 12, 'base_cost' => 400],
                        ['name' => 'Detergent Powder', 'q1' => 10, 'q2' => 10, 'q3' => 10, 'q4' => 10, 'base_cost' => 120],
                        ['name' => 'Safety Gloves', 'q1' => 15, 'q2' => 15, 'q3' => 15, 'q4' => 15, 'base_cost' => 45],
                    ],
                ];
            }

            foreach ($plans as $officeCode => $planData) {
                $user = $planData['user'] ?? $execUser;
                $office = $planData['office'] ?? null;
                if (! $office) {
                    continue;
                }

                $datePrepared = Carbon::create($year - 1, 11, 15);
                $dateApproved = Carbon::create($year - 1, 12, 20);

                // 1. Create PPMP
                $ppmp = Ppmp::query()->updateOrCreate(
                    [
                        'office_id' => $office->id,
                        'fiscal_year' => $year,
                    ],
                    [
                        'prepared_by' => $user->id,
                        'approved_by' => $execUser->id,
                        'fund_source' => FundSource::APPROPRIATION_ORDINANCE,
                        'status' => 'APPROVED',
                        'total_estimated_budget' => 0,
                        'date_prepared' => $datePrepared,
                        'date_approved' => $dateApproved,
                        'remarks' => sprintf('Official approved PPMP for %s Fiscal Year %d.', $officeCode, $year),
                        'created_at' => $datePrepared,
                        'updated_at' => $dateApproved,
                    ]
                );

                $totalBudget = 0;
                $seededPpmpItems = [];

                foreach ($planData['items'] as $itemDef) {
                    $itemModel = $items->get($itemDef['name']) ?? $items->first();
                    if (! $itemModel) {
                        continue;
                    }

                    $unitCost = round($itemDef['base_cost'] * $priceScale, 2);
                    $totalQty = $itemDef['q1'] + $itemDef['q2'] + $itemDef['q3'] + $itemDef['q4'];
                    $estBudget = round($totalQty * $unitCost, 2);
                    $totalBudget += $estBudget;

                    $ppmpItem = PpmpItem::query()->updateOrCreate(
                        [
                            'ppmp_id' => $ppmp->ppmp_id,
                            'item_id' => $itemModel->item_id,
                        ],
                        [
                            'item_category_id' => $itemModel->item_category_id,
                            'quantity' => $totalQty,
                            'unit_cost' => $unitCost,
                            'estimated_budget' => $estBudget,
                            'procurement_schedule' => 'Q1,Q2,Q3,Q4',
                            'remarks' => sprintf('Annual allocation for %s (%d).', $itemModel->item_name, $year),
                            'created_at' => $datePrepared,
                            'updated_at' => $dateApproved,
                        ]
                    );

                    $seededPpmpItems[] = ['ppmp_item' => $ppmpItem, 'item' => $itemModel, 'unit_cost' => $unitCost];
                }

                $ppmp->update(['total_estimated_budget' => $totalBudget]);

                // 2. Create Completed Supply Requests & Procurement for all 4 Quarters
                $quarters = [
                    ['q' => 1, 'month' => 2, 'day' => 10, 'po_day' => 20, 'del_day' => 28],
                    ['q' => 2, 'month' => 5, 'day' => 12, 'po_day' => 22, 'del_day' => 30],
                    ['q' => 3, 'month' => 8, 'day' => 14, 'po_day' => 24, 'del_day' => 31],
                    ['q' => 4, 'month' => 11, 'day' => 8,  'po_day' => 18, 'del_day' => 26],
                ];

                foreach ($quarters as $qData) {
                    $reqDate = Carbon::create($year, $qData['month'], $qData['day']);
                    $poDate = Carbon::create($year, $qData['month'], $qData['po_day']);
                    $delDate = Carbon::create($year, $qData['month'], $qData['del_day']);
                    $distDate = $delDate->copy()->addDays(2);

                    $requestCode = sprintf('SR-%s-%d%02d-01', $officeCode, $year, $qData['month']);

                    $supplyRequest = SupplyRequest::query()->updateOrCreate(
                        ['request_code' => $requestCode],
                        [
                            'office_id' => $office->id,
                            'requested_by' => $user->id,
                            'ppmp_id' => $ppmp->ppmp_id,
                            'request_date' => $reqDate,
                            'needed_by_date' => $reqDate->copy()->addDays(15),
                            'request_category' => 'Office Supplies',
                            'purpose' => sprintf('%s Q%d Supply Requisition for FY %d.', $officeCode, $qData['q'], $year),
                            'priority_level' => 'NORMAL',
                            'status' => 'COMPLETED',
                            'approved_by' => $execUser->id,
                            'approved_at' => $reqDate->copy()->addDays(2),
                            'created_at' => $reqDate,
                            'updated_at' => $distDate,
                        ]
                    );

                    $srItems = [];
                    foreach ($seededPpmpItems as $pItemData) {
                        $pItem = $pItemData['ppmp_item'];
                        $itemObj = $pItemData['item'];
                        $reqQty = max(1, round($pItem->quantity / 4));

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

                        $srItems[] = ['sr_item' => $srItem, 'item' => $itemObj, 'unit_cost' => $pItemData['unit_cost'], 'qty' => $reqQty];
                    }

                    // 3. Purchase Request & Purchase Order
                    $pr = PurchaseRequest::query()->updateOrCreate(
                        ['supply_request_id' => $supplyRequest->supply_request_id],
                        [
                            'office_id' => $office->id,
                            'requested_by' => $user->id,
                            'status' => 'COMPLETED',
                            'remarks' => sprintf('Official approved PR for %s Q%d %d.', $officeCode, $qData['q'], $year),
                            'created_at' => $reqDate->copy()->addDays(3),
                            'updated_at' => $poDate,
                        ]
                    );

                    // Create Purchase Request Items (never leave PRs empty!)
                    foreach ($srItems as $srItemData) {
                        PurchaseRequestItem::query()->updateOrCreate(
                            [
                                'purchase_request_id' => $pr->purchase_request_id,
                                'item_id' => $srItemData['item']->item_id,
                            ],
                            [
                                'quantity_requested' => $srItemData['qty'],
                                'remarks' => sprintf('Procured allocation for %s Q%d %d.', $srItemData['item']->item_name, $qData['q'], $year),
                                'created_at' => $reqDate->copy()->addDays(3),
                                'updated_at' => $poDate,
                            ]
                        );
                    }

                    // Audit Status History for Supply Request & Purchase Request
                    StatusHistory::query()->create([
                        'reference_type' => SupplyRequest::class,
                        'reference_id' => $supplyRequest->supply_request_id,
                        'old_status' => 'DRAFT',
                        'new_status' => 'SUBMITTED',
                        'changed_by' => $user->id,
                        'remarks' => 'Submitted by office for review.',
                        'changed_at' => $reqDate,
                    ]);
                    StatusHistory::query()->create([
                        'reference_type' => SupplyRequest::class,
                        'reference_id' => $supplyRequest->supply_request_id,
                        'old_status' => 'SUBMITTED',
                        'new_status' => 'COMPLETED',
                        'changed_by' => $gsoUser->id,
                        'remarks' => 'Fulfillment and distribution completed.',
                        'changed_at' => $distDate,
                    ]);
                    ApprovalHistory::query()->create([
                        'reference_type' => SupplyRequest::class,
                        'reference_id' => $supplyRequest->supply_request_id,
                        'approved_by' => $execUser->id,
                        'status' => 'APPROVED',
                        'remarks' => 'Approved against annual PPMP.',
                        'approved_at' => $reqDate->copy()->addDays(2),
                    ]);

                    StatusHistory::query()->create([
                        'reference_type' => PurchaseRequest::class,
                        'reference_id' => $pr->purchase_request_id,
                        'old_status' => 'DRAFT',
                        'new_status' => 'OPEN',
                        'changed_by' => $user->id,
                        'remarks' => 'Purchase request forwarded to BAC.',
                        'changed_at' => $reqDate->copy()->addDays(3),
                    ]);
                    StatusHistory::query()->create([
                        'reference_type' => PurchaseRequest::class,
                        'reference_id' => $pr->purchase_request_id,
                        'old_status' => 'OPEN',
                        'new_status' => 'COMPLETED',
                        'changed_by' => $procUser->id,
                        'remarks' => 'Procurement and delivery finalized.',
                        'changed_at' => $distDate,
                    ]);
                    ApprovalHistory::query()->create([
                        'reference_type' => PurchaseRequest::class,
                        'reference_id' => $pr->purchase_request_id,
                        'approved_by' => $execUser->id,
                        'status' => 'APPROVED',
                        'remarks' => 'Approved by BAC & Executive.',
                        'approved_at' => $poDate,
                    ]);

                    $poNumber = sprintf('PO-%d-%02d%02d-%s', $year, $qData['month'], $office->id, $officeCode);
                    $poTotal = 0;

                    $po = PurchaseOrder::query()->updateOrCreate(
                        ['po_number' => $poNumber],
                        [
                            'purchase_request_id' => $pr->purchase_request_id,
                            'supply_request_id' => $supplyRequest->supply_request_id,
                            'supplier_id' => $supplier->supplier_id,
                            'created_by' => $procUser->id,
                            'approved_by' => $execUser->id,
                            'procurement_method' => 'SHOPPING',
                            'status' => 'COMPLETED',
                            'expected_delivery_date' => $delDate,
                            'date_approved' => $poDate,
                            'total_amount' => 0,
                            'terms_notes' => 'Standard 30-day payment terms upon inspection and acceptance.',
                            'remarks' => sprintf('Completed Purchase Order for %s FY %d.', $officeCode, $year),
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
                                'specification' => sprintf('%s - Standard Packaging (%s)', $itObj->item_name, $itObj->default_unit),
                                'quantity_ordered' => $srItemData['qty'],
                                'unit' => $itObj->default_unit,
                                'unit_price' => $srItemData['unit_cost'],
                                'total_price' => $lineTotal,
                                'created_at' => $poDate,
                                'updated_at' => $poDate,
                            ]
                        );

                        $poItemsList[] = ['po_item' => $poItem, 'item' => $itObj, 'qty' => $srItemData['qty'], 'unit_cost' => $srItemData['unit_cost']];
                    }

                    $po->update(['total_amount' => $poTotal]);

                    // 4. Delivery & Verification (IAR)
                    $deliveryCode = sprintf('DEL-%d-%02d-%03d', $year, $qData['month'], $po->purchase_order_id);
                    $drNumber = sprintf('DR-%d-%02d-%03d', $year, $qData['month'], $po->purchase_order_id);
                    $siNumber = sprintf('SI-%d-%02d-%03d', $year, $qData['month'], $po->purchase_order_id);

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
                            'receipt_status' => 'RECEIVED',
                            'remarks' => sprintf('Full delivery received and inspected by GSO Property for FY %d.', $year),
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
                            'remarks' => 'COA Inspection & Acceptance Report (IAR) verified complete.',
                            'created_at' => $delDate,
                            'updated_at' => $delDate,
                        ]
                    );

                    // 5. Distribution Record to Office
                    $distCode = sprintf('DIST-%d-%02d-%03d', $year, $qData['month'], $delivery->delivery_id);

                    $distribution = DistributionRecord::query()->updateOrCreate(
                        ['distribution_code' => $distCode],
                        [
                            'supply_request_id' => $supplyRequest->supply_request_id,
                            'delivery_id' => $delivery->delivery_id,
                            'office_id' => $office->id,
                            'prepared_by' => $gsoUser->id,
                            'received_by' => $user->id,
                            'release_date' => $distDate,
                            'storage_location' => 'Main Warehouse Storage',
                            'status' => 'RECEIVED',
                            'notes' => sprintf('Released and acknowledged by %s representative for FY %d.', $officeCode, $year),
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
                                'received_by' => $user->id,
                                'item_name_snapshot' => $poLine['item']->item_name,
                                'quantity_released' => $poLine['qty'],
                                'quantity_received' => $poLine['qty'],
                                'quantity_damaged' => 0,
                                'quantity_missing' => 0,
                                'receipt_condition' => 'GOOD',
                                'receipt_notes' => 'Acknowledged in good order and condition.',
                                'unit' => $poLine['item']->default_unit,
                                'remarks' => 'Stock issued against approved PPMP allocation.',
                                'created_at' => $distDate,
                                'updated_at' => $distDate,
                            ]
                        );
                    }

                    // Audit Status History for PO, Delivery, and Distribution
                    StatusHistory::query()->create([
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => $po->purchase_order_id,
                        'old_status' => 'DRAFT',
                        'new_status' => 'COMPLETED',
                        'changed_by' => $procUser->id,
                        'remarks' => 'Purchase order delivered, inspected and closed.',
                        'changed_at' => $distDate,
                    ]);
                    StatusHistory::query()->create([
                        'reference_type' => Delivery::class,
                        'reference_id' => $delivery->delivery_id,
                        'old_status' => 'PENDING',
                        'new_status' => 'RECEIVED',
                        'changed_by' => $gsoUser->id,
                        'remarks' => 'Items physically received, DR/SI verified and accepted.',
                        'changed_at' => $delDate,
                    ]);
                    StatusHistory::query()->create([
                        'reference_type' => DistributionRecord::class,
                        'reference_id' => $distribution->distribution_record_id,
                        'old_status' => 'PENDING',
                        'new_status' => 'RECEIVED',
                        'changed_by' => $user->id,
                        'remarks' => 'Supplies officially received and acknowledged by office.',
                        'changed_at' => $distDate,
                    ]);
                }
            }

            // 6. Public Transparency Disclosures for the Fiscal Year
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

            // 7. Resolved Historical Claim for realism
            $sampleDist = DistributionRecord::query()->whereYear('created_at', $year)->first();
            $sampleDistItem = $sampleDist ? $sampleDist->items()->first() : null;

            if ($sampleDist && $sampleDistItem) {
                Claim::query()->updateOrCreate(
                    ['claim_code' => sprintf('CLM-%d-00001', $year)],
                    [
                        'distribution_record_id' => $sampleDist->distribution_record_id,
                        'distribution_item_id' => $sampleDistItem->distribution_item_id,
                        'claimed_by' => $sampleDist->received_by,
                        'office_id' => $sampleDist->office_id,
                        'claim_type' => 'DAMAGED_ITEM',
                        'claim_date' => Carbon::create($year, 3, 14),
                        'claim_location' => 'Office Supply Receiving Station',
                        'quantity_disputed' => 1.0,
                        'quantity_damaged' => 1.0,
                        'quantity_missing' => 0,
                        'remarks' => sprintf('Defective packaging discovered on delivery inspection during FY %d Q1.', $year),
                        'status' => 'RESOLVED',
                        'resolution_type' => 'REPLACEMENT_RELEASED',
                        'replacement_quantity' => 1.0,
                        'created_at' => Carbon::create($year, 3, 14),
                        'updated_at' => Carbon::create($year, 3, 16),
                    ]
                );
            }
        });
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
