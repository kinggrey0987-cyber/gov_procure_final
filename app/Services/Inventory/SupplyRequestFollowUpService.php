<?php

namespace App\Services\Inventory;

use App\Models\Delivery;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;

class SupplyRequestFollowUpService
{
    /**
     * Resolve parent and children follow-up links for a single supply request.
     *
     * @return array{is_follow_up: bool, type: string, label: string, parent: ?array{supply_request_id: int, request_code: string}, children: array<int, array{supply_request_id: int, request_code: string, status: string, reason: string}>}
     */
    public function resolveContext(SupplyRequest $supplyRequest): array
    {
        $supplyRequest->loadMissing([
            'purchaseRequests.sourceDelivery.purchaseOrder.supplyRequest',
            'purchaseRequests.sourceClaim.supplyRequest',
            'purchaseRequests.sourceClaim.distributionRecord.supplyRequest',
        ]);

        // 1. Check if this request is a follow-up child
        $followUpPr = $supplyRequest->purchaseRequests
            ->first(fn (PurchaseRequest $pr): bool => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($pr->source_delivery_id ?? 0) > 0
                || (int) ($pr->source_claim_id ?? 0) > 0
                || (int) ($pr->source_supply_request_id ?? 0) > 0);

        if ($followUpPr) {
            $parentSr = null;
            $type = 'DISCREPANCY_REPLACEMENT';
            $label = 'Delivery Discrepancy Replacement';

            if ((int) $followUpPr->source_delivery_id > 0) {
                $type = 'DISCREPANCY_REPLACEMENT';
                $label = 'Delivery Discrepancy Replacement';
                $parentSr = $this->resolveParentSupplyRequestForDeliveryFollowUp($followUpPr);
            } elseif ((int) ($followUpPr->source_supply_request_id ?? 0) > 0) {
                $type = 'REQUISITION_FOLLOW_UP';
                $label = 'Follow-up Requisition Batch';
                $parentSr = $followUpPr->sourceSupplyRequest
                    ?? SupplyRequest::query()->find($followUpPr->source_supply_request_id);
            } elseif ((int) $followUpPr->source_claim_id > 0) {
                $type = 'CLAIM_REPLACEMENT';
                $label = 'Warranty / Return Claim Replacement';
                $claim = $followUpPr->sourceClaim;
                $parentSr = $claim?->supplyRequest
                    ?? $claim?->distributionRecord?->supplyRequest;
            }

            if ($parentSr instanceof SupplyRequest) {
                return [
                    'is_follow_up' => true,
                    'type' => $type,
                    'label' => $label,
                    'parent' => [
                        'supply_request_id' => (int) $parentSr->supply_request_id,
                        'request_code' => (string) $parentSr->request_code,
                    ],
                    'children' => [],
                ];
            }
        }

        // 2. Check if this request has follow-up children
        $children = $this->resolveChildrenForParent((int) $supplyRequest->supply_request_id);

        if (! empty($children)) {
            return [
                'is_follow_up' => false,
                'type' => 'PARENT_REQUISITION',
                'label' => 'Parent Requisition with Follow-up Batches',
                'parent' => null,
                'children' => $children,
            ];
        }

        return [
            'is_follow_up' => false,
            'type' => 'STANDARD',
            'label' => 'Standard Requisition',
            'parent' => null,
            'children' => [],
        ];
    }

    /**
     * Resolve batch context for a collection of supply requests efficiently.
     *
     * @param  iterable<SupplyRequest>  $supplyRequests
     * @return array<int, array<string, mixed>>
     */
    public function resolveBatchContext(iterable $supplyRequests): array
    {
        $parentSrIds = [];
        foreach ($supplyRequests as $sr) {
            $parentSrIds[] = (int) $sr->supply_request_id;
        }

        $childrenByParentId = $this->resolveChildrenForParentIds($parentSrIds);

        $results = [];
        foreach ($supplyRequests as $sr) {
            $srId = (int) $sr->supply_request_id;

            // Check if child
            $followUpPr = $sr->purchaseRequests
                ->first(fn (PurchaseRequest $pr): bool => in_array((string) $pr->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                    || (int) ($pr->source_delivery_id ?? 0) > 0
                    || (int) ($pr->source_claim_id ?? 0) > 0
                    || (int) ($pr->source_supply_request_id ?? 0) > 0);

            if ($followUpPr) {
                $parentSr = null;
                $type = 'DISCREPANCY_REPLACEMENT';
                $label = 'Delivery Discrepancy Replacement';

                if ((int) $followUpPr->source_delivery_id > 0) {
                    $type = 'DISCREPANCY_REPLACEMENT';
                    $label = 'Delivery Discrepancy Replacement';
                    $parentSr = $this->resolveParentSupplyRequestForDeliveryFollowUp($followUpPr);
                } elseif ((int) ($followUpPr->source_supply_request_id ?? 0) > 0) {
                    $type = 'REQUISITION_FOLLOW_UP';
                    $label = 'Follow-up Requisition Batch';
                    $parentSr = $followUpPr->sourceSupplyRequest
                        ?? SupplyRequest::query()->find($followUpPr->source_supply_request_id);
                } elseif ((int) $followUpPr->source_claim_id > 0) {
                    $type = 'CLAIM_REPLACEMENT';
                    $label = 'Warranty / Return Claim Replacement';
                    $claim = $followUpPr->sourceClaim;
                    $parentSr = $claim?->supplyRequest
                        ?? $claim?->distributionRecord?->supplyRequest;
                }

                if ($parentSr instanceof SupplyRequest) {
                    $results[$srId] = [
                        'is_follow_up' => true,
                        'type' => $type,
                        'label' => $label,
                        'parent' => [
                            'supply_request_id' => (int) $parentSr->supply_request_id,
                            'request_code' => (string) $parentSr->request_code,
                        ],
                        'children' => [],
                    ];
                    continue;
                }
            }

            // Check if parent
            if (isset($childrenByParentId[$srId]) && ! empty($childrenByParentId[$srId])) {
                $results[$srId] = [
                    'is_follow_up' => false,
                    'type' => 'PARENT_REQUISITION',
                    'label' => 'Parent Requisition with Follow-up Batches',
                    'parent' => null,
                    'children' => $childrenByParentId[$srId],
                ];
                continue;
            }

            $results[$srId] = [
                'is_follow_up' => false,
                'type' => 'STANDARD',
                'label' => 'Standard Requisition',
                'parent' => null,
                'children' => [],
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array{supply_request_id: int, request_code: string, status: string, reason: string}>
     */
    private function resolveChildrenForParent(int $parentId): array
    {
        $batch = $this->resolveChildrenForParentIds([$parentId]);

        return $batch[$parentId] ?? [];
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return array<int, array<int, array{supply_request_id: int, request_code: string, status: string, reason: string}>>
     */
    private function resolveChildrenForParentIds(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $results = [];

        // 1. Through delivery discrepancies and follow-up requisitions
        $deliveryPrs = PurchaseRequest::query()
            ->where(function ($q) {
                $q->whereIn('source_type', ['PARTIAL_DELIVERY_FOLLOW_UP', 'REQUISITION_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'])
                    ->orWhereNotNull('source_supply_request_id');
            })
            ->where(function ($query) use ($parentIds) {
                $query->whereIn('source_supply_request_id', $parentIds)
                    ->orWhereNotNull('source_delivery_id')
                    ->orWhereHas('sourceDelivery.purchaseOrder', fn ($q) => $q->whereIn('supply_request_id', $parentIds))
                    ->orWhereHas('sourceDelivery.purchaseOrder.purchaseRequest', fn ($q) => $q->whereIn('supply_request_id', $parentIds));
            })
            ->with([
                'supplyRequest:supply_request_id,request_code,status',
                'sourceSupplyRequest:supply_request_id,request_code,status',
                'sourceDelivery.purchaseOrder.supplyRequest',
                'sourceDelivery.purchaseOrder.purchaseRequest.supplyRequest',
            ])
            ->get();

        foreach ($deliveryPrs as $pr) {
            $parent = ((int) ($pr->source_delivery_id ?? 0) > 0)
                ? $this->resolveParentSupplyRequestForDeliveryFollowUp($pr)
                : (((int) ($pr->source_supply_request_id ?? 0) > 0)
                    ? ($pr->sourceSupplyRequest ?? SupplyRequest::query()->find($pr->source_supply_request_id))
                    : null);
            $pId = (int) ($parent?->supply_request_id ?? 0);
            if (in_array($pId, $parentIds, true) && $pr->supplyRequest) {
                $results[$pId][] = [
                    'supply_request_id' => (int) $pr->supplyRequest->supply_request_id,
                    'request_code' => (string) $pr->supplyRequest->request_code,
                    'status' => (string) $pr->supplyRequest->status,
                    'reason' => ((int) ($pr->source_supply_request_id ?? 0) > 0) ? 'Follow-up Batch' : 'Discrepancy Replacement',
                ];
            }
        }

        // 2. Through claims
        $claimPrs = PurchaseRequest::query()
            ->where('source_type', 'CLAIM_REPLACEMENT_FOLLOW_UP')
            ->whereHas('sourceClaim.distributionRecord', fn ($q) => $q->whereIn('supply_request_id', $parentIds))
            ->with([
                'supplyRequest:supply_request_id,request_code,status',
                'sourceClaim:claims_id,distribution_record_id',
                'sourceClaim.distributionRecord:distribution_record_id,supply_request_id',
            ])
            ->get();

        foreach ($claimPrs as $pr) {
            $pId = (int) ($pr->sourceClaim?->distributionRecord?->supply_request_id ?? 0);
            if ($pId > 0 && $pr->supplyRequest) {
                $results[$pId][] = [
                    'supply_request_id' => (int) $pr->supplyRequest->supply_request_id,
                    'request_code' => (string) $pr->supplyRequest->request_code,
                    'status' => (string) $pr->supplyRequest->status,
                    'reason' => 'Claim Replacement',
                ];
            }
        }

        return $results;
    }

    /**
     * Resolve the parent supply request for a delivery discrepancy follow-up PR.
     */
    public function resolveParentSupplyRequestForDeliveryFollowUp(PurchaseRequest $followUpPr): ?SupplyRequest
    {
        // 1. Direct parent if stored
        if ((int) ($followUpPr->source_supply_request_id ?? 0) > 0) {
            $parent = $followUpPr->sourceSupplyRequest
                ?? SupplyRequest::query()->find($followUpPr->source_supply_request_id);
            if ($parent instanceof SupplyRequest) {
                // If this follow-up PR originated from a consolidated delivery, check if this stored parent is already 100% fulfilled
                $delivery = $followUpPr->sourceDelivery
                    ?? ((int) $followUpPr->source_delivery_id > 0 ? Delivery::query()->find($followUpPr->source_delivery_id) : null);
                $po = $delivery?->purchaseOrder
                    ?? ($delivery?->purchase_order_id ? PurchaseOrder::query()->find($delivery->purchase_order_id) : null);
                if ($delivery && $po) {
                    $traceability = app(\App\Services\Procurement\ConsolidationTraceabilityService::class);
                    $candidateRequests = $traceability->getSourceSupplyRequests($po);
                    if ($candidateRequests->count() > 1 && $this->isSupplyRequestFullyFulfilled($parent)) {
                        $targetParent = $traceability->resolveCandidateSupplyRequestWithDeficit($delivery, $candidateRequests);
                        if ($targetParent instanceof SupplyRequest && (int) $targetParent->supply_request_id !== (int) $parent->supply_request_id) {
                            $followUpPr->forceFill(['source_supply_request_id' => (int) $targetParent->supply_request_id])->save();
                            if ($followUpPr->supplyRequest && (int) $followUpPr->supplyRequest->office_id !== (int) $targetParent->office_id) {
                                $followUpPr->supplyRequest->forceFill(['office_id' => (int) $targetParent->office_id])->save();
                            }

                            return $targetParent;
                        }
                    }
                }

                return $parent;
            }
        }

        $delivery = $followUpPr->sourceDelivery;
        if (! $delivery) {
            return null;
        }

        $po = $delivery->purchaseOrder;
        if (! $po) {
            return null;
        }

        $traceability = app(\App\Services\Procurement\ConsolidationTraceabilityService::class);
        $candidateRequests = $traceability->getSourceSupplyRequests($po);
        if ($candidateRequests->isEmpty() && $po->purchaseRequest) {
            $candidateRequests = $traceability->getSourceSupplyRequests($po->purchaseRequest);
        }

        if ($candidateRequests->count() > 1) {
            $targetParent = $traceability->resolveCandidateSupplyRequestWithDeficit($delivery, $candidateRequests);

            if ($targetParent instanceof SupplyRequest) {
                return $targetParent;
            }
        }

        return $candidateRequests->first()
            ?? $po->supplyRequest
            ?? $po->purchaseRequest?->supplyRequest;
    }

    /**
     * Recursively resolve all follow-up child supply request IDs for a given supply request.
     *
     * @return array<int, int>
     */
    public function resolveAllFollowUpChildSupplyRequestIds(SupplyRequest $supplyRequest): array
    {
        $visited = [(int) $supplyRequest->supply_request_id => true];
        $allChildIds = [];
        $currentParentIds = [(int) $supplyRequest->supply_request_id];

        while (! empty($currentParentIds)) {
            $childrenMap = $this->resolveChildrenForParentIds($currentParentIds);
            $nextParentIds = [];

            foreach ($childrenMap as $children) {
                foreach ($children as $child) {
                    $childId = (int) ($child['supply_request_id'] ?? 0);
                    if ($childId > 0 && ! isset($visited[$childId])) {
                        $visited[$childId] = true;
                        $allChildIds[] = $childId;
                        $nextParentIds[] = $childId;
                    }
                }
            }

            $currentParentIds = $nextParentIds;
        }

        return $allChildIds;
    }

    /**
     * Resolve the parent supply request if the given request is a follow-up.
     */
    public function resolveParentSupplyRequest(SupplyRequest $supplyRequest): ?SupplyRequest
    {
        $context = $this->resolveContext($supplyRequest);
        if ($context['is_follow_up'] && ! empty($context['parent']['supply_request_id'])) {
            return SupplyRequest::query()->find((int) $context['parent']['supply_request_id']);
        }

        return null;
    }

    /**
     * Resolve the root supply request by walking up the parent follow-up chain.
     */
    public function resolveRootSupplyRequest(SupplyRequest $supplyRequest): SupplyRequest
    {
        $current = $supplyRequest;
        $visited = [(int) $current->supply_request_id => true];

        while ($parent = $this->resolveParentSupplyRequest($current)) {
            $parentId = (int) $parent->supply_request_id;
            if (isset($visited[$parentId])) {
                break;
            }
            $visited[$parentId] = true;
            $current = $parent;
        }

        return $current;
    }

    /**
     * Resolve all supply request IDs in the lineage (root parent + all follow-up descendants + self).
     *
     * @return array<int>
     */
    public function resolveLineageSupplyRequestIds(SupplyRequest $supplyRequest): array
    {
        $current = $this->resolveRootSupplyRequest($supplyRequest);

        $allIds = [(int) $current->supply_request_id];
        $allIds = array_merge($allIds, $this->resolveAllFollowUpChildSupplyRequestIds($current));
        $allIds[] = (int) $supplyRequest->supply_request_id;

        return array_values(array_unique(array_filter(array_map('intval', $allIds))));
    }

    /**
     * Resolve all delivery IDs associated with a supply request or its lineage.
     *
     * @return array<int>
     */
    public function resolveDeliveryIdsForSupplyRequest(SupplyRequest $supplyRequest): array
    {
        $srIds = $this->resolveLineageSupplyRequestIds($supplyRequest);
        $requestCode = trim((string) $supplyRequest->request_code);

        $traceabilityPrIds = app(\App\Services\Procurement\ConsolidationTraceabilityService::class)
            ->getPurchaseRequestsForSupplyRequest($supplyRequest)
            ->pluck('purchase_request_id')
            ->filter()
            ->all();

        return Delivery::query()
            ->whereHas('purchaseOrder', function ($query) use ($srIds, $requestCode, $traceabilityPrIds) {
                $query->whereIn('supply_request_id', $srIds)
                    ->orWhereHas('purchaseRequest', function ($prQ) use ($srIds, $requestCode, $traceabilityPrIds) {
                        $prQ->whereIn('supply_request_id', $srIds);
                        if (! empty($traceabilityPrIds)) {
                            $prQ->orWhereIn('purchase_request_id', $traceabilityPrIds);
                        }
                        if ($requestCode !== '') {
                            $prQ->orWhere('remarks', 'like', '%' . $requestCode . '%');
                        }
                    });
            })
            ->pluck('delivery_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isFollowUpSupplyRequest(SupplyRequest $supplyRequest): bool
    {
        $context = $this->resolveContext($supplyRequest);

        return (bool) ($context['is_follow_up'] ?? false);
    }

    public function isSupplyRequestFullyFulfilled(SupplyRequest $supplyRequest): bool
    {
        $supplyRequest->loadMissing(['items', 'distributionRecords.items']);
        if ($supplyRequest->items->isEmpty()) {
            return false;
        }

        $releasedRecords = \App\Models\DistributionRecord::query()
            ->with('items')
            ->where('supply_request_id', $supplyRequest->supply_request_id)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->get();

        $releasedByItem = [];
        foreach ($releasedRecords as $record) {
            foreach ($record->items as $item) {
                $itemId = (int) $item->item_id;
                $damaged = (float) ($item->quantity_damaged ?? 0.0);
                $effective = max((float) $item->quantity_released - $damaged, 0.0);
                $releasedByItem[$itemId] = ($releasedByItem[$itemId] ?? 0.0) + $effective;
            }
        }

        foreach ($supplyRequest->items as $item) {
            $itemId = (int) $item->item_id;
            $requested = (float) $item->quantity_requested;
            $released = (float) ($releasedByItem[$itemId] ?? 0.0);
            if ($released + 0.0001 < $requested) {
                return false;
            }
        }

        return true;
    }
}

