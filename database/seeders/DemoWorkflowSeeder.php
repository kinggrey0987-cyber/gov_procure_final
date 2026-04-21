<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DistributionRecord;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Office;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AlertService;
use App\Services\Analytics\AnomalyDetectionService;
use App\Services\Analytics\AnomalyService;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryVerificationService;
use App\Services\Executive\PpmpApprovalService;
use App\Services\Inventory\ClaimService;
use App\Services\Inventory\DistributionRecordService;
use App\Services\Inventory\InventoryService;
use App\Services\Office\SupplyRequestService;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $this->outputInfo('DemoWorkflowSeeder started.');
        $beforeCounts = $this->snapshotCounts();

        try {
            DB::transaction(function () use ($beforeCounts): void {
                $users = User::query()
                    ->whereIn('email', [
                        'rienz6977@gmail.com',
                        'rienz123123@gmail.com',
                        'rienyhulleza1022@gmail.com',
                        'jayrossrusselherbias@gmail.com',
                        'jeronopjer.chmsu@gmail.com',
                        'jeronopjer@gmail.com',
                        'govprocure86@gmail.com',
                        'kinggrey0987@gmail.com',
                        'roengwapo09@gmail.com',
                    ])
                    ->get()
                    ->keyBy('email');

                $offices = Office::query()->pluck('id', 'code');
                $items = Item::query()->pluck('item_id', 'item_name');

                $execUser = $this->requireUser($users, 'jeronopjer@gmail.com');
                $mpdoUser = $this->requireUser($users, 'rienz6977@gmail.com');
                $gsoUser = $this->requireUser($users, 'rienyhulleza1022@gmail.com');
                $procUser = $this->requireUser($users, 'jayrossrusselherbias@gmail.com');
                $mswdoUser = $this->requireUser($users, 'govprocure86@gmail.com');
                $engUser = $this->requireUser($users, 'roengwapo09@gmail.com');

                $mpdoOfficeId = $this->requireOfficeId($offices, 'MPDO');
                $mswdoOfficeId = $this->requireOfficeId($offices, 'MSWDO');
                $engOfficeId = $this->requireOfficeId($offices, 'ENG');

                $bondPaperItemId = $this->requireItemId($items, 'Bond Paper');
                $this->seedProofMinimumFlow($mpdoUser, $procUser, $mpdoOfficeId, $bondPaperItemId);

                $supplier = Supplier::query()->updateOrCreate(
                    ['supplier_name' => 'Metro Office Depot Trading'],
                    [
                        'contact_person' => 'Mina Solis',
                        'email' => 'orders@metroofficedepot.example',
                        'phone' => '09171234567',
                        'address' => 'Poblacion District, City Proper',
                        'status' => 'ACTIVE',
                    ],
                );

                $secondarySupplier = Supplier::query()->updateOrCreate(
                    ['supplier_name' => 'Northbay General Merchandising'],
                    [
                        'contact_person' => 'Arvin Reyes',
                        'email' => 'sales@northbaymerch.example',
                        'phone' => '09179876543',
                        'address' => 'Industrial Road, City Proper',
                        'status' => 'ACTIVE',
                    ],
                );

                $this->seedInventory($items);

                $ppmps = [
                    $this->seedPpmp(
                        officeId: $mpdoOfficeId,
                        preparer: $mpdoUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Bond Paper', 'quantity' => 20, 'unit_cost' => 18],
                            ['item_name' => 'Ballpen', 'quantity' => 30, 'unit_cost' => 6],
                        ],
                        desiredStatus: 'APPROVED',
                        remarks: '2026 municipal planning stationery plan.',
                    ),
                    $this->seedPpmp(
                        officeId: $mswdoOfficeId,
                        preparer: $mswdoUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Rubbing Alcohol', 'quantity' => 20, 'unit_cost' => 25],
                            ['item_name' => 'Detergent Powder', 'quantity' => 25, 'unit_cost' => 38],
                            ['item_name' => 'Safety Gloves', 'quantity' => 12, 'unit_cost' => 45],
                        ],
                        desiredStatus: 'RETURNED_FOR_REVISION',
                        remarks: 'Returned for budget alignment and quantity review.',
                    ),
                    $this->seedPpmp(
                        officeId: $engOfficeId,
                        preparer: $engUser,
                        approver: $execUser,
                        fiscalYear: 2026,
                        plannedItems: [
                            ['item_name' => 'Printer Ink', 'quantity' => 8, 'unit_cost' => 850],
                            ['item_name' => 'Mouse', 'quantity' => 12, 'unit_cost' => 325],
                            ['item_name' => 'Logbook', 'quantity' => 10, 'unit_cost' => 120],
                        ],
                        desiredStatus: 'APPROVED',
                        remarks: 'Engineering office operating stock plan.',
                    ),
                ];

                $mpdoPpmp = $ppmps[0];
                $mswdoPpmp = $ppmps[1];
                $engPpmp = $ppmps[2];

                $mpdoRequests = [];
                foreach ([2, 3, 4, 5, 80] as $index => $quantity) {
                    $mpdoRequests[] = $this->seedSupplyRequest(
                        actor: $mpdoUser,
                        ppmp: $mpdoPpmp,
                        requestCode: sprintf('SR-MPDO-%04d', $index + 1),
                        purpose: 'Stationery replenishment for planning operations.',
                        requestCategory: 'Office Supplies',
                        priorityLevel: $index === 4 ? 'HIGH' : 'NORMAL',
                        neededByDate: now()->addDays(7 + $index)->toDateString(),
                        lines: [
                            ['item_name' => 'Bond Paper', 'quantity' => $quantity],
                        ],
                    );
                }

                $this->seedSupplyRequest(
                    actor: $mswdoUser,
                    ppmp: $mswdoPpmp,
                    requestCode: 'SR-MSWDO-0001',
                    purpose: 'Welfare and sanitation replenishment for service desk and field use.',
                    requestCategory: 'Cleaning Supplies',
                    priorityLevel: 'HIGH',
                    neededByDate: now()->addDays(10)->toDateString(),
                    lines: [
                        ['item_name' => 'Rubbing Alcohol', 'quantity' => 8],
                        ['item_name' => 'Safety Gloves', 'quantity' => 4],
                    ],
                );

                $this->seedSupplyRequest(
                    actor: $engUser,
                    ppmp: $engPpmp,
                    requestCode: 'SR-ENG-0001',
                    purpose: 'Maintenance desk replenishment for utilities and records.',
                    requestCategory: 'Office Supplies',
                    priorityLevel: 'NORMAL',
                    neededByDate: now()->addDays(12)->toDateString(),
                    lines: [
                        ['item_name' => 'Printer Ink', 'quantity' => 3],
                        ['item_name' => 'Mouse', 'quantity' => 2],
                    ],
                );

                $prices = [18, 19, 20, 21, 60];
                $purchaseOrders = [];

                foreach ($mpdoRequests as $index => $supplyRequest) {
                    $purchaseOrders[] = $this->seedProcurementFlow(
                        actor: $procUser,
                        supplyRequest: $supplyRequest,
                        supplier: $index === 4 ? $secondarySupplier : $supplier,
                        unitPrice: $prices[$index],
                        procurementMethod: $index === 4 ? 'NEGOTIATED' : 'SMALL_VALUE_PROCUREMENT',
                        expectedDeliveryDate: now()->addDays(10 + $index)->toDateString(),
                        deliveryNotes: $index === 4 ? 'Expedite the final replenishment batch.' : 'Standard procurement cycle.',
                    );
                }

                $finalPurchaseOrder = $purchaseOrders[4] ?? null;
                if (! $finalPurchaseOrder instanceof PurchaseOrder) {
                    throw new RuntimeException('Expected final purchase order was not generated for MPDO flow.');
                }

                $finalPurchaseOrder->loadMissing('items', 'purchaseRequest', 'supplyRequest');

                $delivery = $this->seedDeliveryAndDistributionFlow(
                    gsoUser: $gsoUser,
                    officeUser: $mpdoUser,
                    purchaseOrder: $finalPurchaseOrder,
                    supplier: $finalPurchaseOrder->supplier ?? $secondarySupplier,
                );

                $this->seedAlerts($gsoUser, $finalPurchaseOrder, $delivery);

                $this->seedPresentationTopUp(
                    execUser: $execUser,
                    mpdoUser: $mpdoUser,
                    mswdoUser: $mswdoUser,
                    engUser: $engUser,
                    procUser: $procUser,
                    gsoUser: $gsoUser,
                    supplier: $supplier,
                    secondarySupplier: $secondarySupplier,
                    approvedPpmpsByOffice: [
                        'MPDO' => $mpdoPpmp,
                        'MSWDO' => $mswdoPpmp,
                        'ENG' => $engPpmp,
                    ],
                );

                $this->normalizeGsoLinkedDemoData($gsoUser);

                app(AnomalyDetectionService::class)->runExecutiveDetections($execUser);
                $dedupedAnomalies = app(AnomalyService::class)->collapseActiveDuplicates();
                $this->outputLine(sprintf('Anomaly duplicate cleanup dismissed %d duplicate active record(s).', $dedupedAnomalies));

                $afterCounts = $this->snapshotCounts();
                $this->reportCounterDelta($beforeCounts, $afterCounts);
            });
        } catch (Throwable $exception) {
            Log::error('DemoWorkflowSeeder failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->command?->error('DemoWorkflowSeeder failed: '.$exception->getMessage());
            throw $exception;
        }

        $this->outputInfo('DemoWorkflowSeeder completed.');
    }

    /**
     * @return array<string, int>
     */
    private function snapshotCounts(): array
    {
        return [
            'ppmp' => (int) DB::table('ppmp')->count(),
            'ppmp_items' => (int) DB::table('ppmp_items')->count(),
            'supply_requests' => (int) DB::table('supply_requests')->count(),
            'supply_request_items' => (int) DB::table('supply_request_items')->count(),
            'purchase_requests' => (int) DB::table('purchase_requests')->count(),
            'purchase_request_items' => (int) DB::table('purchase_request_items')->count(),
            'inventory' => (int) DB::table('inventory')->count(),
            'deliveries' => (int) DB::table('deliveries')->count(),
            'distribution_records' => (int) DB::table('distribution_records')->count(),
            'anomalies' => (int) DB::table('anomalies')->count(),
            'alerts' => (int) DB::table('alerts')->count(),
        ];
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private function reportCounterDelta(array $before, array $after): void
    {
        $this->outputInfo('DemoWorkflowSeeder insert deltas:');

        foreach ($after as $table => $afterCount) {
            $beforeCount = $before[$table] ?? 0;
            $delta = $afterCount - $beforeCount;
            $this->outputLine(sprintf('- %s: +%d (before=%d, after=%d)', $table, $delta, $beforeCount, $afterCount));
        }
    }

    private function outputInfo(string $message): void
    {
        $this->command?->info($message);
        Log::info($message);
    }

    private function outputLine(string $message): void
    {
        $this->command?->line($message);
        Log::info($message);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $usersByEmail
     */
    private function requireUser($usersByEmail, string $email): User
    {
        $user = $usersByEmail->get($email);

        if (! $user instanceof User) {
            throw new RuntimeException(sprintf('Required user not found for email: %s', $email));
        }

        return $user;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $officesByCode
     */
    private function requireOfficeId($officesByCode, string $code): int
    {
        $officeId = (int) ($officesByCode->get($code) ?? 0);

        if ($officeId <= 0) {
            throw new RuntimeException(sprintf('Required office code not found: %s', $code));
        }

        return $officeId;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $itemsByName
     */
    private function requireItemId($itemsByName, string $itemName): int
    {
        $itemId = (int) ($itemsByName->get($itemName) ?? 0);

        if ($itemId <= 0) {
            throw new RuntimeException(sprintf('Required item not found: %s', $itemName));
        }

        return $itemId;
    }

    private function seedProofMinimumFlow(User $officeUser, User $procUser, int $officeId, int $itemId): void
    {
        $proofPpmp = Ppmp::query()->updateOrCreate(
            [
                'office_id' => $officeId,
                'fiscal_year' => 2030,
            ],
            [
                'prepared_by' => (int) $officeUser->id,
                'approved_by' => null,
                'status' => 'APPROVED',
                'total_estimated_budget' => 100,
                'date_prepared' => now()->toDateString(),
                'date_approved' => now()->toDateString(),
                'remarks' => 'Proof minimum PPMP for insert validation.',
            ],
        );

        $proofPpmpItem = PpmpItem::query()->updateOrCreate(
            [
                'ppmp_id' => (int) $proofPpmp->ppmp_id,
                'item_id' => $itemId,
            ],
            [
                'item_category_id' => (int) Item::query()->where('item_id', $itemId)->value('item_category_id'),
                'quantity' => 5,
                'unit_cost' => 20,
                'estimated_budget' => 100,
                'procurement_schedule' => now()->addDays(5)->toDateString(),
                'fund_source' => 'General Fund',
                'remarks' => 'Proof PPMP item.',
            ],
        );

        $proofSupplyRequest = SupplyRequest::query()->updateOrCreate(
            ['request_code' => 'SR-PROOF-0001'],
            [
                'office_id' => $officeId,
                'requested_by' => (int) $officeUser->id,
                'ppmp_id' => (int) $proofPpmp->ppmp_id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'Office Supplies',
                'purpose' => 'Proof seed supply request',
                'priority_level' => 'NORMAL',
                'status' => 'SUBMITTED',
                'review_notes' => 'Proof inserted by DemoWorkflowSeeder',
                'approved_by' => null,
                'approved_at' => null,
            ],
        );

        SupplyRequestItem::query()->updateOrCreate(
            [
                'supply_request_id' => (int) $proofSupplyRequest->supply_request_id,
                'ppmp_item_id' => (int) $proofPpmpItem->ppmp_item_id,
            ],
            [
                'item_id' => $itemId,
                'item_name_snapshot' => (string) Item::query()->where('item_id', $itemId)->value('item_name'),
                'quantity_requested' => 5,
            ],
        );

        $proofPurchaseRequest = PurchaseRequest::query()->updateOrCreate(
            [
                'supply_request_id' => (int) $proofSupplyRequest->supply_request_id,
            ],
            [
                'office_id' => $officeId,
                'requested_by' => (int) $officeUser->id,
                'status' => 'OPEN',
                'remarks' => 'Proof purchase request for insert validation.',
            ],
        );

        PurchaseRequestItem::query()->updateOrCreate(
            [
                'purchase_request_id' => (int) $proofPurchaseRequest->purchase_request_id,
                'item_id' => $itemId,
            ],
            [
                'quantity_requested' => 5,
                'remarks' => 'Proof purchase request item.',
            ],
        );

        $this->outputLine(sprintf(
            'Proof minimum flow ensured: ppmp_id=%d, ppmp_item_id=%d, supply_request_id=%d, purchase_request_id=%d, actor_proc_id=%d',
            (int) $proofPpmp->ppmp_id,
            (int) $proofPpmpItem->ppmp_item_id,
            (int) $proofSupplyRequest->supply_request_id,
            (int) $proofPurchaseRequest->purchase_request_id,
            (int) $procUser->id,
        ));
    }

    /**
     * @param  array<string, Ppmp>  $approvedPpmpsByOffice
     */
    private function seedPresentationTopUp(
        User $execUser,
        User $mpdoUser,
        User $mswdoUser,
        User $engUser,
        User $procUser,
        User $gsoUser,
        Supplier $supplier,
        Supplier $secondarySupplier,
        array $approvedPpmpsByOffice,
    ): void {
        $this->seedPpmp(
            officeId: (int) $mpdoUser->office_id,
            preparer: $mpdoUser,
            approver: $execUser,
            fiscalYear: 2027,
            plannedItems: [
                ['item_name' => 'Bond Paper', 'quantity' => 140, 'unit_cost' => 19],
                ['item_name' => 'Ballpen', 'quantity' => 260, 'unit_cost' => 7],
                ['item_name' => 'Folder', 'quantity' => 90, 'unit_cost' => 12],
                ['item_name' => 'Logbook', 'quantity' => 50, 'unit_cost' => 115],
            ],
            desiredStatus: 'APPROVED',
            remarks: 'Expanded MPDO PPMP for presentation dataset.',
        );

        $this->seedPpmp(
            officeId: (int) $mswdoUser->office_id,
            preparer: $mswdoUser,
            approver: $execUser,
            fiscalYear: 2027,
            plannedItems: [
                ['item_name' => 'Rubbing Alcohol', 'quantity' => 70, 'unit_cost' => 28],
                ['item_name' => 'Detergent Powder', 'quantity' => 65, 'unit_cost' => 40],
                ['item_name' => 'Safety Gloves', 'quantity' => 40, 'unit_cost' => 48],
                ['item_name' => 'Trash Bag', 'quantity' => 50, 'unit_cost' => 35],
            ],
            desiredStatus: 'RETURNED_FOR_REVISION',
            remarks: 'Expanded MSWDO PPMP in returned state for executive review demos.',
        );

        $engSubmittedPpmp = Ppmp::query()->updateOrCreate(
            [
                'office_id' => (int) $engUser->office_id,
                'fiscal_year' => 2027,
            ],
            [
                'prepared_by' => (int) $engUser->id,
                'approved_by' => null,
                'status' => 'SUBMITTED',
                'total_estimated_budget' => 0,
                'date_prepared' => now()->toDateString(),
                'date_approved' => null,
                'remarks' => 'Engineering submitted PPMP awaiting executive decision.',
            ],
        );

        PpmpItem::query()->updateOrCreate(
            ['ppmp_id' => (int) $engSubmittedPpmp->ppmp_id, 'item_id' => (int) Item::query()->where('item_name', 'Printer Ink')->value('item_id')],
            [
                'item_category_id' => (int) Item::query()->where('item_name', 'Printer Ink')->value('item_category_id'),
                'quantity' => 16,
                'unit_cost' => 860,
                'estimated_budget' => 13760,
                'procurement_schedule' => now()->addDays(30)->toDateString(),
                'fund_source' => 'General Fund',
                'remarks' => 'Submitted PPMP line for engineering consumables.',
            ],
        );

        PpmpItem::query()->updateOrCreate(
            ['ppmp_id' => (int) $engSubmittedPpmp->ppmp_id, 'item_id' => (int) Item::query()->where('item_name', 'Mouse')->value('item_id')],
            [
                'item_category_id' => (int) Item::query()->where('item_name', 'Mouse')->value('item_category_id'),
                'quantity' => 40,
                'unit_cost' => 330,
                'estimated_budget' => 13200,
                'procurement_schedule' => now()->addDays(30)->toDateString(),
                'fund_source' => 'General Fund',
                'remarks' => 'Submitted PPMP line for engineering workstation supplies.',
            ],
        );

        $mpdoApprovedPpmp = $approvedPpmpsByOffice['MPDO'] ?? null;
        $mswdoApprovedPpmp = $approvedPpmpsByOffice['MSWDO'] ?? null;
        $engApprovedPpmp = $approvedPpmpsByOffice['ENG'] ?? null;

        if (! $mpdoApprovedPpmp instanceof Ppmp || ! $mswdoApprovedPpmp instanceof Ppmp || ! $engApprovedPpmp instanceof Ppmp) {
            throw new RuntimeException('Approved PPMP context is missing for expanded presentation top-up seeding.');
        }

        $srOfficeTracking = $this->upsertScenarioSupplyRequest(
            actor: $mpdoUser,
            ppmp: $mpdoApprovedPpmp,
            requestCode: 'SR-MPDO-TRACK-0001',
            status: 'VALIDATING',
            purpose: 'Planning records verification batch',
            priority: 'NORMAL',
            lines: [
                ['item_name' => 'Folder', 'quantity' => 30],
                ['item_name' => 'Logbook', 'quantity' => 12],
            ],
        );

        $srProcQueue = $this->upsertScenarioSupplyRequest(
            actor: $mswdoUser,
            ppmp: $mswdoApprovedPpmp,
            requestCode: 'SR-MSWDO-PROC-0001',
            status: 'FOR_PROCUREMENT',
            purpose: 'Welfare hygiene replenishment queued for BAC processing',
            priority: 'HIGH',
            lines: [
                ['item_name' => 'Rubbing Alcohol', 'quantity' => 20],
                ['item_name' => 'Detergent Powder', 'quantity' => 18],
            ],
        );

        $srReadyDistribution = $this->upsertScenarioSupplyRequest(
            actor: $engUser,
            ppmp: $engApprovedPpmp,
            requestCode: 'SR-ENG-RFD-0001',
            status: 'READY_FOR_DISTRIBUTION',
            purpose: 'Engineering supply batch approved for warehouse release',
            priority: 'NORMAL',
            lines: [
                ['item_name' => 'Printer Ink', 'quantity' => 6],
                ['item_name' => 'Mouse', 'quantity' => 8],
            ],
        );

        $srCompleted = $this->upsertScenarioSupplyRequest(
            actor: $mpdoUser,
            ppmp: $mpdoApprovedPpmp,
            requestCode: 'SR-MPDO-COMP-0001',
            status: 'COMPLETED',
            purpose: 'Completed planning office replenishment cycle',
            priority: 'LOW',
            lines: [
                ['item_name' => 'Bond Paper', 'quantity' => 12],
                ['item_name' => 'Ballpen', 'quantity' => 24],
            ],
        );

        $mpdoFrequentRequests = [];
        for ($i = 1; $i <= 8; $i++) {
            $mpdoFrequentRequests[] = $this->upsertScenarioSupplyRequest(
                actor: $mpdoUser,
                ppmp: $mpdoApprovedPpmp,
                requestCode: sprintf('SR-MPDO-FREQ-%04d', $i),
                status: 'SUBMITTED',
                purpose: 'Frequent office replenishment pattern for anomaly detection demos',
                priority: 'NORMAL',
                lines: [
                    ['item_name' => 'Bond Paper', 'quantity' => $i === 8 ? 120 : 4],
                ],
            );
        }

        $trackedForProcurement = array_merge([$srProcQueue], array_slice($mpdoFrequentRequests, 0, 4));

        foreach ($trackedForProcurement as $index => $request) {
            $purchaseRequest = PurchaseRequest::query()->updateOrCreate(
                ['supply_request_id' => (int) $request->supply_request_id],
                [
                    'office_id' => (int) $request->office_id,
                    'requested_by' => (int) $request->requested_by,
                    'status' => $index === 0 ? 'PENDING' : ($index % 2 === 0 ? 'PROCESSING' : 'COMPLETED'),
                    'remarks' => 'Seeded procurement queue variation for BAC dashboards.',
                ],
            );

            foreach ($request->items as $requestItem) {
                PurchaseRequestItem::query()->updateOrCreate(
                    [
                        'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                        'item_id' => (int) $requestItem->item_id,
                    ],
                    [
                        'quantity_requested' => (float) $requestItem->quantity_requested,
                        'remarks' => 'Seeded procurement item derived from office request lines.',
                    ],
                );
            }

            $purchaseRequest->refresh();

            if ((string) $purchaseRequest->status === 'PROCESSING') {
                $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
                    ['po_number' => sprintf('PO-PRES-%04d', $index + 1)],
                    [
                        'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                        'supply_request_id' => (int) $request->supply_request_id,
                        'supplier_id' => $index === 0 ? (int) $secondarySupplier->supplier_id : (int) $supplier->supplier_id,
                        'created_by' => (int) $procUser->id,
                        'procurement_method' => 'SMALL_VALUE_PROCUREMENT',
                        'status' => $index % 3 === 0 ? 'DRAFT' : 'PLACED',
                        'expected_delivery_date' => now()->addDays(5 + $index)->toDateString(),
                        'total_amount' => 0,
                        'terms_notes' => 'Presentation purchase order terms.',
                        'remarks' => 'Seeded BAC procurement order scenario.',
                    ],
                );

                $runningTotal = 0.0;
                foreach ($request->items as $itemIndex => $requestItem) {
                    $price = $requestItem->item_name_snapshot === 'Bond Paper'
                        ? [18.0, 19.0, 20.0, 75.0, 22.0][$index % 5]
                        : (float) (120 + ($itemIndex * 12));

                    $quantityOrdered = (float) $requestItem->quantity_requested;
                    $lineTotal = round($quantityOrdered * $price, 2);
                    $runningTotal += $lineTotal;

                    PurchaseOrderItem::query()->updateOrCreate(
                        [
                            'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                            'supply_request_item_id' => (int) $requestItem->supply_request_item_id,
                        ],
                        [
                            'item_id' => (int) $requestItem->item_id,
                            'item_name_snapshot' => (string) $requestItem->item_name_snapshot,
                            'specification' => 'Presentation procurement line specification.',
                            'quantity_ordered' => $quantityOrdered,
                            'unit' => 'piece',
                            'unit_price' => $price,
                            'total_price' => $lineTotal,
                        ],
                    );
                }

                $purchaseOrder->forceFill([
                    'total_amount' => $runningTotal,
                    'status' => $index === 2 ? 'COMPLETED' : (string) $purchaseOrder->status,
                ])->save();

                if ((string) $purchaseOrder->status === 'PLACED' || (string) $purchaseOrder->status === 'COMPLETED') {
                    $delivery = Delivery::query()->updateOrCreate(
                        ['delivery_code' => sprintf('DEL-PRES-%04d', $index + 1)],
                        [
                            'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                            'supplier_id' => (int) $purchaseOrder->supplier_id,
                            'actual_delivery_date' => $index % 2 === 0 ? now()->addDays(3 + $index)->toDateString() : now()->addDays(9 + $index)->toDateString(),
                            'expected_arrival_date' => now()->addDays(4 + $index)->toDateString(),
                            'received_by' => (int) $gsoUser->id,
                            'receipt_status' => $index % 2 === 0 ? 'VERIFIED' : 'RECEIVED',
                            'remarks' => $index % 2 === 0 ? 'On-time verified delivery.' : 'Delayed delivery pending final verification.',
                        ],
                    );

                    foreach ($purchaseOrder->items as $orderItem) {
                        DeliveryItem::query()->updateOrCreate(
                            [
                                'delivery_id' => (int) $delivery->delivery_id,
                                'purchase_order_item_id' => (int) $orderItem->purchase_order_item_id,
                            ],
                            [
                                'expected_quantity' => (float) $orderItem->quantity_ordered,
                                'received_quantity' => (float) $orderItem->quantity_ordered,
                                'damaged_quantity' => 0,
                                'missing_quantity' => 0,
                                'item_condition' => 'GOOD',
                                'remarks' => 'Seeded delivery line for presentation scenario.',
                            ],
                        );
                    }

                    if ((string) $delivery->receipt_status === 'VERIFIED') {
                        DistributionRecord::query()->updateOrCreate(
                            ['distribution_code' => sprintf('DIST-PRES-%04d', $index + 1)],
                            [
                                'supply_request_id' => (int) $request->supply_request_id,
                                'delivery_id' => (int) $delivery->delivery_id,
                                'office_id' => (int) $request->office_id,
                                'prepared_by' => (int) $gsoUser->id,
                                'received_by' => (int) $request->requested_by,
                                'release_date' => now()->addDays(1)->toDateString(),
                                'storage_location' => 'Main GSO rack A',
                                'status' => $index % 4 === 0 ? 'RELEASED' : 'RECEIVED',
                                'notes' => 'Seeded distribution visibility scenario.',
                            ],
                        );
                    }
                }
            }
        }

        $this->ensureInventoryShowcases();

        $deliveryCount = (int) DB::table('deliveries')->count();
        if ($deliveryCount < 4) {
            $candidateOrder = PurchaseOrder::query()
                ->whereIn('status', ['PLACED', 'COMPLETED'])
                ->with(['items'])
                ->orderBy('purchase_order_id')
                ->first();

            if ($candidateOrder instanceof PurchaseOrder) {
                $extraDelivery = Delivery::query()->updateOrCreate(
                    ['delivery_code' => 'DEL-PRES-EXTRA-0001'],
                    [
                        'purchase_order_id' => (int) $candidateOrder->purchase_order_id,
                        'supplier_id' => (int) $candidateOrder->supplier_id,
                        'actual_delivery_date' => now()->addDays(11)->toDateString(),
                        'expected_arrival_date' => now()->addDays(6)->toDateString(),
                        'received_by' => (int) $gsoUser->id,
                        'receipt_status' => 'RECEIVED',
                        'remarks' => 'Delayed supplemental delivery for presentation dataset depth.',
                    ],
                );

                foreach ($candidateOrder->items as $orderItem) {
                    DeliveryItem::query()->updateOrCreate(
                        [
                            'delivery_id' => (int) $extraDelivery->delivery_id,
                            'purchase_order_item_id' => (int) $orderItem->purchase_order_item_id,
                        ],
                        [
                            'expected_quantity' => (float) $orderItem->quantity_ordered,
                            'received_quantity' => (float) $orderItem->quantity_ordered,
                            'damaged_quantity' => 0,
                            'missing_quantity' => 0,
                            'item_condition' => 'GOOD',
                            'remarks' => 'Supplemental delayed delivery line for role presentation.',
                        ],
                    );
                }
            }
        }

        $targets = [
            'ppmp' => 6,
            'ppmp_items' => 16,
            'supply_requests' => 16,
            'supply_request_items' => 24,
            'purchase_requests' => 8,
            'purchase_request_items' => 12,
            'deliveries' => 4,
            'distribution_records' => 3,
        ];

        foreach ($targets as $table => $minimum) {
            $current = (int) DB::table($table)->count();
            if ($current < $minimum) {
                $this->outputLine(sprintf('Top-up note: %s is below target (%d/%d). Existing scenarios were applied idempotently.', $table, $current, $minimum));
            }
        }
    }

    private function upsertScenarioSupplyRequest(
        User $actor,
        Ppmp $ppmp,
        string $requestCode,
        string $status,
        string $purpose,
        string $priority,
        array $lines,
    ): SupplyRequest {
        $request = SupplyRequest::query()->updateOrCreate(
            ['request_code' => $requestCode],
            [
                'office_id' => (int) $actor->office_id,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'request_date' => now()->subDays(12)->toDateString(),
                'needed_by_date' => now()->addDays(10)->toDateString(),
                'request_category' => 'Office Supplies',
                'purpose' => $purpose,
                'priority_level' => $priority,
                'status' => $status,
                'review_notes' => 'Seeded status variation for presentation.',
                'approved_by' => null,
                'approved_at' => null,
            ],
        );

        foreach ($lines as $line) {
            $item = Item::query()->where('item_name', (string) $line['item_name'])->first();
            if (! $item instanceof Item) {
                throw new RuntimeException(sprintf('Missing item while seeding scenario supply request: %s', (string) $line['item_name']));
            }

            $ppmpItem = PpmpItem::query()->firstOrCreate(
                [
                    'ppmp_id' => (int) $ppmp->ppmp_id,
                    'item_id' => (int) $item->item_id,
                ],
                [
                    'item_category_id' => (int) $item->item_category_id,
                    'quantity' => max((float) $line['quantity'] * 2, 10),
                    'unit_cost' => 50,
                    'estimated_budget' => max((float) $line['quantity'] * 100, 500),
                    'procurement_schedule' => now()->addDays(20)->toDateString(),
                    'fund_source' => 'General Fund',
                    'remarks' => 'Auto-created PPMP line for scenario request.',
                ],
            );

            SupplyRequestItem::query()->updateOrCreate(
                [
                    'supply_request_id' => (int) $request->supply_request_id,
                    'ppmp_item_id' => (int) $ppmpItem->ppmp_item_id,
                ],
                [
                    'item_id' => (int) $item->item_id,
                    'item_name_snapshot' => (string) $item->item_name,
                    'quantity_requested' => (float) $line['quantity'],
                ],
            );
        }

        return $request->fresh(['items']) ?? $request;
    }

    private function ensureInventoryShowcases(): void
    {
        $showcase = [
            'Bond Paper' => ['stock_on_hand' => 180, 'available_stock' => 120, 'threshold' => 30],
            'Printer Ink' => ['stock_on_hand' => 6, 'available_stock' => 2, 'threshold' => 8],
            'Toner Cartridge' => ['stock_on_hand' => 4, 'available_stock' => 3, 'threshold' => 3],
        ];

        foreach ($showcase as $itemName => $values) {
            $item = Item::query()->where('item_name', $itemName)->first();
            if (! $item instanceof Item) {
                continue;
            }

            Inventory::query()->updateOrCreate(
                ['item_id' => (int) $item->item_id],
                [
                    'stock_on_hand' => (float) $values['stock_on_hand'],
                    'available_stock' => (float) $values['available_stock'],
                    'reserved_quantity' => 0,
                    'released_quantity' => 0,
                    'low_stock_threshold' => (float) $values['threshold'],
                    'last_updated' => now(),
                ],
            );
        }
    }

    private function seedInventory($items): void
    {
        $inventoryRows = [
            'Bond Paper' => [220, 200, 25],
            'Ballpen' => [80, 70, 20],
            'Printer Ink' => [4, 4, 8],
            'Mouse' => [10, 10, 6],
            'Logbook' => [5, 5, 8],
            'Rubbing Alcohol' => [6, 6, 8],
            'Detergent Powder' => [8, 8, 10],
            'Safety Gloves' => [3, 3, 5],
            'Flash Drive' => [5, 5, 4],
            'Toner Cartridge' => [2, 2, 3],
        ];

        foreach ($inventoryRows as $itemName => [$stockOnHand, $availableStock, $lowStockThreshold]) {
            $itemId = (int) ($items->get($itemName) ?? 0);
            if ($itemId <= 0) {
                throw new RuntimeException(sprintf('Missing item for inventory seed row: %s', $itemName));
            }

            Inventory::query()->updateOrCreate(
                ['item_id' => $itemId],
                [
                    'stock_on_hand' => $stockOnHand,
                    'available_stock' => $availableStock,
                    'reserved_quantity' => 0,
                    'released_quantity' => 0,
                    'low_stock_threshold' => $lowStockThreshold,
                    'last_updated' => now(),
                ],
            );
        }
    }

    private function seedPpmp(int $officeId, User $preparer, User $approver, int $fiscalYear, array $plannedItems, string $desiredStatus, string $remarks): Ppmp
    {
        if ($officeId <= 0) {
            throw new RuntimeException('Invalid office_id resolved while seeding PPMP.');
        }

        $ppmp = Ppmp::query()->updateOrCreate(
            [
                'office_id' => $officeId,
                'fiscal_year' => $fiscalYear,
            ],
            [
                'prepared_by' => (int) $preparer->id,
                'status' => 'SUBMITTED',
                'total_estimated_budget' => 0,
                'date_prepared' => now()->toDateString(),
                'date_approved' => null,
                'remarks' => $remarks,
            ],
        );

        $totalEstimatedBudget = 0.0;
        foreach ($plannedItems as $plannedItem) {
            $itemId = (int) Item::query()->where('item_name', $plannedItem['item_name'])->value('item_id');
            if ($itemId <= 0) {
                throw new RuntimeException(sprintf('Missing item for PPMP seed row: %s', $plannedItem['item_name']));
            }

            $unitCost = (float) $plannedItem['unit_cost'];
            $quantity = (float) $plannedItem['quantity'];
            $estimatedBudget = round($unitCost * $quantity, 2);
            $totalEstimatedBudget += $estimatedBudget;

            PpmpItem::query()->updateOrCreate(
                [
                    'ppmp_id' => (int) $ppmp->ppmp_id,
                    'item_id' => $itemId,
                ],
                [
                    'item_category_id' => (int) Item::query()->where('item_id', $itemId)->value('item_category_id'),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'estimated_budget' => $estimatedBudget,
                    'procurement_schedule' => now()->addWeeks(2)->toDateString(),
                    'fund_source' => 'General Fund',
                    'remarks' => 'Seeded demo PPMP line item.',
                ],
            );
        }

        $ppmp->forceFill([
            'total_estimated_budget' => $totalEstimatedBudget,
        ])->save();

        if ((string) $ppmp->status === 'SUBMITTED') {
            $service = app(PpmpApprovalService::class);
            $ppmp = match ($desiredStatus) {
                'APPROVED' => $service->updateStatus($ppmp, $approver, 'APPROVED', 'Approved for demo workflow seeding.'),
                'RETURNED_FOR_REVISION' => $service->updateStatus($ppmp, $approver, 'RETURNED_FOR_REVISION', 'Returned for revision for demo workflow seeding.'),
                'REJECTED' => $service->updateStatus($ppmp, $approver, 'REJECTED', 'Rejected for demo workflow seeding.'),
                default => $ppmp,
            };
        }

        return $ppmp->fresh(['office', 'preparer', 'approver', 'items.item', 'items.itemCategory']) ?? $ppmp;
    }

    private function seedSupplyRequest(
        User $actor,
        Ppmp $ppmp,
        string $requestCode,
        string $purpose,
        string $requestCategory,
        string $priorityLevel,
        string $neededByDate,
        array $lines,
    ): SupplyRequest {
        $supplyRequest = SupplyRequest::query()->updateOrCreate(
            ['request_code' => $requestCode],
            [
                'office_id' => (int) $actor->office_id,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => (int) $ppmp->ppmp_id,
                'request_date' => now()->toDateString(),
                'needed_by_date' => $neededByDate,
                'request_category' => $requestCategory,
                'purpose' => $purpose,
                'priority_level' => $priorityLevel,
                'status' => 'DRAFT',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ],
        );

        if ((string) $supplyRequest->status !== 'DRAFT') {
            $this->outputLine(sprintf(
                'Supply request %s already %s; skipping draft mutation path.',
                (string) $supplyRequest->request_code,
                (string) $supplyRequest->status,
            ));
            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $supplyRequest;
        }

        foreach ($lines as $line) {
            $item = Item::query()->where('item_name', $line['item_name'])->first();
            if (! $item instanceof Item) {
                throw new RuntimeException(sprintf('Missing item while seeding supply request line: %s', $line['item_name']));
            }

            $ppmpItem = PpmpItem::query()
                ->where('ppmp_id', (int) $ppmp->ppmp_id)
                ->where('item_id', (int) $item->item_id)
                ->first();

            if (! $ppmpItem instanceof PpmpItem) {
                throw new RuntimeException(sprintf(
                    'Missing PPMP item mapping for item_id %d in ppmp_id %d while seeding supply request.',
                    (int) $item->item_id,
                    (int) $ppmp->ppmp_id,
                ));
            }

            SupplyRequestItem::query()->updateOrCreate(
                [
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'ppmp_item_id' => (int) $ppmpItem->ppmp_item_id,
                ],
                [
                    'item_id' => (int) $item->item_id,
                    'item_name_snapshot' => (string) $item->item_name,
                    'quantity_requested' => (float) $line['quantity'],
                ],
            );
        }

        $service = app(SupplyRequestService::class);
        $submitted = $service->submit($supplyRequest->fresh(['items']) ?? $supplyRequest, $actor, 'Seeded office request submitted for demo workflow.');

        return $submitted->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $submitted;
    }

    private function seedProcurementFlow(
        User $actor,
        SupplyRequest $supplyRequest,
        Supplier $supplier,
        float $unitPrice,
        string $procurementMethod,
        string $expectedDeliveryDate,
        string $deliveryNotes,
    ): PurchaseOrder {
        $purchaseRequestService = app(PurchaseRequestService::class);
        $purchaseOrderService = app(PurchaseOrderService::class);

        $purchaseRequest = PurchaseRequest::query()->where('supply_request_id', (int) $supplyRequest->supply_request_id)->first();
        if (! $purchaseRequest instanceof PurchaseRequest) {
            $purchaseRequest = $purchaseRequestService->createFromSupplyRequest($actor, [
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'remarks' => 'Auto-created from seeded supply request.',
            ]);
        }

        if ((string) $purchaseRequest->status === 'OPEN') {
            $purchaseRequest = $purchaseRequestService->process($purchaseRequest, $actor, 'Opened for procurement processing.');
        }

        $purchaseOrder = PurchaseOrder::query()->where('purchase_request_id', (int) $purchaseRequest->purchase_request_id)->first();
        if (! $purchaseOrder instanceof PurchaseOrder) {
            $purchaseOrder = $purchaseOrderService->create($actor, [
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'supplier_id' => (int) $supplier->supplier_id,
                'procurement_method' => $procurementMethod,
                'expected_delivery_date' => $expectedDeliveryDate,
                'terms_notes' => 'Standard demo procurement terms.',
                'remarks' => $deliveryNotes,
            ]);
        }

        $purchaseOrder->loadMissing('items', 'purchaseRequest', 'supplyRequest');

        if (! $purchaseOrder->items()->exists()) {
            $supplyRequestItem = $supplyRequest->items()->first();
            if ($supplyRequestItem instanceof SupplyRequestItem) {
                $purchaseOrderService->addItem($purchaseOrder, $actor, [
                    'supply_request_item_id' => (int) $supplyRequestItem->supply_request_item_id,
                    'item_id' => (int) $supplyRequestItem->item_id,
                    'item_name_snapshot' => (string) $supplyRequestItem->item_name_snapshot,
                    'specification' => 'Demo procurement specification.',
                    'quantity_ordered' => (float) $supplyRequestItem->quantity_requested,
                    'unit' => 'piece',
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        if ((string) $purchaseOrder->status === 'DRAFT') {
            $purchaseOrder = $purchaseOrderService->place($purchaseOrder, $actor, 'Purchase order placed for demo workflow.');
        }

        return $purchaseOrder->fresh(['items', 'supplier', 'creator', 'purchaseRequest', 'supplyRequest']) ?? $purchaseOrder;
    }

    private function seedDeliveryAndDistributionFlow(
        User $gsoUser,
        User $officeUser,
        PurchaseOrder $purchaseOrder,
        Supplier $supplier,
    ): Delivery {
        $deliveryService = app(DeliveryService::class);
        $verificationService = app(DeliveryVerificationService::class);
        $inventoryService = app(InventoryService::class);
        $distributionService = app(DistributionRecordService::class);
        $claimService = app(ClaimService::class);

        $delivery = Delivery::query()->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)->first();
        if (! $delivery instanceof Delivery) {
            $delivery = $deliveryService->create($gsoUser, [
                'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                'supplier_id' => (int) $supplier->supplier_id,
                'expected_arrival_date' => now()->addDays(5)->toDateString(),
                'actual_delivery_date' => now()->addDays(7)->toDateString(),
                'remarks' => 'Delivery created for demo workflow.',
            ]);
        }

        if (! $delivery->items()->exists()) {
            $purchaseOrderItem = $purchaseOrder->items()->first();
            if ($purchaseOrderItem instanceof PurchaseOrderItem) {
                DeliveryItem::query()->create([
                    'delivery_id' => (int) $delivery->delivery_id,
                    'purchase_order_item_id' => (int) $purchaseOrderItem->purchase_order_item_id,
                    'expected_quantity' => (float) $purchaseOrderItem->quantity_ordered,
                    'received_quantity' => (float) $purchaseOrderItem->quantity_ordered,
                    'damaged_quantity' => 0,
                    'missing_quantity' => 0,
                    'item_condition' => 'GOOD',
                    'remarks' => 'Demo delivery verified without discrepancy.',
                ]);
            }
        }

        if ((string) $delivery->receipt_status === 'PENDING') {
            $delivery = $deliveryService->markReceived($delivery, $gsoUser, 'Received at the GSO receiving area.');
        }

        if ($delivery->verifications()->doesntExist()) {
            $verificationService->create($delivery, $gsoUser, [
                'verification_date' => now()->toDateString(),
                'verification_status' => 'APPROVED',
                'discrepancy_notes' => null,
                'remarks' => 'Verified and cleared for inventory sync in the demo workflow.',
            ]);
        }

        $delivery = $delivery->fresh(['items', 'purchaseOrder', 'supplier', 'receiver']) ?? $delivery;

        if ((string) $delivery->receipt_status !== 'VERIFIED') {
            $delivery->forceFill(['receipt_status' => 'VERIFIED'])->save();
            $delivery = $delivery->fresh(['items', 'purchaseOrder', 'supplier', 'receiver']) ?? $delivery;
        }

        $inventoryService->updateFromVerifiedDelivery($delivery, $gsoUser);

        $distributionRecord = DistributionRecord::query()->where('delivery_id', (int) $delivery->delivery_id)->first();
        if (! $distributionRecord instanceof DistributionRecord) {
            $distributionRecord = $distributionService->createRecord($gsoUser, [
                'supply_request_id' => (int) $purchaseOrder->supply_request_id,
                'delivery_id' => (int) $delivery->delivery_id,
                'office_id' => (int) $officeUser->office_id,
                'received_by' => (int) $officeUser->id,
                'release_date' => now()->toDateString(),
                'storage_location' => 'Main GSO storage bay',
                'notes' => 'Seeded distribution for office handoff.',
            ]);
        }

        if (! $distributionRecord->items()->exists()) {
            $deliveryItem = $delivery->items()->first();
            $purchaseOrderItem = $deliveryItem?->purchaseOrderItem;
            if ($deliveryItem instanceof DeliveryItem && $purchaseOrderItem instanceof PurchaseOrderItem) {
                $distributionService->addDistributionItem($distributionRecord, $gsoUser, [
                    'item_id' => (int) $purchaseOrderItem->item_id,
                    'received_by' => (int) $officeUser->id,
                    'item_name_snapshot' => (string) $purchaseOrderItem->item_name_snapshot,
                    'quantity_released' => min((float) $deliveryItem->received_quantity, (float) $purchaseOrderItem->quantity_ordered - 2),
                    'unit' => (string) $purchaseOrderItem->unit,
                    'remarks' => 'Released to the office for demo distribution.',
                ]);
            }
        }

        if ((string) $distributionRecord->status === 'RELEASED') {
            $distributionService->markReceivedByOffice($distributionRecord, $officeUser, 'Office acknowledged receipt of released supplies.');
        }

        if (! $distributionRecord->claims()->exists()) {
            $claim = $claimService->createClaim($officeUser, [
                'distribution_record_id' => (int) $distributionRecord->distribution_record_id,
                'office_id' => (int) $officeUser->office_id,
                'claim_type' => 'SHORTAGE',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office receiving desk',
                'remarks' => 'Minor discrepancy claimed for demo traceability.',
                'distribution_record' => $distributionRecord,
            ]);

            if ((string) $claim->status === 'OPEN') {
                $claimService->updateClaimStatus($claim, $gsoUser, [
                    'status' => 'IN_REVIEW',
                    'remarks' => 'Claim is under review for demo visibility.',
                ]);
            }
        }

        return $delivery->fresh(['purchaseOrder', 'supplier', 'receiver', 'items']) ?? $delivery;
    }

    private function seedAlerts(User $gsoUser, PurchaseOrder $purchaseOrder, Delivery $delivery): void
    {
        $alertService = app(AlertService::class);

        $lowStockItemId = (int) Item::query()->where('item_name', 'Printer Ink')->value('item_id');
        $lowStockInventory = Inventory::query()->where('item_id', $lowStockItemId)->first();

        if ($lowStockInventory instanceof Inventory) {
            $existing = Alert::query()->where('alert_type', 'LOW_STOCK')->where('office_id', $gsoUser->office_id)->first();
            if (! $existing instanceof Alert) {
                $alertService->create($gsoUser, [
                    'alert_type' => 'LOW_STOCK',
                    'severity' => 'HIGH',
                    'office_id' => (int) $gsoUser->office_id,
                    'alert_message' => 'Printer ink is at low stock and needs replenishment soon.',
                    'detected_at' => now(),
                ]);
            }
        }

        $existingDelayAlert = Alert::query()->where('alert_type', 'DELAYED_DELIVERY')->where('purchase_order_id', (int) $purchaseOrder->purchase_order_id)->first();
        if (! $existingDelayAlert instanceof Alert) {
            $alertService->create($gsoUser, [
                'alert_type' => 'DELAYED_DELIVERY',
                'severity' => 'MEDIUM',
                'purchase_order_id' => (int) $purchaseOrder->purchase_order_id,
                'supplier_id' => (int) $delivery->supplier_id,
                'office_id' => (int) $gsoUser->office_id,
                'alert_message' => 'Delivery was received after the expected date and was partially verified.',
                'detected_at' => now(),
            ]);
        }
    }

    private function normalizeGsoLinkedDemoData(User $gsoUser): void
    {
        $distributionService = app(DistributionRecordService::class);
        $claimService = app(ClaimService::class);

        $records = DistributionRecord::query()
            ->with(['supplyRequest.items', 'items', 'claims'])
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->orderBy('distribution_record_id')
            ->get();

        foreach ($records as $record) {
            if (! $record instanceof DistributionRecord) {
                continue;
            }

            if ($record->items->isNotEmpty()) {
                continue;
            }

            $supplyItems = $record->supplyRequest?->items ?? collect();
            foreach ($supplyItems as $supplyItem) {
                $inventory = Inventory::query()->where('item_id', (int) $supplyItem->item_id)->first();
                if (! $inventory instanceof Inventory) {
                    continue;
                }

                $releaseQty = min((float) $supplyItem->quantity_requested, max((float) $inventory->available_stock, 0));
                if ($releaseQty <= 0) {
                    continue;
                }

                try {
                    $distributionService->addDistributionItem($record, $gsoUser, [
                        'item_id' => (int) $supplyItem->item_id,
                        'received_by' => $record->received_by,
                        'item_name_snapshot' => (string) $supplyItem->item_name_snapshot,
                        'quantity_released' => round($releaseQty, 2),
                        'unit' => 'piece',
                        'remarks' => 'Seeder normalization: linked released item from supply request context.',
                    ]);
                } catch (Throwable $exception) {
                    Log::warning('DemoWorkflowSeeder distribution item normalization skipped.', [
                        'distribution_record_id' => (int) $record->distribution_record_id,
                        'item_id' => (int) $supplyItem->item_id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $claimTarget = DistributionRecord::query()
            ->with(['items'])
            ->whereIn('status', ['RELEASED', 'RECEIVED'])
            ->whereHas('items')
            ->whereDoesntHave('claims')
            ->orderByDesc('distribution_record_id')
            ->first();

        if (! $claimTarget instanceof DistributionRecord) {
            return;
        }

        $officeUser = User::query()
            ->where('office_id', (int) $claimTarget->office_id)
            ->whereHas('role', fn ($query) => $query->where('role_code', 'OFFICE_USER'))
            ->first();

        if (! $officeUser instanceof User) {
            return;
        }

        try {
            $claimService->createClaim($officeUser, [
                'distribution_record_id' => (int) $claimTarget->distribution_record_id,
                'office_id' => (int) $claimTarget->office_id,
                'claim_type' => 'QUANTITY_MISMATCH',
                'claim_date' => now()->toDateString(),
                'claim_location' => 'Office receiving point',
                'remarks' => 'Seeder normalization: quantity mismatch issue linked to a released record.',
                'distribution_record' => $claimTarget,
            ]);
        } catch (Throwable $exception) {
            Log::warning('DemoWorkflowSeeder claim normalization skipped.', [
                'distribution_record_id' => (int) $claimTarget->distribution_record_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
