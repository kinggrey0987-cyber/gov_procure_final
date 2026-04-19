<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryVerification;
use App\Models\DistributionRecord;
use App\Models\DistributionItem;
use App\Models\Inventory;
use App\Models\Item;
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
use App\Services\Analytics\AlertService;
use App\Services\Analytics\AnomalyDetectionService;
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

class DemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
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

        $execUser = $users->get('jeronopjer@gmail.com');
        $mpdoUser = $users->get('rienz6977@gmail.com');
        $gsoUser = $users->get('rienyhulleza1022@gmail.com');
        $procUser = $users->get('jayrossrusselherbias@gmail.com');
        $mswdoUser = $users->get('govprocure86@gmail.com');
        $engUser = $users->get('roengwapo09@gmail.com');
        $adminUser = $users->get('rienz123123@gmail.com');

        if (! $execUser instanceof User || ! $mpdoUser instanceof User || ! $gsoUser instanceof User || ! $procUser instanceof User || ! $adminUser instanceof User) {
            return;
        }

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
                officeId: (int) ($offices->get('MPDO') ?? 0),
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
                officeId: (int) ($offices->get('MSWDO') ?? 0),
                preparer: $mswdoUser instanceof User ? $mswdoUser : $mpdoUser,
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
                officeId: (int) ($offices->get('ENG') ?? 0),
                preparer: $engUser instanceof User ? $engUser : $mpdoUser,
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
        foreach ([1, 1, 1, 1, 25] as $index => $quantity) {
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

        $mswdoRequest = $this->seedSupplyRequest(
            actor: $adminUser,
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

        $engRequest = $this->seedSupplyRequest(
            actor: $engUser instanceof User ? $engUser : $mpdoUser,
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

        $finalPurchaseOrder = $purchaseOrders[4];
        $finalPurchaseOrder->loadMissing('items', 'purchaseRequest', 'supplyRequest');

        $delivery = $this->seedDeliveryAndDistributionFlow(
            gsoUser: $gsoUser,
            officeUser: $mpdoUser,
            purchaseOrder: $finalPurchaseOrder,
            supplier: $finalPurchaseOrder->supplier ?? $secondarySupplier,
        );

        $this->seedAlerts($gsoUser, $finalPurchaseOrder, $delivery);

        app(AnomalyDetectionService::class)->runExecutiveDetections($execUser);
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
                continue;
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
                continue;
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
            return $supplyRequest->fresh(['items', 'office', 'requester', 'approver', 'ppmp']) ?? $supplyRequest;
        }

        foreach ($lines as $line) {
            $item = Item::query()->where('item_name', $line['item_name'])->first();
            if (! $item instanceof Item) {
                continue;
            }

            $ppmpItem = PpmpItem::query()
                ->where('ppmp_id', (int) $ppmp->ppmp_id)
                ->where('item_id', (int) $item->item_id)
                ->first();

            if (! $ppmpItem instanceof PpmpItem) {
                continue;
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
}
