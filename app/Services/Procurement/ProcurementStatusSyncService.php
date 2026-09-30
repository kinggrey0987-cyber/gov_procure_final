<?php

namespace App\Services\Procurement;

use App\Models\ApprovalHistory;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Services\Inventory\SupplyRequestFollowUpService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class ProcurementStatusSyncService
{
    public function __construct(
        private readonly SupplyRequestFollowUpService $followUpService,
        private readonly ConsolidationTraceabilityService $traceabilityService,
    ) {
    }

    public function syncCompletedStatusesForReceivedSupplyRequest(SupplyRequest $supplyRequest, ?User $actor = null): void
    {
        DB::transaction(function () use ($supplyRequest, $actor): void {
            $supplyRequest->loadMissing([
                'items',
                'distributionRecords.items',
                'purchaseRequests',
                'purchaseOrders',
            ]);

            if ($this->isFullyReceived($supplyRequest)) {
                $remarks = 'Linked supply request was fully received by the requesting office.';

                $this->transitionSupplyRequest($supplyRequest, $actor, $remarks);
                $this->transitionPurchaseRequests($supplyRequest->purchaseRequests, $actor, $remarks);
                $this->transitionPurchaseOrders($supplyRequest->purchaseOrders, $actor, $remarks);

                $childIds = $this->resolveFulfillmentChildSupplyRequestIds($supplyRequest);
                if (! empty($childIds)) {
                    $children = SupplyRequest::query()
                        ->with(['items', 'distributionRecords.items', 'purchaseRequests', 'purchaseOrders'])
                        ->whereIn('supply_request_id', $childIds)
                        ->get();
                    foreach ($children as $child) {
                        if ($this->canSyncChildWhenParentFullyReceived($child, $supplyRequest)) {
                            $childRemarks = 'Parent supply request lineage was fully received by the requesting office.';
                            $this->transitionSupplyRequest($child, $actor, $childRemarks);
                            $this->transitionPurchaseRequests($child->purchaseRequests, $actor, $childRemarks);
                            $this->transitionPurchaseOrders($child->purchaseOrders, $actor, $childRemarks);
                        }
                    }
                }
            }

            $current = $supplyRequest;
            $visitedParentIds = [(int) $supplyRequest->supply_request_id => true];
            while (true) {
                $parent = $this->followUpService->resolveParentSupplyRequest($current);
                if (! $parent instanceof SupplyRequest) {
                    break;
                }
                $parentId = (int) $parent->supply_request_id;
                if (isset($visitedParentIds[$parentId])) {
                    break;
                }
                $visitedParentIds[$parentId] = true;

                $parent->loadMissing([
                    'items',
                    'distributionRecords.items',
                    'purchaseRequests',
                    'purchaseOrders',
                ]);
                if ($this->isFullyReceived($parent)) {
                    $remarks = 'Linked supply request was fully received across follow-up deliveries.';
                    $this->transitionSupplyRequest($parent, $actor, $remarks);
                    $this->transitionPurchaseRequests($parent->purchaseRequests, $actor, $remarks);
                    $this->transitionPurchaseOrders($parent->purchaseOrders, $actor, $remarks);

                    $siblingIds = $this->resolveFulfillmentChildSupplyRequestIds($parent);
                    if (! empty($siblingIds)) {
                        $siblings = SupplyRequest::query()
                            ->with(['items', 'distributionRecords.items', 'purchaseRequests', 'purchaseOrders'])
                            ->whereIn('supply_request_id', $siblingIds)
                            ->get();
                        foreach ($siblings as $sibling) {
                            if ($this->canSyncChildWhenParentFullyReceived($sibling, $parent)) {
                                $siblingRemarks = 'Parent supply request lineage was fully received across follow-up deliveries.';
                                $this->transitionSupplyRequest($sibling, $actor, $siblingRemarks);
                                $this->transitionPurchaseRequests($sibling->purchaseRequests, $actor, $siblingRemarks);
                                $this->transitionPurchaseOrders($sibling->purchaseOrders, $actor, $siblingRemarks);
                            }
                        }
                    }
                }

                $current = $parent;
            }

            // Consolidated procurement constituent fulfillment check
            $rootSr = $this->followUpService->resolveRootSupplyRequest($supplyRequest);
            $consolidatedCandidates = $this->resolveConsolidatedConstituentSupplyRequests($rootSr);
            if ($consolidatedCandidates->count() > 1) {
                $allCandidatesFullyReceived = true;
                foreach ($consolidatedCandidates as $candidate) {
                    if ($this->isFullyReceived($candidate)) {
                        $constituentRemarks = 'Consolidated procurement constituent was fully received across deliveries.';
                        $this->transitionSupplyRequest($candidate, $actor, $constituentRemarks);
                        $this->transitionPurchaseRequests($candidate->purchaseRequests, $actor, $constituentRemarks);
                        $this->transitionPurchaseOrders($candidate->purchaseOrders, $actor, $constituentRemarks);
                    } else {
                        $allCandidatesFullyReceived = false;
                    }
                }

                if ($allCandidatesFullyReceived) {
                    $consolidatedRemarks = 'Consolidated procurement was fully received across follow-up deliveries.';
                    foreach ($consolidatedCandidates as $candidate) {
                        $childIds = $this->resolveFulfillmentChildSupplyRequestIds($candidate);
                        if (! empty($childIds)) {
                            $children = SupplyRequest::query()
                                ->with(['items', 'distributionRecords.items', 'purchaseRequests', 'purchaseOrders'])
                                ->whereIn('supply_request_id', $childIds)
                                ->get();
                            foreach ($children as $child) {
                                if ($this->canSyncChildWhenParentFullyReceived($child, $candidate)) {
                                    $this->transitionSupplyRequest($child, $actor, $consolidatedRemarks);
                                    $this->transitionPurchaseRequests($child->purchaseRequests, $actor, $consolidatedRemarks);
                                    $this->transitionPurchaseOrders($child->purchaseOrders, $actor, $consolidatedRemarks);
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    public function syncAllReceivedSupplyRequests(?User $actor = null): int
    {
        $synced = 0;

        SupplyRequest::query()
            ->with(['items', 'distributionRecords.items', 'purchaseRequests', 'purchaseOrders'])
            ->whereHas('distributionRecords', fn ($query) => $query->where('status', 'RECEIVED'))
            ->orderBy('supply_request_id')
            ->chunkById(100, function ($requests) use ($actor, &$synced): void {
                foreach ($requests as $supplyRequest) {
                    if (! $supplyRequest instanceof SupplyRequest || ! $this->isFullyReceived($supplyRequest)) {
                        continue;
                    }

                    $this->syncCompletedStatusesForReceivedSupplyRequest($supplyRequest, $actor);
                    $synced++;
                }
            }, 'supply_request_id', 'supply_request_id');

        return $synced;
    }

    public function syncCompletedProactiveAndRestockRequests(?User $actor = null): int
    {
        $synced = 0;

        $prs = PurchaseRequest::query()
            ->with(['purchaseOrders.items', 'purchaseOrders.deliveries.items', 'supplyRequest'])
            ->whereIn('status', ['OPEN', 'PROCESSING'])
            ->where('remarks', 'like', '%LOW_STOCK_SIGNAL:inventory:%')
            ->get();

        foreach ($prs as $pr) {
            if ($pr->purchaseOrders->isEmpty()) {
                continue;
            }

            $allPosCompleted = true;

            foreach ($pr->purchaseOrders as $po) {
                $hasPendingDeliveries = $po->deliveries()
                    ->whereNotIn('receipt_status', ['VERIFIED', 'COMPLETED', 'CANCELLED'])
                    ->exists();

                if ($hasPendingDeliveries || $po->deliveries->isEmpty()) {
                    $allPosCompleted = false;
                    break;
                }

                $verifiedDeliveries = $po->deliveries->where('receipt_status', 'VERIFIED');
                foreach ($po->items as $poItem) {
                    $orderedQty = (float) $poItem->quantity_ordered;
                    $receivedQty = (float) $verifiedDeliveries
                        ->flatMap(fn (Delivery $del) => $del->items)
                        ->where('purchase_order_item_id', (int) $poItem->purchase_order_item_id)
                        ->sum('received_quantity');

                    if ($receivedQty < $orderedQty) {
                        $allPosCompleted = false;
                        break 2;
                    }
                }

                if (! in_array((string) $po->status, ['COMPLETED', 'CLOSED'], true)) {
                    $po->forceFill(['status' => 'COMPLETED'])->save();
                }
            }

            if ($allPosCompleted) {
                $oldStatus = (string) $pr->status;
                $pr->forceFill(['status' => 'COMPLETED'])->save();

                StatusHistory::query()->create([
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $pr->purchase_request_id,
                    'old_status' => $oldStatus,
                    'new_status' => 'COMPLETED',
                    'changed_by' => $actor?->id,
                    'remarks' => 'Proactive replenishment completed upon verified delivery of all items.',
                    'changed_at' => now(),
                ]);

                if ($pr->supplyRequest && ! in_array((string) $pr->supplyRequest->status, ['RECEIVED', 'COMPLETED'], true)) {
                    // Only mark received if genuinely fully received by office
                    if ($this->isFullyReceived($pr->supplyRequest)) {
                        $pr->supplyRequest->forceFill(['status' => 'RECEIVED'])->save();
                    }
                }

                $synced++;
            }
        }

        return $synced;
    }

    public function isFullyReceived(SupplyRequest $supplyRequest): bool
    {
        $supplyRequest->loadMissing('items');
        if ($supplyRequest->items->isEmpty()) {
            return false;
        }

        // Active or unresolved claims prevent a supply request from being fully received
        if ($this->hasUnresolvedClaims($supplyRequest)) {
            return false;
        }

        $fulfillment = $this->traceabilityService->calculateAllocatedFulfillment($supplyRequest);

        return (bool) ($fulfillment['is_fully_received'] ?? false);
    }

    public function hasUnresolvedClaims(SupplyRequest $supplyRequest): bool
    {
        $distributionRecordIds = $this->allFulfillmentDistributionRecords($supplyRequest)
            ->pluck('distribution_record_id')
            ->filter()
            ->all();

        if (empty($distributionRecordIds)) {
            return false;
        }

        return \App\Models\Claim::query()
            ->whereIn('distribution_record_id', $distributionRecordIds)
            ->whereNotIn('status', ['RESOLVED', 'REJECTED'])
            ->exists();
    }

    private function canSyncChildWhenParentFullyReceived(SupplyRequest $child, SupplyRequest $parent): bool
    {
        // 1. If the child has a linked claim or is a claim replacement, it can NEVER be marked received unless that claim is RESOLVED
        $sourceClaimIds = $child->purchaseRequests()->pluck('source_claim_id')->filter()->all();
        if (! empty($sourceClaimIds)) {
            $hasUnresolvedClaim = \App\Models\Claim::query()
                ->whereIn('claims_id', $sourceClaimIds)
                ->whereNotIn('status', ['RESOLVED', 'REJECTED'])
                ->exists();

            if ($hasUnresolvedClaim) {
                return false;
            }
        }

        if ($this->hasUnresolvedClaims($child)) {
            return false;
        }

        // 2. Child cannot be in DRAFT or RETURNED_FOR_REVISION
        if (in_array(strtoupper((string) $child->status), ['DRAFT', 'RETURNED_FOR_REVISION', 'REJECTED', 'CANCELLED'], true)) {
            return false;
        }

        // 3. For consolidated procurements, verify ALL constituents of the parent consolidated procurement are fully received
        $rootPo = $this->resolveRootPurchaseOrderForSupplyRequest($child);
        if ($rootPo) {
            $candidates = $this->traceabilityService->getSourceSupplyRequests($rootPo);
            if ($candidates->count() > 1) {
                foreach ($candidates as $cand) {
                    if (! $this->isFullyReceived($cand)) {
                        return false;
                    }
                }

                return true;
            }
        }

        // 4. Verify the parent is indeed in the same lineage or consolidated procurement family
        $resolvedRootChild = $this->followUpService->resolveRootSupplyRequest($child);
        $resolvedRootParent = $this->followUpService->resolveRootSupplyRequest($parent);
        if ((int) $resolvedRootChild->supply_request_id !== (int) $resolvedRootParent->supply_request_id) {
            $candidates = $this->resolveConsolidatedConstituentSupplyRequests($resolvedRootChild);
            if (! $candidates->contains('supply_request_id', (int) $resolvedRootParent->supply_request_id)) {
                return false;
            }
        }

        return true;
    }

    public function resolveRootPurchaseOrderForSupplyRequest(SupplyRequest $supplyRequest): ?PurchaseOrder
    {
        $supplyRequest->loadMissing([
            'purchaseOrders',
            'purchaseRequests.purchaseOrders',
            'purchaseRequests.sourceDelivery.purchaseOrder',
            'purchaseRequests.sourceClaim.distributionRecord.delivery.purchaseOrder',
        ]);

        $currentPo = $supplyRequest->purchaseOrders->first()
            ?? $supplyRequest->purchaseRequests->flatMap->purchaseOrders->first()
            ?? $supplyRequest->purchaseRequests->firstWhere('source_delivery_id')?->sourceDelivery?->purchaseOrder
            ?? $supplyRequest->purchaseRequests->firstWhere('source_claim_id')?->sourceClaim?->distributionRecord?->delivery?->purchaseOrder;

        if (! $currentPo) {
            $rootSr = $this->followUpService->resolveRootSupplyRequest($supplyRequest);
            $currentPo = $rootSr->purchaseOrders->first()
                ?? $rootSr->purchaseRequests->flatMap->purchaseOrders->first();
        }

        if (! $currentPo) {
            return null;
        }

        $visitedPoIds = [(int) $currentPo->purchase_order_id => true];
        while (true) {
            $sourceDelivery = $currentPo->purchaseRequest?->sourceDelivery;
            if ($sourceDelivery && $sourceDelivery->purchaseOrder) {
                $nextPo = $sourceDelivery->purchaseOrder;
                $nextId = (int) $nextPo->purchase_order_id;
                if (! isset($visitedPoIds[$nextId])) {
                    $visitedPoIds[$nextId] = true;
                    $currentPo = $nextPo;
                    continue;
                }
            }

            $sourceClaim = $currentPo->purchaseRequest?->sourceClaim;
            if ($sourceClaim && $sourceClaim->distributionRecord?->delivery?->purchaseOrder) {
                $nextPo = $sourceClaim->distributionRecord->delivery->purchaseOrder;
                $nextId = (int) $nextPo->purchase_order_id;
                if (! isset($visitedPoIds[$nextId])) {
                    $visitedPoIds[$nextId] = true;
                    $currentPo = $nextPo;
                    continue;
                }
            }

            break;
        }

        return $currentPo;
    }

    public function resolveConsolidatedConstituentSupplyRequests(SupplyRequest $rootSr): \Illuminate\Support\Collection
    {
        $rootPo = $this->resolveRootPurchaseOrderForSupplyRequest($rootSr);
        if ($rootPo) {
            $candidates = $this->traceabilityService->getSourceSupplyRequests($rootPo);
            if ($candidates->count() > 1) {
                return $candidates;
            }
        }

        $allPrs = $this->traceabilityService->getPurchaseRequestsForSupplyRequest($rootSr);
        foreach ($allPrs as $pr) {
            $candidates = $this->traceabilityService->getSourceSupplyRequests($pr);
            if ($candidates->count() > 1) {
                return $candidates;
            }
        }

        return collect([$rootSr]);
    }

    public function isConsolidatedFamilyFullyReceived(\Illuminate\Support\Collection $candidates): bool
    {
        if ($candidates->isEmpty()) {
            return false;
        }

        // 1. Gather all item requirements across all candidate requests
        $familyRequestedByItem = [];
        foreach ($candidates as $cand) {
            $cand->loadMissing('items');
            foreach ($cand->items as $item) {
                $itemId = (int) $item->item_id;
                $familyRequestedByItem[$itemId] = ($familyRequestedByItem[$itemId] ?? 0.0) + (float) $item->quantity_requested;
            }
        }

        if (empty($familyRequestedByItem)) {
            return false;
        }

        // 2. Gather all supply request IDs in the family (candidates + all follow-up descendants)
        $allFamilySrIds = $candidates->pluck('supply_request_id')->all();
        foreach ($candidates as $cand) {
            $allFamilySrIds = array_merge($allFamilySrIds, $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($cand));
        }
        $allFamilySrIds = array_values(array_unique(array_filter(array_map('intval', $allFamilySrIds))));

        // 3. Gather all RECEIVED distribution records across the family
        $receivedRecords = DistributionRecord::query()
            ->with('items')
            ->whereIn('supply_request_id', $allFamilySrIds)
            ->where('status', 'RECEIVED')
            ->get();

        $familyReceivedByItem = [];
        foreach ($receivedRecords as $record) {
            foreach ($record->items as $item) {
                $itemId = (int) $item->item_id;
                $familyReceivedByItem[$itemId] = ($familyReceivedByItem[$itemId] ?? 0.0) + (float) $item->quantity_received;
            }
        }

        // 4. Verify each requested item is fully received
        foreach ($familyRequestedByItem as $itemId => $requested) {
            $received = (float) ($familyReceivedByItem[$itemId] ?? 0.0);
            if ($received + 0.0001 < $requested) {
                return false;
            }
        }

        // 5. Verify no unresolved claims exist on any distribution record of the family
        $hasUnresolvedClaim = \App\Models\Claim::query()
            ->whereIn('distribution_record_id', $receivedRecords->pluck('distribution_record_id'))
            ->whereNotIn('status', ['RESOLVED', 'REJECTED'])
            ->exists();

        if ($hasUnresolvedClaim) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int>
     */
    private function resolveFulfillmentChildSupplyRequestIds(SupplyRequest $supplyRequest): array
    {
        $childIds = $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);

        $deliveryIds = $this->followUpService->resolveDeliveryIdsForSupplyRequest($supplyRequest);
        if (! empty($deliveryIds)) {
            $deliveryChildIds = PurchaseRequest::query()
                ->whereIn('source_delivery_id', $deliveryIds)
                ->whereNotNull('supply_request_id')
                ->pluck('supply_request_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $childIds = array_values(array_unique(array_merge($childIds, $deliveryChildIds)));
        }

        return $childIds;
    }

    /**
     * @return \Illuminate\Support\Collection<int, DistributionRecord>
     */
    private function allFulfillmentDistributionRecords(SupplyRequest $supplyRequest): \Illuminate\Support\Collection
    {
        $supplyRequest->loadMissing('distributionRecords.items');
        $directRecords = $supplyRequest->distributionRecords;
        $childIds = $this->followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);

        if (empty($childIds)) {
            return $directRecords;
        }

        $childRecords = DistributionRecord::query()
            ->with('items')
            ->whereIn('supply_request_id', $childIds)
            ->get();

        return $directRecords->concat($childRecords)->values();
    }

    private function transitionSupplyRequest(SupplyRequest $supplyRequest, ?User $actor, string $remarks): void
    {
        $oldStatus = strtoupper((string) $supplyRequest->status);
        if ($oldStatus === 'RECEIVED') {
            return;
        }

        $supplyRequest->forceFill([
            'status' => 'RECEIVED',
            'review_notes' => $remarks,
        ])->save();

        $this->recordStatus(SupplyRequest::class, (int) $supplyRequest->supply_request_id, $oldStatus, 'RECEIVED', $actor, $remarks);
        $this->recordApproval(SupplyRequest::class, (int) $supplyRequest->supply_request_id, 'RECEIVED', $actor, $remarks);
    }

    public function canTransitionPurchaseRequestToCompleted(PurchaseRequest $purchaseRequest): bool
    {
        $oldStatus = strtoupper((string) $purchaseRequest->status);
        if (in_array($oldStatus, ['COMPLETED', 'CLOSED', 'CANCELLED', 'REJECTED'], true)) {
            return false;
        }

        $purchaseRequest->loadMissing([
            'purchaseOrders.items',
            'purchaseOrders.deliveries.items',
            'supplyRequest',
        ]);

        if ($purchaseRequest->purchaseOrders->isEmpty()) {
            return false;
        }

        foreach ($purchaseRequest->purchaseOrders as $po) {
            if (! $this->canTransitionPurchaseOrderToCompleted($po)) {
                return false;
            }
        }

        // If this PR was generated from a claim replacement follow-up, the claim MUST be fully RESOLVED!
        if ((int) ($purchaseRequest->source_claim_id ?? 0) > 0 || (string) $purchaseRequest->source_type === 'CLAIM_REPLACEMENT_FOLLOW_UP') {
            $claim = $purchaseRequest->sourceClaim ?? \App\Models\Claim::find($purchaseRequest->source_claim_id);
            if ($claim instanceof \App\Models\Claim && (string) $claim->status !== 'RESOLVED') {
                return false;
            }
        }

        $traceability = app(\App\Services\Procurement\ConsolidationTraceabilityService::class);
        $sourceRequests = $traceability->getSourceSupplyRequests($purchaseRequest);

        if ($sourceRequests->isNotEmpty()) {
            foreach ($sourceRequests as $sourceSr) {
                if (! in_array(strtoupper((string) $sourceSr->status), ['RECEIVED', 'COMPLETED'], true)) {
                    if (! $this->isFullyReceived($sourceSr)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function canTransitionPurchaseOrderToCompleted(PurchaseOrder $purchaseOrder): bool
    {
        $status = strtoupper((string) $purchaseOrder->status);
        if (in_array($status, ['CANCELLED', 'REJECTED'], true)) {
            return false;
        }

        $purchaseOrder->loadMissing(['items', 'deliveries.items']);

        if ($purchaseOrder->deliveries->isEmpty()) {
            return false;
        }

        $hasPendingDeliveries = $purchaseOrder->deliveries
            ->whereNotIn('receipt_status', ['VERIFIED', 'COMPLETED', 'CANCELLED'])
            ->isNotEmpty();

        if ($hasPendingDeliveries) {
            return false;
        }

        $verifiedDeliveries = $purchaseOrder->deliveries->whereIn('receipt_status', ['VERIFIED', 'COMPLETED']);
        foreach ($purchaseOrder->items as $poItem) {
            $orderedQty = (float) $poItem->quantity_ordered;
            if ($orderedQty <= 0) {
                continue;
            }

            $receivedQty = (float) $verifiedDeliveries
                ->flatMap(fn (Delivery $del) => $del->items)
                ->where('purchase_order_item_id', (int) $poItem->purchase_order_item_id)
                ->sum('received_quantity');

            if ($receivedQty + 0.0001 < $orderedQty) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  EloquentCollection<int, PurchaseRequest>  $purchaseRequests
     */
    private function transitionPurchaseRequests(EloquentCollection $purchaseRequests, ?User $actor, string $remarks): void
    {
        foreach ($purchaseRequests as $purchaseRequest) {
            if (! $this->canTransitionPurchaseRequestToCompleted($purchaseRequest)) {
                continue;
            }

            $oldStatus = strtoupper((string) $purchaseRequest->status);
            $purchaseRequest->forceFill([
                'status' => 'COMPLETED',
                'remarks' => trim((string) $purchaseRequest->remarks) !== ''
                    ? $purchaseRequest->remarks
                    : $remarks,
            ])->save();

            $this->recordStatus(PurchaseRequest::class, (int) $purchaseRequest->purchase_request_id, $oldStatus, 'COMPLETED', $actor, $remarks);
            $this->recordApproval(PurchaseRequest::class, (int) $purchaseRequest->purchase_request_id, 'COMPLETED', $actor, $remarks);
        }
    }

    /**
     * @param  EloquentCollection<int, PurchaseOrder>  $purchaseOrders
     */
    private function transitionPurchaseOrders(EloquentCollection $purchaseOrders, ?User $actor, string $remarks): void
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            if (! $this->canTransitionPurchaseOrderToCompleted($purchaseOrder)) {
                continue;
            }

            $oldStatus = strtoupper((string) $purchaseOrder->status);
            if (in_array($oldStatus, ['COMPLETED', 'CLOSED'], true)) {
                continue;
            }

            $purchaseOrder->forceFill([
                'status' => 'COMPLETED',
                'remarks' => trim((string) $purchaseOrder->remarks) !== ''
                    ? $purchaseOrder->remarks
                    : $remarks,
            ])->save();

            $this->recordStatus(PurchaseOrder::class, (int) $purchaseOrder->purchase_order_id, $oldStatus, 'COMPLETED', $actor, $remarks);
            $this->recordApproval(PurchaseOrder::class, (int) $purchaseOrder->purchase_order_id, 'COMPLETED', $actor, $remarks);
        }
    }

    private function recordStatus(string $referenceType, int $referenceId, ?string $oldStatus, string $newStatus, ?User $actor, string $remarks): void
    {
        StatusHistory::query()->create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor?->id,
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);
    }

    private function recordApproval(string $referenceType, int $referenceId, string $status, ?User $actor, string $remarks): void
    {
        ApprovalHistory::query()->create([
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'approved_by' => $actor?->id,
            'status' => $status,
            'remarks' => $remarks,
            'approved_at' => now(),
        ]);
    }
}
