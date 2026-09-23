<?php

namespace App\Services\Procurement;

use App\Enums\UserRole;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Inventory;
use App\Models\Office;
use App\Models\ApprovalHistory;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PpmpItem;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;
use App\Services\Analytics\AnomalyService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationService $notificationService,
        private readonly AnomalyService $anomalyService,
        private readonly \App\Services\Inventory\InventoryMovementService $inventoryMovementService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createFromSupplyRequest(User $actor, array $payload): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        $supplyRequest = SupplyRequest::query()
            ->with('items')
            ->where('supply_request_id', (int) $payload['supply_request_id'])
            ->firstOrFail();

        if ((string) $supplyRequest->status !== 'SUBMITTED') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only SUBMITTED supply requests can become purchase requests.',
            ]);
        }

        if (! $supplyRequest->items->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Supply request must contain at least one item before conversion.',
            ]);
        }

        if (PurchaseRequest::query()->where('supply_request_id', $supplyRequest->supply_request_id)->exists()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'A purchase request already exists for this supply request.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $supplyRequest): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => $this->generateNextPrNumber(),
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => (int) $supplyRequest->office_id,
                'requested_by' => (int) $supplyRequest->requested_by,
                'status' => 'OPEN',
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recreateItemsFromSupplyRequest($purchaseRequest, $supplyRequest, $actor);

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request created from submitted supply request.');
            $this->recordAudit($actor, 'CREATE', $purchaseRequest);

            $this->notificationService->notifySupplyRequestOfficeRequester(
                supplyRequest: $supplyRequest,
                title: 'Supply Request Routed to Procurement',
                message: sprintf('Your supply request %s was converted into a purchase request by Procurement.', (string) $supplyRequest->request_code),
                notificationType: 'PROCUREMENT_PURCHASE_REQUEST_CREATED',
            );

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queueFromSupplyRequest(User $actor, array $payload): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        $supplyRequest = SupplyRequest::query()
            ->with(['office', 'items.item.inventory'])
            ->where('supply_request_id', (int) $payload['supply_request_id'])
            ->firstOrFail();

        if ((string) ($supplyRequest->office?->code ?? '') === 'GSO') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'GSO-origin supply requests are internal logistics records and cannot be queued through office procurement routing.',
            ]);
        }

        if ((string) $supplyRequest->status !== 'SUBMITTED') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only SUBMITTED supply requests can be queued for procurement.',
            ]);
        }

        if (! $supplyRequest->items->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Supply request must contain at least one item before queueing.',
            ]);
        }

        $existingPurchaseRequest = PurchaseRequest::query()
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->latest('purchase_request_id')
            ->first();

        if ($existingPurchaseRequest instanceof PurchaseRequest && (string) $existingPurchaseRequest->status !== 'RETURNED_FOR_REVISION') {
            throw ValidationException::withMessages([
                'supply_request_id' => 'A purchase request already exists for this supply request.',
            ]);
        }

        $shortageTotal = 0.0;
        foreach ($supplyRequest->items as $supplyRequestItem) {
            $requested = (float) $supplyRequestItem->quantity_requested;
            $available = (float) ($supplyRequestItem->item?->inventory?->available_stock ?? 0);
            $shortageTotal += max($requested - $available, 0);
        }

        if ($shortageTotal <= 0) {
            throw ValidationException::withMessages([
                'supply_request_id' => 'Only shortage requests can be queued for procurement.',
            ]);
        }

        return DB::transaction(function () use ($actor, $payload, $supplyRequest, $existingPurchaseRequest): PurchaseRequest {
            $remarks = $payload['remarks'] ?? 'Queued by GSO due to stock shortage.';

            if ($existingPurchaseRequest instanceof PurchaseRequest) {
                $purchaseRequest = $existingPurchaseRequest;
                $oldStatus = (string) $purchaseRequest->status;

                $purchaseRequest->forceFill([
                    'status' => 'OPEN',
                    'remarks' => $remarks,
                ])->save();

                $this->releaseReservedStockForPurchaseRequest($purchaseRequest, $actor);
                $purchaseRequest->items()->delete();
                $this->recreateItemsFromSupplyRequest($purchaseRequest, $supplyRequest, $actor);

                $this->recordStatus($purchaseRequest, $oldStatus, 'OPEN', $actor, 'Purchase request re-queued by GSO after office revision.');
                $this->recordAudit($actor, 'REQUEUE', $purchaseRequest);
            } else {
                $purchaseRequest = PurchaseRequest::query()->create([
                    'pr_number' => $this->generateNextPrNumber(),
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'office_id' => (int) $supplyRequest->office_id,
                    'requested_by' => (int) $supplyRequest->requested_by,
                    'status' => 'OPEN',
                    'remarks' => $remarks,
                ]);

                $this->recreateItemsFromSupplyRequest($purchaseRequest, $supplyRequest, $actor);

                $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request queued by GSO due to stock shortage.');
                $this->recordAudit($actor, 'QUEUE', $purchaseRequest);
            }

            $this->syncSupplyRequestStatus(
                $purchaseRequest,
                $actor,
                'FOR_PROCUREMENT',
                'Supply request queued by GSO for procurement due to stock shortage.'
            );

            $this->notificationService->notifySupplyRequestOfficeRequester(
                supplyRequest: $supplyRequest,
                title: 'Supply Request Forwarded to Procurement',
                message: sprintf('GSO forwarded your supply request %s to Procurement due to stock shortage.', (string) $supplyRequest->request_code),
                notificationType: 'OFFICE_SUPPLY_REQUEST_FORWARDED_TO_PROCUREMENT',
            );

            $this->notificationService->notifyRole(
                role: UserRole::PROCUREMENT_OFFICER,
                title: 'Purchase Request Queued for Review',
                message: sprintf(
                    'GSO queued %s from supply request %s for Procurement Unit review.',
                    (string) $purchaseRequest->pr_number,
                    (string) $supplyRequest->request_code,
                ),
                notificationType: 'PROCUREMENT_PURCHASE_REQUEST_QUEUED',
                referenceType: PurchaseRequest::class,
                referenceId: (int) $purchaseRequest->purchase_request_id,
            );

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    private function recreateItemsFromSupplyRequest(PurchaseRequest $purchaseRequest, SupplyRequest $supplyRequest, ?User $actor = null): void
    {
        $itemRows = [];
        foreach ($supplyRequest->items as $supplyRequestItem) {
            $itemId = (int) $supplyRequestItem->item_id;
            $requested = (float) $supplyRequestItem->quantity_requested;

            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();
            $available = (float) ($inventory?->available_stock ?? 0);

            $shortage = max($requested - $available, 0.0);
            $reserveAmount = min($requested, $available);

            if ($shortage > 0) {
                $itemRows[] = [
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => $itemId,
                    'quantity_requested' => round($shortage, 2),
                    'remarks' => $reserveAmount > 0
                        ? sprintf('Net shortage requested (%.2f requested, %.2f reserved from warehouse).', $requested, $reserveAmount)
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($reserveAmount > 0 && $inventory instanceof Inventory) {
                $inventory->forceFill([
                    'available_stock' => (float) $inventory->available_stock - $reserveAmount,
                    'reserved_quantity' => (float) $inventory->reserved_quantity + $reserveAmount,
                    'last_updated' => now(),
                ])->save();

                $this->inventoryMovementService->record(
                    inventoryId: (int) $inventory->inventory_id,
                    movementType: 'STOCK_RESERVATION',
                    referenceType: PurchaseRequest::class,
                    referenceId: (int) $purchaseRequest->purchase_request_id,
                    quantity: $reserveAmount,
                    actor: $actor,
                    remarks: sprintf('Stock reserved for PR %s (SR %s).', (string) $purchaseRequest->pr_number, (string) $supplyRequest->request_code),
                );
            }
        }

        if ($itemRows !== []) {
            PurchaseRequestItem::query()->insert($itemRows);
        }
    }

    public function createFromLowStockSignal(User $actor, Inventory $inventory, float $quantityRequested): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        if ((int) $inventory->item_id <= 0) {
            throw ValidationException::withMessages([
                'inventory_id' => 'Inventory record is not linked to a valid catalog item.',
            ]);
        }

        if ($quantityRequested <= 0) {
            throw ValidationException::withMessages([
                'quantity_requested' => 'Low-stock purchase quantity must be greater than zero.',
            ]);
        }

        $officeId = (int) ($actor->office_id ?? 0);
        if ($officeId <= 0) {
            throw ValidationException::withMessages([
                'office_id' => 'GSO officer must belong to an office before creating low-stock purchase requests.',
            ]);
        }

        $signalMarker = sprintf('LOW_STOCK_SIGNAL:inventory:%d', (int) $inventory->inventory_id);
        $governanceLabel = 'Proactive GSO Demand Forecast Signal - statistical stock-demand planning context only; not an office-approved PPMP and not a procurement approval.';

        $alreadyQueued = PurchaseRequest::query()
            ->whereIn('status', ['OPEN', 'PROCESSING'])
            ->where('remarks', 'like', '%'.$signalMarker.'%')
            ->exists();

        if ($alreadyQueued) {
            throw ValidationException::withMessages([
                'inventory_id' => 'An active purchase request is already queued from this low-stock signal.',
            ]);
        }

        return DB::transaction(function () use ($actor, $inventory, $quantityRequested, $officeId, $signalMarker, $governanceLabel): PurchaseRequest {
            $inventory->loadMissing('item');

            $item = $inventory->item;
            $itemCategoryId = (int) ($item?->item_category_id ?? 0);

            if ($itemCategoryId <= 0) {
                throw ValidationException::withMessages([
                    'inventory_id' => 'Inventory item must have a valid category for low-stock purchase request initiation.',
                ]);
            }

            $supplyRequest = SupplyRequest::query()->create([
                    'request_code' => $this->buildRequestCode($officeId),
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => null,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => sprintf(
                    'Proactive GSO Demand Forecast Signal for %s (inventory #%d). Statistical stock-demand planning context only; the Procurement Unit must still process any PR/PO.',
                    (string) ($inventory->item?->item_name ?? 'Unassigned Item'),
                    (int) $inventory->inventory_id
                ),
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'item_id' => (int) $inventory->item_id,
                'ppmp_item_id' => null,
                'item_name_snapshot' => (string) ($item?->item_name ?? 'Unassigned Item'),
                'quantity_requested' => round($quantityRequested, 2),
            ]);

            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => $this->generateNextPrNumber(),
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'status' => 'OPEN',
                'remarks' => sprintf(
                    '%s | Proactive GSO Demand Forecast Signal for %s. Statistical stock-demand planning context only; this is not automated PPMP approval, procurement approval, or PO approval.',
                    $signalMarker,
                    (string) ($inventory->item?->item_name ?? 'Unassigned Item')
                ),
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'item_id' => (int) $inventory->item_id,
                'quantity_requested' => round($quantityRequested, 2),
                'remarks' => 'Quantity proposed by proactive GSO demand forecast signal; Procurement Unit processing remains required.',
            ]);

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Purchase request queued from proactive GSO demand forecast signal; Procurement Unit processing and PO creation remain required.');
            $this->recordAudit($actor, 'AUTO_QUEUE_LOW_STOCK', $purchaseRequest);

            $deficit = max((float) $inventory->low_stock_threshold - (float) $inventory->available_stock, 0);
            $anomalyThreshold = max($deficit * 3, (float) $inventory->low_stock_threshold * 2, 1);

            if ($quantityRequested > $anomalyThreshold) {
                $severity = $quantityRequested > ($anomalyThreshold * 1.5) ? 'HIGH' : 'MEDIUM';

                $this->anomalyService->createOrUpdateSystemDetected([
                    'anomaly_type' => 'EXCESSIVE_QUANTITY',
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => (int) $inventory->item_id,
                    'office_id' => $officeId,
                    'detected_value' => round($quantityRequested, 2),
                    'expected_value' => round($deficit, 2),
                    'threshold_value' => round($anomalyThreshold, 2),
                    'detection_method' => 'LOW_STOCK_SIGNAL_QUANTITY_GUARD',
                    'severity' => $severity,
                    'remarks' => 'Low-stock initiated quantity exceeds configured threshold guardrail.',
                ]);
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function createFromPendingClaimReplacement(Claim $claim, User $actor, ?string $remarks = null): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        $claim->loadMissing(['distributionItem.item', 'office']);

        if ($claim->distributionItem === null) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Pending replacement procurement requires a linked claimed item line.',
            ]);
        }

        $distributionItem = $claim->distributionItem;
        $item = $distributionItem->item;
        $itemId = (int) $distributionItem->item_id;
        $itemName = (string) ($item?->item_name ?? $distributionItem->item_name_snapshot ?? 'Claim Replacement Item');

        $remainingDisputed = round(max((float) $claim->quantity_disputed - (float) $claim->replacement_quantity, 0), 2);
        if ($remainingDisputed <= 0) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'No remaining disputed quantity needs replacement procurement.',
            ]);
        }

        $availableStock = (float) (Inventory::query()->where('item_id', $itemId)->value('available_stock') ?? 0);
        $quantityToProcure = round(max($remainingDisputed - $availableStock, 0), 2);
        if ($quantityToProcure <= 0) {
            throw ValidationException::withMessages([
                'replacement_quantity' => 'Replacement stock is available. Release the replacement instead of marking the claim pending procurement.',
            ]);
        }

        $existing = PurchaseRequest::query()
            ->where('source_claim_id', (int) $claim->claims_id)
            ->where('source_type', 'CLAIM_REPLACEMENT_FOLLOW_UP')
            ->whereIn('status', ['OPEN', 'PROCESSING', 'RETURNED_FOR_REVISION'])
            ->latest('purchase_request_id')
            ->first();

        if ($existing instanceof PurchaseRequest) {
            throw ValidationException::withMessages([
                'replacement_quantity' => sprintf(
                    'A replacement purchase request is already active for this claim (%s).',
                    (string) $existing->pr_number
                ),
            ]);
        }

        $officeId = (int) (
            $claim->office_id
            ?? $claim->distributionRecord?->office_id
            ?? $actor->office_id
            ?? 1
        );

        $baseRemarks = trim((string) $remarks) !== ''
            ? trim((string) $remarks)
            : 'Generated from claim pending replacement due to insufficient GSO stock.';

        return DB::transaction(function () use ($actor, $claim, $itemId, $itemName, $officeId, $quantityToProcure, $baseRemarks): PurchaseRequest {
            if ((string) $claim->status !== 'PENDING_REPLACEMENT') {
                $oldClaimStatus = (string) $claim->status;
                $claim->forceFill([
                    'status' => 'PENDING_REPLACEMENT',
                    'resolution_type' => 'PENDING_REPLACEMENT',
                ])->save();

                $this->recordStatus($claim, $oldClaimStatus, 'PENDING_REPLACEMENT', $actor, 'Claim placed in pending replacement awaiting procurement.');
            }

            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => $this->buildRequestCode($officeId),
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => null,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => 'GENERAL',
                'purpose' => sprintf(
                    'Claim replacement procurement for %s from claim %s.',
                    $itemName,
                    (string) $claim->claim_code
                ),
                'priority_level' => 'HIGH',
                'status' => 'SUBMITTED',
                'review_notes' => sprintf(
                    'Generated because claim %s is pending replacement and GSO stock is insufficient. %s',
                    (string) $claim->claim_code,
                    $baseRemarks
                ),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            SupplyRequestItem::query()->create([
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'item_id' => $itemId,
                'ppmp_item_id' => null,
                'item_name_snapshot' => $itemName,
                'quantity_requested' => $quantityToProcure,
            ]);

            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => $this->generateNextPrNumber(),
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'source_claim_id' => (int) $claim->claims_id,
                'source_type' => 'CLAIM_REPLACEMENT_FOLLOW_UP',
                'status' => 'OPEN',
                'remarks' => sprintf(
                    'Replacement procurement generated from claim %s. %s',
                    (string) $claim->claim_code,
                    $baseRemarks
                ),
            ]);

            PurchaseRequestItem::query()->create([
                'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                'item_id' => $itemId,
                'quantity_requested' => $quantityToProcure,
                'remarks' => sprintf(
                    'Claim replacement quantity for claim %s; remaining unavailable quantity %.2f.',
                    (string) $claim->claim_code,
                    $quantityToProcure
                ),
            ]);

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Claim replacement purchase request queued due to insufficient GSO stock.');
            $this->recordAudit($actor, 'CREATE_FROM_CLAIM_REPLACEMENT', $purchaseRequest);

            $procurementUsers = User::query()
                ->whereHas('role', fn ($query) => $query->where('code', UserRole::PROCUREMENT_OFFICER->value))
                ->get();

            if ($procurementUsers->isNotEmpty()) {
                $this->notificationService->notifyUsers(
                    users: $procurementUsers,
                    title: 'Claim Replacement Purchase Request Queued',
                    message: sprintf('GSO queued replacement procurement for claim %s.', (string) $claim->claim_code),
                    notificationType: 'PROCUREMENT_PURCHASE_REQUEST_CREATED',
                    referenceType: PurchaseRequest::class,
                    referenceId: (int) $purchaseRequest->purchase_request_id,
                );
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest', 'sourceClaim']) ?? $purchaseRequest;
        });
    }

    public function createFromPartialDelivery(Delivery $delivery, User $actor, ?string $remarks = null): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        if ((string) $delivery->receipt_status !== 'PARTIALLY_RECEIVED') {
            throw ValidationException::withMessages([
                'delivery_id' => 'Only PARTIALLY_RECEIVED deliveries can generate follow-up procurement requests.',
            ]);
        }

        $delivery->loadMissing(['items.purchaseOrderItem.item.category', 'items.purchaseOrderItem.supplyRequestItem', 'purchaseOrder.supplyRequest', 'purchaseOrder.purchaseRequest.supplyRequest', 'verifications']);

        $existing = PurchaseRequest::query()
            ->where('source_delivery_id', (int) $delivery->delivery_id)
            ->whereIn('status', ['OPEN', 'PROCESSING', 'RETURNED_FOR_REVISION'])
            ->latest('purchase_request_id')
            ->first();

        if ($existing instanceof PurchaseRequest) {
            throw ValidationException::withMessages([
                'delivery_id' => sprintf(
                    'A follow-up purchase request already exists for this partially received delivery (%s).',
                    (string) $existing->pr_number
                ),
            ]);
        }

        $sourceSupplyRequest = $delivery->purchaseOrder?->supplyRequest
            ?? $delivery->purchaseOrder?->purchaseRequest?->supplyRequest;

        $discrepancyItems = $delivery->items
            ->filter(function ($item): bool {
                $missing = (float) $item->missing_quantity;
                $damaged = (float) $item->damaged_quantity;

                return ($missing + $damaged) > 0;
            })
            ->values();

        if ($discrepancyItems->isEmpty()) {
            throw ValidationException::withMessages([
                'delivery_id' => 'No discrepancy quantities were found for follow-up procurement.',
            ]);
        }

        $officeId = (int) (
            $sourceSupplyRequest?->office_id
            ?? $delivery->purchaseOrder?->purchaseRequest?->office_id
            ?? $actor->office_id
            ?? 1
        );
        $ppmpId = (int) ($sourceSupplyRequest?->ppmp_id ?? 0);

        $followUpRemarks = trim((string) $remarks);
        $verificationNotes = $delivery->verifications->last()?->discrepancy_notes;
        $baseRemarks = $followUpRemarks !== ''
            ? $followUpRemarks
            : (is_string($verificationNotes) && trim($verificationNotes) !== ''
                ? trim($verificationNotes)
                : 'Generated from partially received delivery discrepancy.');

        return DB::transaction(function () use ($actor, $delivery, $sourceSupplyRequest, $discrepancyItems, $officeId, $ppmpId, $baseRemarks): PurchaseRequest {
            $supplyRequest = SupplyRequest::query()->create([
                'request_code' => $this->buildRequestCode($officeId),
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'ppmp_id' => $ppmpId > 0 ? $ppmpId : null,
                'request_date' => now()->toDateString(),
                'needed_by_date' => now()->addDays(7)->toDateString(),
                'request_category' => (string) ($sourceSupplyRequest?->request_category ?? 'GENERAL'),
                'purpose' => sprintf(
                    'Follow-up procurement for partially received delivery %s linked to PO %s.',
                    (string) $delivery->delivery_code,
                    (string) ($delivery->purchaseOrder?->po_number ?? 'N/A')
                ),
                'priority_level' => (string) ($sourceSupplyRequest?->priority_level ?? 'HIGH'),
                'status' => 'SUBMITTED',
                'review_notes' => sprintf(
                    'Generated from discrepancy follow-up of delivery %s. %s',
                    (string) $delivery->delivery_code,
                    $baseRemarks
                ),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => $this->generateNextPrNumber(),
                'supply_request_id' => (int) $supplyRequest->supply_request_id,
                'office_id' => $officeId,
                'requested_by' => (int) $actor->id,
                'source_delivery_id' => (int) $delivery->delivery_id,
                'source_type' => 'PARTIAL_DELIVERY_FOLLOW_UP',
                'status' => 'OPEN',
                'remarks' => sprintf(
                    'Follow-up procurement generated from delivery %s. %s',
                    (string) $delivery->delivery_code,
                    $baseRemarks
                ),
            ]);

            $groupedDiscrepancies = [];
            foreach ($discrepancyItems as $deliveryItem) {
                if (! $deliveryItem instanceof DeliveryItem) {
                    continue;
                }

                $purchaseOrderItem = $deliveryItem->purchaseOrderItem;
                if (! $purchaseOrderItem) {
                    continue;
                }

                $spec = trim((string) ($purchaseOrderItem->specification ?? ''));
                $key = ((int) $purchaseOrderItem->item_id) . '::' . $spec;
                $quantityRequested = round((float) $deliveryItem->missing_quantity + (float) $deliveryItem->damaged_quantity, 2);

                if ($quantityRequested <= 0) {
                    continue;
                }

                if (isset($groupedDiscrepancies[$key])) {
                    $groupedDiscrepancies[$key]['quantity_requested'] += $quantityRequested;
                    $groupedDiscrepancies[$key]['missing'] += (float) $deliveryItem->missing_quantity;
                    $groupedDiscrepancies[$key]['damaged'] += (float) $deliveryItem->damaged_quantity;
                } else {
                    $groupedDiscrepancies[$key] = [
                        'delivery_item' => $deliveryItem,
                        'purchase_order_item' => $purchaseOrderItem,
                        'specification' => $spec,
                        'quantity_requested' => $quantityRequested,
                        'missing' => (float) $deliveryItem->missing_quantity,
                        'damaged' => (float) $deliveryItem->damaged_quantity,
                    ];
                }
            }

            foreach ($groupedDiscrepancies as $grouped) {
                $deliveryItem = $grouped['delivery_item'];
                $purchaseOrderItem = $grouped['purchase_order_item'];
                $catalogItem = $purchaseOrderItem->item;
                $quantityRequested = round((float) $grouped['quantity_requested'], 2);

                $sourceSupplyRequestItem = $purchaseOrderItem->supplyRequestItem;
                $ppmpItemId = $sourceSupplyRequestItem?->ppmp_item_id;

                if (! $ppmpItemId && $ppmpId > 0) {
                    $ppmpItemId = PpmpItem::query()
                        ->where('ppmp_id', $ppmpId)
                        ->where('item_id', $purchaseOrderItem->item_id)
                        ->value('ppmp_item_id');
                }

                $itemName = (string) (
                    $catalogItem?->item_name
                    ?? $purchaseOrderItem->item_name_snapshot
                    ?? 'Unassigned Item'
                );

                SupplyRequestItem::query()->create([
                    'supply_request_id' => (int) $supplyRequest->supply_request_id,
                    'item_id' => (int) $purchaseOrderItem->item_id,
                    'ppmp_item_id' => $ppmpItemId ? (int) $ppmpItemId : null,
                    'item_name_snapshot' => $itemName,
                    'quantity_requested' => $quantityRequested,
                ]);

                PurchaseRequestItem::query()->create([
                    'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                    'item_id' => (int) $purchaseOrderItem->item_id,
                    'source_delivery_item_id' => (int) $deliveryItem->delivery_item_id,
                    'quantity_requested' => $quantityRequested,
                    'remarks' => sprintf(
                        'Follow-up quantity from delivery %s discrepancy (missing: %.2f, damaged: %.2f)%s.',
                        (string) $delivery->delivery_code,
                        (float) $grouped['missing'],
                        (float) $grouped['damaged'],
                        $grouped['specification'] !== '' ? ' | Spec: '.$grouped['specification'] : ''
                    ),
                ]);
            }

            $purchaseRequest->loadMissing(['items', 'office', 'requester', 'supplyRequest']);

            if (! $purchaseRequest->items->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'delivery_id' => 'No eligible discrepancy items were available for follow-up procurement.',
                ]);
            }

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, 'Follow-up purchase request created from partially received delivery discrepancy.');
            $this->recordAudit($actor, 'CREATE_FROM_PARTIAL_DELIVERY', $purchaseRequest);

            $procurementUsers = User::query()
                ->whereHas('role', fn ($query) => $query->where('code', UserRole::PROCUREMENT_OFFICER->value))
                ->get();

            if ($procurementUsers->isNotEmpty()) {
                $this->notificationService->notifyUsers(
                    users: $procurementUsers,
                    title: 'Follow-up Purchase Request Queued',
                    message: sprintf('GSO queued a discrepancy follow-up purchase request from delivery %s.', (string) $delivery->delivery_code),
                    notificationType: 'PROCUREMENT_PURCHASE_REQUEST_CREATED',
                    referenceType: PurchaseRequest::class,
                    referenceId: (int) $purchaseRequest->purchase_request_id,
                );
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest', 'sourceDelivery']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(PurchaseRequest $purchaseRequest, User $actor, array $payload): PurchaseRequest
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        return DB::transaction(function () use ($purchaseRequest, $actor, $payload): PurchaseRequest {
            $purchaseRequest->fill([
                'remarks' => $payload['remarks'] ?? $purchaseRequest->remarks,
            ])->save();

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request updated.');
            $this->recordAudit($actor, 'UPDATE', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function process(PurchaseRequest $purchaseRequest, User $actor, ?string $remarks = null): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if ((string) $purchaseRequest->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN purchase requests can be moved to PROCESSING.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $remarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $purchaseRequest->forceFill([
                'status' => 'PROCESSING',
                'remarks' => $remarks ?? $purchaseRequest->remarks,
            ])->save();

            $this->syncSupplyRequestStatus(
                $purchaseRequest,
                $actor,
                'FOR_PROCUREMENT',
                'Linked purchase request moved to procurement processing.'
            );

            $this->recordStatus($purchaseRequest, $oldStatus, 'PROCESSING', $actor, 'Purchase request is now in procurement processing.');
            $this->recordAudit($actor, 'PROCESS', $purchaseRequest);

            $supplyRequest = $purchaseRequest->supplyRequest()->first();
            if ($supplyRequest instanceof SupplyRequest) {
                $this->notificationService->notifySupplyRequestOfficeRequester(
                    supplyRequest: $supplyRequest,
                    title: 'Supply Request Processing Started',
                    message: sprintf('Procurement started processing your supply request %s.', (string) $supplyRequest->request_code),
                    notificationType: 'OFFICE_SUPPLY_REQUEST_PROCUREMENT_PROCESSING',
                );
            }

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function returnForRevision(PurchaseRequest $purchaseRequest, User $actor, string $remarks): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN or PROCESSING purchase requests can be returned for revision.',
            ]);
        }

        $finalRemarks = trim($remarks);
        if ($finalRemarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when returning for revision.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $finalRemarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $this->releaseReservedStockForPurchaseRequest($purchaseRequest, $actor);

            $purchaseRequest->forceFill([
                'status' => 'RETURNED_FOR_REVISION',
                'remarks' => $finalRemarks,
            ])->save();

            $this->syncSupplyRequestStatus(
                $purchaseRequest,
                $actor,
                'DRAFT',
                'Linked purchase request returned for revision by procurement. '.$finalRemarks
            );

            $this->recordStatus($purchaseRequest, $oldStatus, 'RETURNED_FOR_REVISION', $actor, 'Purchase request returned for revision by procurement.');
            $this->recordAudit($actor, 'RETURN_FOR_REVISION', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    public function reject(PurchaseRequest $purchaseRequest, User $actor, string $remarks): PurchaseRequest
    {
        $this->assertProcurementActor($actor);

        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only OPEN or PROCESSING purchase requests can be rejected.',
            ]);
        }

        $finalRemarks = trim($remarks);
        if ($finalRemarks === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when rejecting a request.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $actor, $finalRemarks): PurchaseRequest {
            $oldStatus = (string) $purchaseRequest->status;

            $this->releaseReservedStockForPurchaseRequest($purchaseRequest, $actor);

            $purchaseRequest->forceFill([
                'status' => 'REJECTED',
                'remarks' => $finalRemarks,
            ])->save();

            $this->syncSupplyRequestStatus(
                $purchaseRequest,
                $actor,
                'REJECTED',
                'Linked purchase request rejected by procurement. '.$finalRemarks
            );

            $this->recordStatus($purchaseRequest, $oldStatus, 'REJECTED', $actor, 'Purchase request rejected by procurement.');
            $this->recordAudit($actor, 'REJECT', $purchaseRequest);

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addItem(PurchaseRequest $purchaseRequest, User $actor, array $payload): PurchaseRequestItem
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        return DB::transaction(function () use ($purchaseRequest, $actor, $payload): PurchaseRequestItem {
            $item = $purchaseRequest->items()->create([
                'item_id' => (int) $payload['item_id'],
                'quantity_requested' => (float) $payload['quantity_requested'],
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request item added.');
            $this->recordAudit($actor, 'ADD_ITEM', $purchaseRequest);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateItem(PurchaseRequest $purchaseRequest, PurchaseRequestItem $item, User $actor, array $payload): PurchaseRequestItem
    {
        $this->assertProcurementActor($actor);
        $this->assertRequestIsMutable($purchaseRequest);

        if ((int) $item->purchase_request_id !== (int) $purchaseRequest->purchase_request_id) {
            throw ValidationException::withMessages([
                'purchase_request_item_id' => 'Item does not belong to this purchase request.',
            ]);
        }

        return DB::transaction(function () use ($purchaseRequest, $item, $actor, $payload): PurchaseRequestItem {
            $item->fill([
                'item_id' => (int) $payload['item_id'],
                'quantity_requested' => (float) $payload['quantity_requested'],
                'remarks' => $payload['remarks'] ?? null,
            ])->save();

            $this->recordStatus($purchaseRequest, (string) $purchaseRequest->status, (string) $purchaseRequest->status, $actor, 'Purchase request item updated.');
            $this->recordAudit($actor, 'UPDATE_ITEM', $purchaseRequest);

            return $item;
        });
    }

    public function generateNextPrNumber(?\DateTimeInterface $date = null): string
    {
        $targetDate = $date ?? now();
        $prefix = sprintf('PR-%s-', $targetDate->format('Y-m'));

        $latest = PurchaseRequest::query()
            ->where('pr_number', 'like', $prefix.'%')
            ->orderByDesc('pr_number')
            ->value('pr_number');

        $nextSequence = 1;
        if (is_string($latest) && preg_match('/^PR-\d{4}-\d{2}-(\d+)$/', $latest, $matches) === 1) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = sprintf('%s%04d', $prefix, $nextSequence);
            $nextSequence++;
        } while (PurchaseRequest::query()->where('pr_number', $candidate)->exists());

        return $candidate;
    }

    private function buildRequestCode(int $officeId): string
    {
        $office = Office::query()->find($officeId);
        $officeCode = $office ? (string) $office->code : 'GEN';
        $prefix = sprintf('SR-%s-%s-', $officeCode, now()->format('Y-m'));

        $countThisMonth = SupplyRequest::query()
            ->where('office_id', $officeId)
            ->where('request_code', 'like', $prefix.'%')
            ->count();

        $nextSequence = $countThisMonth + 1;
        do {
            $code = sprintf('%s%04d', $prefix, $nextSequence);
            $nextSequence++;
        } while (SupplyRequest::query()->where('request_code', $code)->exists());

        return $code;
    }

    private function assertProcurementActor(User $actor): void
    {
        if (! $actor->isProcurement()) {
            throw ValidationException::withMessages([
                'role' => 'Only Procurement Unit users can perform this action.',
            ]);
        }
    }

    private function assertGsoActor(User $actor): void
    {
        if (! $actor->isGsoOfficer()) {
            throw ValidationException::withMessages([
                'role' => 'Only GSO officers can queue procurement requests from inventory.',
            ]);
        }
    }

    private function assertRequestIsMutable(PurchaseRequest $purchaseRequest): void
    {
        if (! in_array((string) $purchaseRequest->status, ['OPEN', 'PROCESSING', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This purchase request can no longer be modified.',
            ]);
        }
    }

    private function recordStatus(PurchaseRequest $purchaseRequest, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => PurchaseRequest::class,
            'reference_id' => (int) $purchaseRequest->purchase_request_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function syncSingleSupplyRequestStatus(
        SupplyRequest $supplyRequest,
        User $actor,
        string $newStatus,
        string $remarks
    ): void {
        $oldStatus = (string) $supplyRequest->status;
        if ($oldStatus === $newStatus) {
            return;
        }

        $supplyRequest->forceFill([
            'status' => $newStatus,
            'review_notes' => $remarks,
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        StatusHistory::query()->create([
            'reference_type' => SupplyRequest::class,
            'reference_id' => (int) $supplyRequest->supply_request_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => (int) $actor->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function syncSupplyRequestStatus(
        PurchaseRequest $purchaseRequest,
        User $actor,
        string $newStatus,
        string $remarks
    ): void {
        $supplyRequest = $purchaseRequest->supplyRequest()->first();
        if (! $supplyRequest instanceof SupplyRequest) {
            return;
        }

        $this->syncSingleSupplyRequestStatus($supplyRequest, $actor, $newStatus, $remarks);

        $message = match ($newStatus) {
            'DRAFT' => 'Your supply request was returned for revision by Procurement. Please review the remarks and resubmit it after updating.',
            'REJECTED' => 'Your supply request was rejected by Procurement. Please review the remarks for the reason.',
            'FOR_PROCUREMENT' => 'Your supply request is now being processed by Procurement.',
            default => 'Your supply request status was updated by Procurement.',
        };

        $this->notificationService->notifySupplyRequestOfficeRequester(
            supplyRequest: $supplyRequest,
            title: 'Supply Request Status Updated',
            message: $message,
            notificationType: 'OFFICE_SUPPLY_REQUEST_STATUS_UPDATED',
        );
    }

    private function recordAudit(User $actor, string $actionType, PurchaseRequest $purchaseRequest): void
    {
        $this->auditLogService->log(
            actor: $actor,
            moduleName: 'Procurement.PurchaseRequest',
            actionType: $actionType,
            referenceType: PurchaseRequest::class,
            referenceId: (int) $purchaseRequest->purchase_request_id,
            details: [
                'supply_request_id' => $purchaseRequest->supply_request_id,
                'office_id' => $purchaseRequest->office_id,
                'status' => $purchaseRequest->status,
            ],
            logDomain: 'BUSINESS',
            officeId: (int) $purchaseRequest->office_id,
        );
    }

    /**
     * Create a single consolidated purchase request from multiple supply requests of the same office.
     *
     * This method aggregates all eligible (SUBMITTED, shortage) supply requests for an office
     * into one PR, merging item quantities by item_id to avoid duplicate line items.
     *
     * @param  int[]  $supplyRequestIds
     */
    public function queueConsolidatedFromOffice(User $actor, int $officeId, array $supplyRequestIds): PurchaseRequest
    {
        $this->assertGsoActor($actor);

        $office = Office::query()->findOrFail($officeId);
        if ((string) ($office->code ?? '') === 'GSO') {
            throw ValidationException::withMessages([
                'office_id' => 'GSO-origin supply requests cannot be consolidated for procurement routing.',
            ]);
        }

        $supplyRequests = SupplyRequest::query()
            ->with(['items.item.inventory'])
            ->where('office_id', $officeId)
            ->whereIn('supply_request_id', $supplyRequestIds)
            ->get();

        if ($supplyRequests->isEmpty()) {
            throw ValidationException::withMessages([
                'supply_request_ids' => 'No supply requests found for the selected office.',
            ]);
        }

        // Validate all supply requests are SUBMITTED and have shortages
        $eligible = $supplyRequests->filter(function (SupplyRequest $request): bool {
            if ((string) $request->status !== 'SUBMITTED') {
                return false;
            }

            // Check that there's no existing active PR
            $hasActivePr = PurchaseRequest::query()
                ->where('supply_request_id', $request->supply_request_id)
                ->whereIn('status', ['OPEN', 'PROCESSING'])
                ->exists();

            return ! $hasActivePr;
        });

        if ($eligible->count() < 2) {
            throw ValidationException::withMessages([
                'supply_request_ids' => 'Consolidation requires at least 2 eligible supply requests to merge. For a single request, initiate a direct purchase request instead.',
            ]);
        }

        // Merge items strictly by (item_id + specification), summing quantities for identical specs
        $mergedItems = [];
        foreach ($eligible as $supplyRequest) {
            $supplyRequest->loadMissing(['items.item', 'items.ppmpItem']);
            foreach ($supplyRequest->items as $item) {
                $itemId = (int) $item->item_id;
                $spec = trim((string) ($item->ppmpItem?->remarks ?? ''));
                $key = $itemId . '::' . $spec;
                $requested = (float) $item->quantity_requested;

                if (isset($mergedItems[$key])) {
                    $mergedItems[$key]['quantity_requested'] += $requested;
                    $mergedItems[$key]['source_item_ids'][] = (int) $item->supply_request_item_id;
                } else {
                    $mergedItems[$key] = [
                        'item_id' => $itemId,
                        'specification' => $spec,
                        'quantity_requested' => $requested,
                        'item_name_snapshot' => (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item'),
                        'source_item_ids' => [(int) $item->supply_request_item_id],
                    ];
                }
            }
        }

        if (count($mergedItems) === 0) {
            throw ValidationException::withMessages([
                'supply_request_ids' => 'No items found across the selected supply requests.',
            ]);
        }

        // Use the first eligible supply request as the "primary" link for the consolidated PR
        $primarySupplyRequest = $eligible->first();

        return DB::transaction(function () use ($actor, $office, $eligible, $mergedItems, $primarySupplyRequest): PurchaseRequest {
            $sourceRequestCodes = $eligible->pluck('request_code')->implode(', ');

            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => $this->generateNextPrNumber(),
                'supply_request_id' => (int) $primarySupplyRequest->supply_request_id,
                'office_id' => (int) $office->id,
                'requested_by' => (int) $actor->id,
                'status' => 'OPEN',
                'remarks' => sprintf(
                    'Consolidated PR for %s from %d supply request(s): %s.',
                    (string) $office->name,
                    $eligible->count(),
                    $sourceRequestCodes,
                ),
            ]);

            $itemRows = [];
            foreach ($mergedItems as $merged) {
                $itemId = (int) $merged['item_id'];
                $spec = (string) ($merged['specification'] ?? '');
                $totalRequested = (float) $merged['quantity_requested'];

                /** @var Inventory|null $inventory */
                $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();
                $available = (float) ($inventory?->available_stock ?? 0);

                $shortage = max($totalRequested - $available, 0.0);
                $reserveAmount = min($totalRequested, $available);

                if ($shortage > 0) {
                    $remarkParts = [];
                    if ($spec !== '') {
                        $remarkParts[] = "Spec: {$spec}";
                    }
                    if ($reserveAmount > 0) {
                        $remarkParts[] = sprintf('Consolidated net shortage (%.2f requested, %.2f reserved from warehouse).', $totalRequested, $reserveAmount);
                    } else {
                        $remarkParts[] = 'Consolidated from office supply requests.';
                    }

                    $itemRows[] = [
                        'purchase_request_id' => (int) $purchaseRequest->purchase_request_id,
                        'item_id' => $itemId,
                        'quantity_requested' => round($shortage, 2),
                        'remarks' => implode(' | ', $remarkParts),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($reserveAmount > 0 && $inventory instanceof Inventory) {
                    $inventory->forceFill([
                        'available_stock' => (float) $inventory->available_stock - $reserveAmount,
                        'reserved_quantity' => (float) $inventory->reserved_quantity + $reserveAmount,
                        'last_updated' => now(),
                    ])->save();

                    $this->inventoryMovementService->record(
                        inventoryId: (int) $inventory->inventory_id,
                        movementType: 'STOCK_RESERVATION',
                        referenceType: PurchaseRequest::class,
                        referenceId: (int) $purchaseRequest->purchase_request_id,
                        quantity: $reserveAmount,
                        actor: $actor,
                        remarks: sprintf('Stock reserved for consolidated PR %s from %d supply requests.', (string) $purchaseRequest->pr_number, $eligible->count()),
                    );
                }
            }

            if ($itemRows !== []) {
                PurchaseRequestItem::query()->insert($itemRows);
            }

            // Update all source supply requests to FOR_PROCUREMENT
            foreach ($eligible as $supplyRequest) {
                $this->syncSingleSupplyRequestStatus(
                    $supplyRequest,
                    $actor,
                    'FOR_PROCUREMENT',
                    sprintf('Supply request consolidated into %s for office %s.', (string) $purchaseRequest->pr_number, (string) $office->name),
                );
            }

            $this->recordStatus($purchaseRequest, null, 'OPEN', $actor, sprintf(
                'Consolidated purchase request created from %d supply request(s) for %s.',
                $eligible->count(),
                (string) $office->name,
            ));
            $this->recordAudit($actor, 'CONSOLIDATED_QUEUE', $purchaseRequest);

            $this->notificationService->notifyRole(
                role: UserRole::PROCUREMENT_OFFICER,
                title: 'Consolidated Purchase Request Queued',
                message: sprintf(
                    'GSO consolidated %d supply request(s) from %s into %s for Procurement Unit review.',
                    $eligible->count(),
                    (string) $office->name,
                    (string) $purchaseRequest->pr_number,
                ),
                notificationType: 'PROCUREMENT_PURCHASE_REQUEST_QUEUED',
                referenceType: PurchaseRequest::class,
                referenceId: (int) $purchaseRequest->purchase_request_id,
            );

            return $purchaseRequest->fresh(['items', 'office', 'requester', 'supplyRequest']) ?? $purchaseRequest;
        });
    }

    private function releaseReservedStockForPurchaseRequest(PurchaseRequest $purchaseRequest, ?User $actor = null): void
    {
        $reservationMovements = \App\Models\InventoryMovement::query()
            ->where('movement_type', 'STOCK_RESERVATION')
            ->where('reference_type', PurchaseRequest::class)
            ->where('reference_id', (int) $purchaseRequest->purchase_request_id)
            ->get();

        foreach ($reservationMovements as $movement) {
            /** @var Inventory|null $inventory */
            $inventory = Inventory::query()->where('inventory_id', (int) $movement->inventory_id)->lockForUpdate()->first();
            if ($inventory instanceof Inventory) {
                $restoreQty = min((float) $movement->quantity, (float) $inventory->reserved_quantity);
                if ($restoreQty > 0) {
                    $inventory->forceFill([
                        'available_stock' => (float) $inventory->available_stock + $restoreQty,
                        'reserved_quantity' => max((float) $inventory->reserved_quantity - $restoreQty, 0),
                        'last_updated' => now(),
                    ])->save();
                }
            }

            $this->inventoryMovementService->record(
                inventoryId: (int) $movement->inventory_id,
                movementType: 'RESERVATION_REVERSAL',
                referenceType: PurchaseRequest::class,
                referenceId: (int) $purchaseRequest->purchase_request_id,
                quantity: (float) $movement->quantity,
                actor: $actor,
                remarks: sprintf('Reservation reversed after PR %s status change.', (string) $purchaseRequest->pr_number),
            );

            $movement->forceFill(['movement_type' => 'STOCK_RESERVATION_RELEASED'])->save();
        }
    }
}
