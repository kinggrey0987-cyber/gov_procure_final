<?php

namespace App\Services\Procurement;

use App\Models\Delivery;
use App\Models\DistributionItem;
use App\Models\DistributionRecord;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;

class ConsolidationTraceabilityService
{
    /** @var \Illuminate\Support\Collection<int, PurchaseRequest>|null */
    private ?\Illuminate\Support\Collection $prsWithConsolidatedRemarksIndex = null;

    /** @var array<string, array<int>>|null */
    private ?array $statusHistorySrLinksIndex = null;

    /** @var array<int, array<string>>|null */
    private ?array $statusHistoryRemarksBySrId = null;

    /**
     * Clear memoized cache for testing or batch operations.
     */
    public function clearCache(): void
    {
        $this->supplyRequestPrsCache = [];
        $this->prsWithConsolidatedRemarksIndex = null;
        $this->statusHistorySrLinksIndex = null;
        $this->statusHistoryRemarksBySrId = null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, PurchaseRequest>
     */
    private function getPrsWithConsolidatedRemarks(): \Illuminate\Support\Collection
    {
        if ($this->prsWithConsolidatedRemarksIndex === null) {
            $this->prsWithConsolidatedRemarksIndex = PurchaseRequest::query()
                ->where(function ($q) {
                    $q->where('remarks', 'like', '%PR-%')
                        ->orWhere('remarks', 'like', '%SR-%')
                        ->orWhere('remarks', 'like', '%consolidat%');
                })
                ->with([
                    'supplyRequest.office',
                    'supplyRequest.requester',
                    'supplyRequest.items.item',
                    'supplyRequest.items.ppmpItem',
                    'requester.role',
                    'purchaseOrders.supplier',
                    'purchaseOrders.creator.role',
                    'purchaseOrders.approver.role',
                ])
                ->get();
        }

        return $this->prsWithConsolidatedRemarksIndex;
    }

    /**
     * @return array{sr_links: array<string, array<int>>, remarks_by_sr: array<int, array<string>>}
     */
    private function getStatusHistoryConsolidationIndexes(): array
    {
        if ($this->statusHistorySrLinksIndex === null || $this->statusHistoryRemarksBySrId === null) {
            $rows = StatusHistory::query()
                ->where('reference_type', SupplyRequest::class)
                ->where(function ($q) {
                    $q->where('remarks', 'like', '%PR-%')
                        ->orWhere('remarks', 'like', '%consolidat%');
                })
                ->get(['reference_id', 'remarks']);

            $srLinks = [];
            $remarksBySr = [];
            foreach ($rows as $h) {
                $srId = (int) $h->reference_id;
                $rem = (string) $h->remarks;
                $remarksBySr[$srId][] = $rem;
                preg_match_all('/PR-[A-Z0-9-]+/', $rem, $matches);
                if (! empty($matches[0])) {
                    foreach ($matches[0] as $token) {
                        $srLinks[$token][] = $srId;
                    }
                }
            }

            $this->statusHistorySrLinksIndex = $srLinks;
            $this->statusHistoryRemarksBySrId = $remarksBySr;
        }

        return [
            'sr_links' => $this->statusHistorySrLinksIndex,
            'remarks_by_sr' => $this->statusHistoryRemarksBySrId,
        ];
    }

    /**
     * Get all source supply requests associated with a Purchase Request or Purchase Order.
     *
     * @return \Illuminate\Support\Collection<int, SupplyRequest>
     */
    public function getSourceSupplyRequests(PurchaseRequest|PurchaseOrder $context): \Illuminate\Support\Collection
    {
        $purchaseRequest = $context instanceof PurchaseRequest ? $context : $context->purchaseRequest;

        $allSupplyRequests = collect();

        if ($context instanceof PurchaseOrder && $context->supplyRequest instanceof SupplyRequest) {
            $allSupplyRequests->push($context->supplyRequest);
        }

        if ($purchaseRequest instanceof PurchaseRequest) {
            $purchaseRequest->loadMissing([
                'supplyRequest.office',
                'supplyRequest.requester',
                'supplyRequest.items.item',
                'supplyRequest.items.ppmpItem',
            ]);

            if ($purchaseRequest->supplyRequest instanceof SupplyRequest) {
                $allSupplyRequests->push($purchaseRequest->supplyRequest);
            }

            $isFollowUp = in_array((string) $purchaseRequest->source_type, ['PARTIAL_DELIVERY_FOLLOW_UP', 'CLAIM_REPLACEMENT_FOLLOW_UP'], true)
                || (int) ($purchaseRequest->source_delivery_id ?? 0) > 0
                || (int) ($purchaseRequest->source_claim_id ?? 0) > 0
                || (int) ($purchaseRequest->source_supply_request_id ?? 0) > 0;

            if (! $isFollowUp) {
                $remarks = (string) $purchaseRequest->remarks;
                $prIdPad = sprintf('%06d', (int) $purchaseRequest->purchase_request_id);

                $consolidatedPrs = $this->getPrsWithConsolidatedRemarks();
                $linkedPrs = $consolidatedPrs
                    ->where('purchase_request_id', '!=', $purchaseRequest->purchase_request_id)
                    ->filter(fn ($pr) => str_contains((string) $pr->remarks, "PR-{$prIdPad}"));

                foreach ($linkedPrs as $lPr) {
                    if ($lPr->supplyRequest instanceof SupplyRequest) {
                        $allSupplyRequests->push($lPr->supplyRequest);
                    }
                }

                preg_match_all('/SR-[A-Z0-9-]+/', $remarks, $matches);
                if (! empty($matches[0])) {
                    $extraRequests = SupplyRequest::query()
                        ->whereIn('request_code', $matches[0])
                        ->with([
                            'office',
                            'requester',
                            'items.item',
                            'items.ppmpItem',
                        ])
                        ->get();
                    $allSupplyRequests = $allSupplyRequests->merge($extraRequests);
                }

                $indexes = $this->getStatusHistoryConsolidationIndexes();
                $token1 = "PR-{$prIdPad}";
                $prNumber = (string) $purchaseRequest->pr_number;

                $historySrIds = array_values(array_unique(array_merge(
                    $indexes['sr_links'][$token1] ?? [],
                    $prNumber !== '' ? ($indexes['sr_links'][$prNumber] ?? []) : []
                )));

                if (! empty($historySrIds)) {
                    $historyRequests = SupplyRequest::query()
                        ->whereIn('supply_request_id', $historySrIds)
                        ->with([
                            'office',
                            'requester',
                            'items.item',
                            'items.ppmpItem',
                        ])
                        ->get();
                    $allSupplyRequests = $allSupplyRequests->merge($historyRequests);
                }
            }
        }

        return $allSupplyRequests->unique('supply_request_id')->values();
    }

    /**
     * Build full multi-office and signatory traceability for a Purchase Request.
     *
     * @return array<string, mixed>
     */
    public function resolveForPurchaseRequest(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->loadMissing([
            'office',
            'requester.role',
            'supplyRequest.office',
            'supplyRequest.requester',
            'supplyRequest.items.item',
            'items.item',
        ]);

        $allSupplyRequests = $this->getSourceSupplyRequests($purchaseRequest);

        // A PR is only considered consolidated if it merges 2 or more distinct supply requests
        $isConsolidated = $allSupplyRequests->count() >= 2;

        // Build GSO Forwarder attribution
        $gsoForwarder = $this->resolveGsoForwarder($purchaseRequest, $allSupplyRequests);

        // Build source supply requests breakdown
        $sourceRequests = $allSupplyRequests->map(function (SupplyRequest $sr): array {
            return [
                'supply_request_id' => (int) $sr->supply_request_id,
                'request_code' => (string) $sr->request_code,
                'priority_level' => strtoupper((string) ($sr->priority_level ?? 'NORMAL')),
                'office_name' => (string) ($sr->office?->name ?? 'Unknown Office'),
                'office_code' => (string) ($sr->office?->code ?? 'OFFICE'),
                'requester_name' => (string) ($sr->requester?->name ?? 'Office Requisitioner'),
                'request_date' => optional($sr->request_date ?? $sr->created_at)->format('M d, Y'),
                'items' => $sr->items->map(fn (SupplyRequestItem $sri): array => [
                    'item_name' => (string) ($sri->item?->item_name ?? $sri->item_name_snapshot ?? 'Item'),
                    'quantity' => (float) $sri->quantity_requested,
                    'unit' => (string) ($sri->item?->default_unit ?? 'box'),
                ])->values()->all(),
            ];
        })->values()->all();

        // Build unique requisitioning signatories (deduplicated by office and requester)
        $seenSignatories = [];
        foreach ($sourceRequests as $sr) {
            $key = $sr['office_code'] . '::' . $sr['requester_name'];
            if (! isset($seenSignatories[$key])) {
                $seenSignatories[$key] = [
                    'office_code' => $sr['office_code'],
                    'office_name' => $sr['office_name'],
                    'requester_name' => $sr['requester_name'],
                    'request_date' => $sr['request_date'],
                    'request_codes' => [$sr['request_code']],
                ];
            } else {
                $seenSignatories[$key]['request_codes'][] = $sr['request_code'];
            }
        }

        if (empty($seenSignatories)) {
            $officeName = (string) ($purchaseRequest->office?->name ?? $purchaseRequest->supplyRequest?->office?->name ?? 'Requesting Office');
            $officeCode = (string) ($purchaseRequest->office?->code ?? $purchaseRequest->supplyRequest?->office?->code ?? 'OFFICE');
            $requesterName = (string) ($purchaseRequest->supplyRequest?->requester?->name ?? $purchaseRequest->requester?->name ?? 'Office Requisitioner');
            $reqDate = optional($purchaseRequest->supplyRequest?->request_date ?? $purchaseRequest->created_at)->format('M d, Y');

            $seenSignatories[] = [
                'office_code' => $officeCode,
                'office_name' => $officeName,
                'requester_name' => $requesterName,
                'request_date' => $reqDate,
                'request_codes' => array_filter([(string) ($purchaseRequest->supplyRequest?->request_code ?? '')]),
            ];
        }

        $requisitioningSignatories = array_values($seenSignatories);

        // Build office allocations per item
        $officeAllocations = $this->buildOfficeAllocations($allSupplyRequests);

        // Procurement Officer attribution (if PR is in processing)
        $procurementOfficer = null;
        if ($purchaseRequest->purchase_request_id) {
            $procHistory = StatusHistory::query()
                ->where('reference_type', PurchaseRequest::class)
                ->where('reference_id', (int) $purchaseRequest->purchase_request_id)
                ->whereIn('new_status', ['PROCESSING', 'APPROVED'])
                ->whereHas('changedBy.role', fn ($q) => $q->where('code', 'PROC_OFFICER'))
                ->with('changedBy.role')
                ->latest('id')
                ->first();

            if ($procHistory && $procHistory->changedBy instanceof User) {
                $procurementOfficer = [
                    'name' => (string) $procHistory->changedBy->name,
                    'role' => 'BAC Procurement Secretariat',
                    'processed_at' => optional($procHistory->changed_at ?? $procHistory->created_at)->format('M d, Y h:i A'),
                ];
            }
        }

        $isUrgent = $allSupplyRequests->contains(fn ($sr) => strtoupper((string) ($sr->priority_level ?? '')) === 'URGENT')
            || strtoupper((string) ($purchaseRequest->supplyRequest?->priority_level ?? '')) === 'URGENT';

        return [
            'is_consolidated' => $isConsolidated,
            'is_urgent' => $isUrgent,
            'gso_forwarder' => $gsoForwarder,
            'procurement_officer' => $procurementOfficer,
            'source_supply_requests' => $sourceRequests,
            'requisitioning_signatories' => $requisitioningSignatories,
            'office_allocations' => $officeAllocations,
        ];
    }

    /**
     * Resolve the actual GSO officer who forwarded / queued this request for procurement.
     *
     * @param  \Illuminate\Support\Collection<int, SupplyRequest>  $allSupplyRequests
     * @return array{name: string, role: string, forwarded_at: string}
     */
    private function resolveGsoForwarder(PurchaseRequest $purchaseRequest, \Illuminate\Support\Collection $allSupplyRequests): array
    {
        $gsoUser = null;
        $forwardedAt = null;

        // 1. Check if the purchase request requester is explicitly a GSO officer
        if ($purchaseRequest->requester instanceof User && (string) ($purchaseRequest->requester->role?->code ?? '') === 'GSO_OFFICER') {
            $gsoUser = $purchaseRequest->requester;
            $forwardedAt = optional($purchaseRequest->created_at)->format('M d, Y h:i A');
        }

        // 2. Check StatusHistory on the purchase request for a transition recorded by a GSO officer
        if (! $gsoUser && $purchaseRequest->purchase_request_id) {
            $gsoHistory = StatusHistory::query()
                ->where('reference_type', PurchaseRequest::class)
                ->where('reference_id', (int) $purchaseRequest->purchase_request_id)
                ->whereHas('changedBy.role', fn ($q) => $q->where('code', 'GSO_OFFICER'))
                ->with('changedBy.role')
                ->latest('id')
                ->first();

            if ($gsoHistory && $gsoHistory->changedBy instanceof User) {
                $gsoUser = $gsoHistory->changedBy;
                $forwardedAt = optional($gsoHistory->changed_at ?? $gsoHistory->created_at)->format('M d, Y h:i A');
            }
        }

        // 3. Check StatusHistory on linked supply requests for a transition recorded by a GSO officer
        if (! $gsoUser && $allSupplyRequests->isNotEmpty()) {
            $srIds = $allSupplyRequests->pluck('supply_request_id')->filter()->all();
            $srHistory = StatusHistory::query()
                ->where('reference_type', SupplyRequest::class)
                ->whereIn('reference_id', $srIds)
                ->where('new_status', 'FOR_PROCUREMENT')
                ->whereHas('changedBy.role', fn ($q) => $q->where('code', 'GSO_OFFICER'))
                ->with('changedBy.role')
                ->latest('id')
                ->first();

            if ($srHistory && $srHistory->changedBy instanceof User) {
                $gsoUser = $srHistory->changedBy;
                $forwardedAt = optional($srHistory->changed_at ?? $srHistory->created_at)->format('M d, Y h:i A');
            }
        }

        // 4. Fallback to active GSO Officer in the system
        if (! $gsoUser) {
            $gsoUser = User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'GSO_OFFICER'))
                ->with('role')
                ->first();
        }

        if (! $forwardedAt) {
            $forwardedAt = optional($purchaseRequest->created_at)->format('M d, Y h:i A');
        }

        return [
            'name' => (string) ($gsoUser?->name ?? 'GSO Property Officer'),
            'role' => 'GSO Property Custodian',
            'forwarded_at' => $forwardedAt ?: 'Validated & Forwarded',
        ];
    }

    /**
     * Build full multi-office and signatory traceability for a Purchase Order.
     *
     * @return array<string, mixed>
     */
    public function resolveForPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing([
            'creator.role',
            'approver.role',
            'purchaseRequest.office',
            'purchaseRequest.requester.role',
            'purchaseRequest.supplyRequest.office',
            'purchaseRequest.supplyRequest.requester',
            'supplyRequest.office',
            'supplyRequest.requester',
        ]);

        $prTraceability = [];
        if ($purchaseOrder->purchaseRequest instanceof PurchaseRequest) {
            $prTraceability = $this->resolveForPurchaseRequest($purchaseOrder->purchaseRequest);
        }

        // Procurement Officer attribution
        $procurementOfficer = null;
        if ($purchaseOrder->creator instanceof User) {
            $procurementOfficer = [
                'name' => (string) $purchaseOrder->creator->name,
                'role' => 'BAC Procurement Secretariat',
                'processed_at' => optional($purchaseOrder->created_at)->format('M d, Y h:i A'),
            ];
        }

        // Approver / Placer attribution
        $approverData = null;
        $executiveApprover = null;
        if ($purchaseOrder->approver instanceof User) {
            $roleCode = (string) ($purchaseOrder->approver->role?->code ?? '');
            $isExec = $roleCode === 'EXEC_OFFICER';
            $approverData = [
                'name' => (string) $purchaseOrder->approver->name,
                'role' => $isExec ? 'Executive Officer / Municipal Mayor' : 'Procurement Unit / Authorized Placer',
                'approved_at' => optional($purchaseOrder->date_approved ?? $purchaseOrder->updated_at)->format('M d, Y'),
            ];

            if ($isExec) {
                $executiveApprover = $approverData;
            }
        }

        if ($executiveApprover === null) {
            $mayor = User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'EXEC_OFFICER'))
                ->first();
            $executiveApprover = [
                'name' => (string) ($mayor?->name ?? 'Mayor Jeron'),
                'role' => 'Executive Officer / Municipal Mayor',
                'approved_at' => optional($purchaseOrder->date_approved ?? $purchaseOrder->updated_at)->format('M d, Y'),
            ];
        }

        return [
            'is_consolidated' => $prTraceability['is_consolidated'] ?? false,
            'gso_forwarder' => $prTraceability['gso_forwarder'] ?? null,
            'procurement_officer' => $procurementOfficer,
            'executive_approver' => $executiveApprover,
            'procurement_approver' => $approverData,
            'source_supply_requests' => $prTraceability['source_supply_requests'] ?? [],
            'requisitioning_signatories' => $prTraceability['requisitioning_signatories'] ?? [],
            'office_allocations' => $prTraceability['office_allocations'] ?? [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupplyRequest>  $supplyRequests
     * @return array<int, array<string, mixed>>
     */
    private function buildOfficeAllocations($supplyRequests): array
    {
        $itemsMap = [];

        foreach ($supplyRequests as $sr) {
            $officeName = (string) ($sr->office?->name ?? 'Office');
            $officeCode = (string) ($sr->office?->code ?? 'OFFICE');
            $requestCode = (string) $sr->request_code;

            foreach ($sr->items as $item) {
                $itemName = (string) ($item->item?->item_name ?? $item->item_name_snapshot ?? 'Unknown Item');
                $unit = (string) ($item->item?->default_unit ?? 'box');
                $qty = (float) $item->quantity_requested;

                if (! isset($itemsMap[$itemName])) {
                    $itemsMap[$itemName] = [
                        'item_name' => $itemName,
                        'unit' => $unit,
                        'total_quantity' => 0.0,
                        'allocations' => [],
                    ];
                }

                $itemsMap[$itemName]['total_quantity'] += $qty;
                $itemsMap[$itemName]['allocations'][] = [
                    'office_name' => $officeName,
                    'office_code' => $officeCode,
                    'request_code' => $requestCode,
                    'quantity' => $qty,
                ];
            }
        }

        return array_values($itemsMap);
    }

    /**
     * @var array<int, \Illuminate\Support\Collection<int, PurchaseRequest>>
     */
    private array $supplyRequestPrsCache = [];

    /**
     * Get all Purchase Requests associated with a Supply Request (direct + consolidated).
     *
     * @return \Illuminate\Support\Collection<int, PurchaseRequest>
     */
    public function getPurchaseRequestsForSupplyRequest(SupplyRequest $supplyRequest): \Illuminate\Support\Collection
    {
        $srId = (int) $supplyRequest->supply_request_id;
        if ($srId > 0 && isset($this->supplyRequestPrsCache[$srId])) {
            return $this->supplyRequestPrsCache[$srId];
        }

        $prs = collect();

        // 1. Direct relationship
        $supplyRequest->loadMissing([
            'purchaseRequests.requester.role',
            'purchaseRequests.purchaseOrders.supplier',
            'purchaseRequests.purchaseOrders.creator.role',
            'purchaseRequests.purchaseOrders.approver.role',
        ]);

        if ($supplyRequest->purchaseRequests->isNotEmpty()) {
            $prs = $prs->merge($supplyRequest->purchaseRequests);
        }

        // 2. PRs where this supply request code is explicitly listed in consolidated remarks
        $requestCode = trim((string) $supplyRequest->request_code);
        if ($requestCode !== '') {
            $matchingPrs = $this->getPrsWithConsolidatedRemarks()
                ->filter(fn ($pr) => str_contains((string) $pr->remarks, $requestCode));
            $prs = $prs->merge($matchingPrs);
        }

        // 3. Status history references linking this supply request to a PR
        $indexes = $this->getStatusHistoryConsolidationIndexes();
        $historyRemarks = $indexes['remarks_by_sr'][(int) $supplyRequest->supply_request_id] ?? [];

        $prNumbers = [];
        foreach ($historyRemarks as $histRemark) {
            preg_match_all('/PR-[A-Z0-9-]+/', (string) $histRemark, $matches);
            if (! empty($matches[0])) {
                foreach ($matches[0] as $m) {
                    $prNumbers[] = $m;
                }
            }
        }

        if (! empty($prNumbers)) {
            $historyPrs = PurchaseRequest::query()
                ->whereIn('pr_number', array_values(array_unique($prNumbers)))
                ->with([
                    'requester.role',
                    'purchaseOrders.supplier',
                    'purchaseOrders.creator.role',
                    'purchaseOrders.approver.role',
                ])
                ->get();
            $prs = $prs->merge($historyPrs);
        }

        $resolved = $prs->unique('purchase_request_id')->values();
        if ($srId > 0) {
            $this->supplyRequestPrsCache[$srId] = $resolved;
        }

        return $resolved;
    }

    /**
     * Determine which candidate supply request has the active deficit for a delivery with discrepancies.
     *
     * @param  Delivery  $delivery
     * @param  \Illuminate\Support\Collection<int, SupplyRequest>  $candidateRequests
     * @return SupplyRequest|null
     */
    public function resolveCandidateSupplyRequestWithDeficit(Delivery $delivery, \Illuminate\Support\Collection $candidateRequests): ?SupplyRequest
    {
        if ($candidateRequests->isEmpty()) {
            return null;
        }

        if ($candidateRequests->count() === 1) {
            return $candidateRequests->first();
        }

        $delivery->loadMissing(['items.purchaseOrderItem', 'distributionRecords.items']);

        // 1. Identify discrepancy items and accepted quantities in this delivery
        $discrepancyByItem = [];
        $acceptedInDeliveryByItem = [];

        foreach ($delivery->items as $item) {
            $itemId = (int) ($item->purchaseOrderItem?->item_id ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $missing = (float) $item->missing_quantity;
            $damaged = (float) $item->damaged_quantity;
            $accepted = max((float) $item->received_quantity - $damaged, 0.0);

            if (($missing + $damaged) > 0.0001) {
                $discrepancyByItem[$itemId] = ($discrepancyByItem[$itemId] ?? 0.0) + $missing + $damaged;
            }
            $acceptedInDeliveryByItem[$itemId] = ($acceptedInDeliveryByItem[$itemId] ?? 0.0) + $accepted;
        }

        // If no items have discrepancies, fallback to first candidate
        if (empty($discrepancyByItem)) {
            return $candidateRequests->first();
        }

        // 2. Identify how much has ALREADY been released specifically from this delivery
        $deliveryReleasedByItem = $delivery->distributionRecords
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->flatMap(fn ($record) => $record->items)
            ->groupBy(fn ($di) => (int) $di->item_id)
            ->map(fn ($group): float => (float) $group->sum('quantity_released'));

        // Unreleased stock remaining in this delivery available to allocate
        $availableToAllocate = [];
        foreach ($acceptedInDeliveryByItem as $itemId => $acc) {
            $rel = (float) ($deliveryReleasedByItem->get($itemId) ?? 0.0);
            $availableToAllocate[$itemId] = max($acc - $rel, 0.0);
        }

        $deficitByCandidateId = [];

        foreach ($candidateRequests as $sr) {
            $sr->load(['items', 'distributionRecords.items']);
            $srId = (int) $sr->supply_request_id;
            $totalDeficit = 0.0;

            // Quantity already released to this candidate from PRIOR/OTHER deliveries (excluding this delivery)
            $srPriorReleasedByItem = $sr->distributionRecords
                ->where('delivery_id', '!=', (int) $delivery->delivery_id)
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($record) => $record->items)
                ->groupBy(fn ($di) => (int) $di->item_id)
                ->map(fn ($group): float => (float) $group->sum('quantity_released'));

            // Quantity released to this candidate specifically FROM THIS delivery
            $srFromThisDeliveryByItem = $sr->distributionRecords
                ->where('delivery_id', (int) $delivery->delivery_id)
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->flatMap(fn ($record) => $record->items)
                ->groupBy(fn ($di) => (int) $di->item_id)
                ->map(fn ($group): float => (float) $group->sum('quantity_released'));

            foreach ($sr->items as $sri) {
                $itemId = (int) $sri->item_id;
                if (! isset($discrepancyByItem[$itemId])) {
                    continue;
                }

                $requested = (float) $sri->quantity_requested;
                $priorReleased = (float) ($srPriorReleasedByItem->get($itemId) ?? 0.0);
                $stillNeeded = max($requested - $priorReleased, 0.0);

                // If candidate already got released from this delivery, subtract that directly
                $fromThisDel = (float) ($srFromThisDeliveryByItem->get($itemId) ?? 0.0);
                $stillNeeded = max($stillNeeded - $fromThisDel, 0.0);

                if ($stillNeeded <= 0.0001) {
                    continue;
                }

                $availDelivery = $availableToAllocate[$itemId] ?? 0.0;
                $covered = min($stillNeeded, $availDelivery);
                $availableToAllocate[$itemId] = max($availDelivery - $covered, 0.0);

                $uncovered = max($stillNeeded - $covered, 0.0);
                $totalDeficit += $uncovered;
            }

            $deficitByCandidateId[$srId] = $totalDeficit;
        }

        // 3. Find candidate with active deficit (> 0)
        foreach ($candidateRequests as $sr) {
            $srId = (int) $sr->supply_request_id;
            if (($deficitByCandidateId[$srId] ?? 0.0) > 0.0001) {
                return $sr;
            }
        }

        // Fallback: candidate request with highest deficit
        arsort($deficitByCandidateId);
        $topSrId = key($deficitByCandidateId);
        if ($topSrId) {
            $found = $candidateRequests->firstWhere('supply_request_id', $topSrId);
            if ($found instanceof SupplyRequest) {
                return $found;
            }
        }

        return $candidateRequests->first();
    }

    /**
     * Calculate exact allocated fulfillment (released, received, deficits) for a supply request,
     * applying deterministic waterfall allocation across consolidated procurement constituents.
     *
     * @return array{requested: float, released: float, received: float, is_fully_received: bool, is_fully_released: bool, by_item: array<int, array{requested: float, released: float, received: float, deficit_released: float, deficit_received: float}>, allocated_records: \Illuminate\Support\Collection}
     */
    public function calculateAllocatedFulfillment(SupplyRequest $supplyRequest): array
    {
        $supplyRequest->loadMissing([
            'items.item',
            'distributionRecords.items.item',
            'distributionRecords.delivery',
        ]);

        $requestedTotal = (float) $supplyRequest->items->sum('quantity_requested');
        if ($requestedTotal <= 0.0001) {
            return [
                'requested' => 0.0,
                'released' => 0.0,
                'received' => 0.0,
                'is_fully_received' => true,
                'is_fully_released' => true,
                'by_item' => [],
                'allocated_records' => collect(),
            ];
        }

        $followUpService = app(\App\Services\Inventory\SupplyRequestFollowUpService::class);
        $syncService = app(\App\Services\Procurement\ProcurementStatusSyncService::class);

        // Check if this request is a follow-up child
        $parentSr = $followUpService->resolveParentSupplyRequest($supplyRequest);
        $isChild = $parentSr instanceof SupplyRequest;

        // Find root SR and consolidated candidates
        $rootSr = $followUpService->resolveRootSupplyRequest($supplyRequest);
        $candidates = $syncService->resolveConsolidatedConstituentSupplyRequests($rootSr);

        // CASE 1: Standard requisition (not part of a consolidated procurement)
        if ($candidates->count() <= 1) {
            $directRecords = $supplyRequest->distributionRecords
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);

            $childIds = $followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);
            $followUpRecords = empty($childIds)
                ? collect()
                : DistributionRecord::query()
                    ->with(['items.item', 'delivery'])
                    ->whereIn('supply_request_id', $childIds)
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->get();

            $allRecords = $directRecords->concat($followUpRecords)->values();

            $releasedByItem = [];
            $receivedByItem = [];
            foreach ($allRecords as $record) {
                if (in_array($record->status, ['RELEASED', 'RECEIVED'], true)) {
                    foreach ($record->items as $item) {
                        $itemId = (int) $item->item_id;
                        $releasedByItem[$itemId] = ($releasedByItem[$itemId] ?? 0.0) + (float) $item->quantity_released;
                    }
                }
                if ($record->status === 'RECEIVED') {
                    foreach ($record->items as $item) {
                        $itemId = (int) $item->item_id;
                        $receivedByItem[$itemId] = ($receivedByItem[$itemId] ?? 0.0) + (float) ($item->quantity_received ?? $item->quantity_released);
                    }
                }
            }

            $isFullyReceived = true;
            $isFullyReleased = true;
            $byItem = [];
            foreach ($supplyRequest->items as $sri) {
                $itemId = (int) $sri->item_id;
                $req = (float) $sri->quantity_requested;
                $rec = (float) ($receivedByItem[$itemId] ?? 0.0);
                $rel = (float) ($releasedByItem[$itemId] ?? 0.0);

                if ($rec + 0.0001 < $req) {
                    $isFullyReceived = false;
                }
                if ($rel + 0.0001 < $req) {
                    $isFullyReleased = false;
                }

                $byItem[$itemId] = [
                    'requested' => $req,
                    'released' => min($req, $rel),
                    'received' => min($req, $rec),
                    'deficit_released' => max($req - $rel, 0.0),
                    'deficit_received' => max($req - $rec, 0.0),
                ];
            }

            $allocatedRecords = $allRecords
                ->filter(fn ($r) => in_array($r->status, ['RELEASED', 'RECEIVED'], true))
                ->map(function ($r) use ($supplyRequest): array {
                    $isDirect = (int) $r->supply_request_id === (int) $supplyRequest->supply_request_id;
                    return [
                        'distribution_record_id' => (int) $r->distribution_record_id,
                        'distribution_code' => (string) $r->distribution_code,
                        'delivery_code' => (string) ($r->delivery?->delivery_code ?? 'Warehouse Inventory'),
                        'supply_request_code' => (string) ($r->supplyRequest?->request_code ?? $supplyRequest->request_code),
                        'is_follow_up' => ! $isDirect,
                        'is_follow_up_fulfillment' => ! $isDirect,
                        'status' => (string) $r->status,
                        'release_date' => optional($r->release_date)?->toDateString(),
                        'received_date' => optional($r->received_date)?->toDateString(),
                        'storage_location' => (string) ($r->storage_location ?? 'GSO Storage'),
                        'items' => $r->items->map(fn ($di): array => [
                            'distribution_item_id' => (int) $di->distribution_item_id,
                            'item_id' => (int) $di->item_id,
                            'item_name' => (string) ($di->item?->item_name ?? $di->item_name_snapshot ?? 'Item'),
                            'unit' => (string) ($di->unit ?? $di->item?->default_unit ?? 'UNIT'),
                            'quantity_released' => (float) $di->quantity_released,
                            'quantity_received' => (float) ($di->quantity_received ?? 0),
                            'quantity_damaged' => (float) ($di->quantity_damaged ?? 0),
                        ])->values()->all(),
                    ];
                });

            return [
                'requested' => $requestedTotal,
                'released' => (float) array_sum($releasedByItem),
                'received' => (float) array_sum($receivedByItem),
                'is_fully_received' => $isFullyReceived,
                'is_fully_released' => $isFullyReleased,
                'by_item' => $byItem,
                'allocated_records' => $allocatedRecords,
            ];
        }

        // CASE 2: If $supplyRequest is itself a follow-up child (e.g. SR-5 or SR-6)
        if ($isChild) {
            $directRecords = $supplyRequest->distributionRecords
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED']);

            $childIds = $followUpService->resolveAllFollowUpChildSupplyRequestIds($supplyRequest);
            $followUpRecords = empty($childIds)
                ? collect()
                : DistributionRecord::query()
                    ->with(['items.item', 'delivery', 'supplyRequest'])
                    ->whereIn('supply_request_id', $childIds)
                    ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                    ->get();

            $allRecords = $directRecords->concat($followUpRecords)->values();

            $releasedByItem = [];
            $receivedByItem = [];
            foreach ($allRecords as $record) {
                if (in_array($record->status, ['RELEASED', 'RECEIVED'], true)) {
                    foreach ($record->items as $item) {
                        $itemId = (int) $item->item_id;
                        $releasedByItem[$itemId] = ($releasedByItem[$itemId] ?? 0.0) + (float) $item->quantity_released;
                    }
                }
                if ($record->status === 'RECEIVED') {
                    foreach ($record->items as $item) {
                        $itemId = (int) $item->item_id;
                        $receivedByItem[$itemId] = ($receivedByItem[$itemId] ?? 0.0) + (float) ($item->quantity_received ?? $item->quantity_released);
                    }
                }
            }

            $isFullyReceived = true;
            $isFullyReleased = true;
            $byItem = [];
            foreach ($supplyRequest->items as $sri) {
                $itemId = (int) $sri->item_id;
                $req = (float) $sri->quantity_requested;
                $itemRel = (float) ($releasedByItem[$itemId] ?? 0.0);
                $itemRec = (float) ($receivedByItem[$itemId] ?? 0.0);

                if ($itemRec + 0.0001 < $req) {
                    $isFullyReceived = false;
                }
                if ($itemRel + 0.0001 < $req) {
                    $isFullyReleased = false;
                }

                $byItem[$itemId] = [
                    'requested' => $req,
                    'released' => min($req, $itemRel),
                    'received' => min($req, $itemRec),
                    'deficit_released' => max($req - $itemRel, 0.0),
                    'deficit_received' => max($req - $itemRec, 0.0),
                ];
            }

            $allocatedRecords = $allRecords
                ->filter(fn ($r) => in_array($r->status, ['RELEASED', 'RECEIVED'], true))
                ->map(function ($r) use ($supplyRequest): array {
                    $isDirect = (int) $r->supply_request_id === (int) $supplyRequest->supply_request_id;

                    return [
                        'distribution_record_id' => (int) $r->distribution_record_id,
                        'distribution_code' => (string) $r->distribution_code,
                        'delivery_code' => (string) ($r->delivery?->delivery_code ?? 'Warehouse Inventory'),
                        'supply_request_code' => (string) ($r->supplyRequest?->request_code ?? $supplyRequest->request_code),
                        'is_follow_up' => ! $isDirect,
                        'is_follow_up_fulfillment' => ! $isDirect,
                        'status' => (string) $r->status,
                        'release_date' => optional($r->release_date)?->toDateString(),
                        'received_date' => optional($r->received_date)?->toDateString(),
                        'storage_location' => (string) ($r->storage_location ?? 'GSO Storage'),
                        'items' => $r->items->map(fn ($di): array => [
                            'distribution_item_id' => (int) $di->distribution_item_id,
                            'item_id' => (int) $di->item_id,
                            'item_name' => (string) ($di->item?->item_name ?? $di->item_name_snapshot ?? 'Item'),
                            'unit' => (string) ($di->unit ?? $di->item?->default_unit ?? 'UNIT'),
                            'quantity_released' => (float) $di->quantity_released,
                            'quantity_received' => (float) ($di->quantity_received ?? 0),
                            'quantity_damaged' => (float) ($di->quantity_damaged ?? 0),
                        ])->values()->all(),
                    ];
                });

            return [
                'requested' => $requestedTotal,
                'released' => (float) array_sum($releasedByItem),
                'received' => (float) array_sum($receivedByItem),
                'is_fully_received' => $isFullyReceived,
                'is_fully_released' => $isFullyReleased,
                'by_item' => $byItem,
                'allocated_records' => $allocatedRecords,
            ];
        }

        // CASE 3: Consolidated constituent parent (e.g. SR-3 or SR-4)
        return $this->runConsolidatedWaterfallAllocation($supplyRequest, $candidates);
    }

    /**
     * Perform deterministic waterfall allocation across consolidated constituent requests.
     *
     * @param  SupplyRequest  $targetSr
     * @param  \Illuminate\Support\Collection<int, SupplyRequest>  $candidates
     * @return array{requested: float, released: float, received: float, is_fully_received: bool, is_fully_released: bool, by_item: array<int, array{requested: float, released: float, received: float, deficit_released: float, deficit_received: float}>, allocated_records: \Illuminate\Support\Collection}
     */
    private function runConsolidatedWaterfallAllocation(SupplyRequest $targetSr, \Illuminate\Support\Collection $candidates): array
    {
        $followUpService = app(\App\Services\Inventory\SupplyRequestFollowUpService::class);

        // Gather all family supply request IDs
        $allFamilySrIds = $candidates->pluck('supply_request_id')->all();
        foreach ($candidates as $cand) {
            $allFamilySrIds = array_merge($allFamilySrIds, $followUpService->resolveAllFollowUpChildSupplyRequestIds($cand));
        }
        $allFamilySrIds = array_values(array_unique(array_filter(array_map('intval', $allFamilySrIds))));
        $parentSrIds = $candidates->pluck('supply_request_id')->map(fn ($id) => (int) $id)->all();
        $childSrIds = array_values(array_diff($allFamilySrIds, $parentSrIds));

        // Load all direct distribution records on parents
        $directRecords = DistributionRecord::query()
            ->with(['items.item', 'delivery', 'supplyRequest'])
            ->whereIn('supply_request_id', $parentSrIds)
            ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
            ->get();

        // Load all follow-up distribution records (ordered by distribution_record_id ASC)
        $followUpRecords = empty($childSrIds)
            ? collect()
            : DistributionRecord::query()
                ->with(['items.item', 'delivery', 'supplyRequest'])
                ->whereIn('supply_request_id', $childSrIds)
                ->whereIn('status', ['DRAFT', 'RELEASED', 'RECEIVED'])
                ->orderBy('distribution_record_id')
                ->get();

        // 1. Map requirements and direct fulfillments for each candidate per item
        $targetSrId = (int) $targetSr->supply_request_id;
        $itemsMap = [];

        foreach ($candidates as $cand) {
            $candId = (int) $cand->supply_request_id;
            $cand->loadMissing('items.item');

            $candDirect = $directRecords->where('supply_request_id', $candId);

            foreach ($cand->items as $sri) {
                $itemId = (int) $sri->item_id;
                $req = (float) $sri->quantity_requested;

                $rel = (float) $candDirect
                    ->whereIn('status', ['RELEASED', 'RECEIVED'])
                    ->flatMap(fn ($r) => $r->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_released');

                $rec = (float) $candDirect
                    ->where('status', 'RECEIVED')
                    ->flatMap(fn ($r) => $r->items)
                    ->where('item_id', $itemId)
                    ->sum('quantity_received');

                $itemsMap[$itemId]['name'][$candId] = (string) ($sri->item?->item_name ?? $sri->item_name_snapshot ?? 'Item');
                $itemsMap[$itemId]['unit'][$candId] = (string) ($sri->item?->default_unit ?? 'UNIT');
                $itemsMap[$itemId]['req'][$candId] = $req;
                $itemsMap[$itemId]['dir_rel'][$candId] = $rel;
                $itemsMap[$itemId]['dir_rec'][$candId] = $rec;
                $itemsMap[$itemId]['def_rel'][$candId] = max($req - $rel, 0.0);
                $itemsMap[$itemId]['def_rec'][$candId] = max($req - $rec, 0.0);
                $itemsMap[$itemId]['alloc_rel'][$candId] = 0.0;
                $itemsMap[$itemId]['alloc_rec'][$candId] = 0.0;
            }
        }

        // 2. Track allocated portions per follow-up record for $targetSr
        $targetFollowUpContributions = [];

        // 3. Run the Waterfall per Item
        foreach ($itemsMap as $itemId => &$meta) {
            // Prioritize partially fulfilled candidates first, then by supply_request_id ASC
            $priorityCandIds = array_keys($meta['req']);
            usort($priorityCandIds, function ($a, $b) use ($meta) {
                $aRec = $meta['dir_rec'][$a] ?? 0.0;
                $aReq = $meta['req'][$a] ?? 0.0;
                $aDef = $meta['def_rec'][$a] ?? 0.0;

                $bRec = $meta['dir_rec'][$b] ?? 0.0;
                $bReq = $meta['req'][$b] ?? 0.0;
                $bDef = $meta['def_rec'][$b] ?? 0.0;

                $aPartial = ($aRec > 0.0001 && $aDef > 0.0001);
                $bPartial = ($bRec > 0.0001 && $bDef > 0.0001);

                if ($aPartial !== $bPartial) {
                    return $bPartial ? 1 : -1;
                }

                if ($aPartial && $bPartial) {
                    $aPct = $aReq > 0 ? ($aRec / $aReq) : 0;
                    $bPct = $bReq > 0 ? ($bRec / $bReq) : 0;
                    if (abs($aPct - $bPct) > 0.0001) {
                        return $bPct <=> $aPct;
                    }
                }

                return $a <=> $b;
            });

            // Waterfall follow-up records across prioritized candidates
            foreach ($followUpRecords as $fuRec) {
                $fuItem = $fuRec->items->firstWhere('item_id', $itemId);
                if (! $fuItem) {
                    continue;
                }

                $fuRelQty = in_array($fuRec->status, ['RELEASED', 'RECEIVED'], true)
                    ? (float) $fuItem->quantity_released
                    : 0.0;

                $fuRecQty = ($fuRec->status === 'RECEIVED')
                    ? (float) ($fuItem->quantity_received ?? $fuItem->quantity_released)
                    : 0.0;

                $remRel = $fuRelQty;
                $remRec = $fuRecQty;

                foreach ($priorityCandIds as $cId) {
                    if ($remRel <= 0.0001 && $remRec <= 0.0001) {
                        break;
                    }

                    // Allocation for release
                    $relTake = 0.0;
                    if ($remRel > 0.0001 && $meta['def_rel'][$cId] > 0.0001) {
                        $relTake = min($remRel, $meta['def_rel'][$cId]);
                        $meta['def_rel'][$cId] -= $relTake;
                        $meta['alloc_rel'][$cId] += $relTake;
                        $remRel -= $relTake;
                    }

                    // Allocation for receipt
                    $recTake = 0.0;
                    if ($remRec > 0.0001 && $meta['def_rec'][$cId] > 0.0001) {
                        $recTake = min($remRec, $meta['def_rec'][$cId]);
                        $meta['def_rec'][$cId] -= $recTake;
                        $meta['alloc_rec'][$cId] += $recTake;
                        $remRec -= $recTake;
                    }

                    if ($cId === $targetSrId && ($relTake > 0.0001 || $recTake > 0.0001)) {
                        $recId = (int) $fuRec->distribution_record_id;
                        if (! isset($targetFollowUpContributions[$recId])) {
                            $targetFollowUpContributions[$recId] = [
                                'record' => $fuRec,
                                'items' => [],
                            ];
                        }
                        $targetFollowUpContributions[$recId]['items'][$itemId] = [
                            'distribution_item_id' => (int) $fuItem->distribution_item_id,
                            'item_id' => $itemId,
                            'item_name' => $meta['name'][$cId] ?? 'Item',
                            'unit' => $meta['unit'][$cId] ?? 'UNIT',
                            'quantity_released' => $relTake,
                            'quantity_received' => $recTake,
                            'quantity_damaged' => 0.0,
                        ];
                    }
                }
            }
        }
        unset($meta);

        // 4. Calculate total requested, released, received for $targetSr
        $totalReq = 0.0;
        $totalRel = 0.0;
        $totalRec = 0.0;
        $isFullyReceived = true;
        $isFullyReleased = true;
        $byItem = [];

        foreach ($itemsMap as $itemId => $meta) {
            $req = (float) ($meta['req'][$targetSrId] ?? 0.0);
            $rel = (float) (($meta['dir_rel'][$targetSrId] ?? 0.0) + ($meta['alloc_rel'][$targetSrId] ?? 0.0));
            $rec = (float) (($meta['dir_rec'][$targetSrId] ?? 0.0) + ($meta['alloc_rec'][$targetSrId] ?? 0.0));

            $totalReq += $req;
            $totalRel += $rel;
            $totalRec += $rec;

            if ($rec + 0.0001 < $req) {
                $isFullyReceived = false;
            }
            if ($rel + 0.0001 < $req) {
                $isFullyReleased = false;
            }

            $byItem[$itemId] = [
                'requested' => $req,
                'released' => min($req, $rel),
                'received' => min($req, $rec),
                'deficit_released' => max($req - $rel, 0.0),
                'deficit_received' => max($req - $rec, 0.0),
            ];
        }

        // 5. Build allocated records for Related Records tab
        $allocatedRecords = collect();

        // Add direct records of $targetSr
        $targetDirect = $directRecords
            ->where('supply_request_id', $targetSrId)
            ->filter(fn ($r) => in_array($r->status, ['RELEASED', 'RECEIVED'], true));

        foreach ($targetDirect as $r) {
            $allocatedRecords->push([
                'distribution_record_id' => (int) $r->distribution_record_id,
                'distribution_code' => (string) $r->distribution_code,
                'delivery_code' => (string) ($r->delivery?->delivery_code ?? 'Warehouse Inventory'),
                'supply_request_code' => (string) $targetSr->request_code,
                'is_follow_up' => false,
                'is_follow_up_fulfillment' => false,
                'status' => (string) $r->status,
                'release_date' => optional($r->release_date)?->toDateString(),
                'received_date' => optional($r->received_date)?->toDateString(),
                'storage_location' => (string) ($r->storage_location ?? 'GSO Storage'),
                'items' => $r->items->map(fn ($di): array => [
                    'distribution_item_id' => (int) $di->distribution_item_id,
                    'item_id' => (int) $di->item_id,
                    'item_name' => (string) ($di->item?->item_name ?? $di->item_name_snapshot ?? 'Item'),
                    'unit' => (string) ($di->unit ?? $di->item?->default_unit ?? 'UNIT'),
                    'quantity_released' => (float) $di->quantity_released,
                    'quantity_received' => (float) ($di->quantity_received ?? 0),
                    'quantity_damaged' => (float) ($di->quantity_damaged ?? 0),
                ])->values()->all(),
            ]);
        }

        // Add follow-up records that allocated stock to $targetSr
        foreach ($targetFollowUpContributions as $contrib) {
            $r = $contrib['record'];
            $items = array_values($contrib['items']);
            $allocatedRecords->push([
                'distribution_record_id' => (int) $r->distribution_record_id,
                'distribution_code' => (string) $r->distribution_code,
                'delivery_code' => (string) ($r->delivery?->delivery_code ?? 'Warehouse Inventory'),
                'supply_request_code' => (string) ($r->supplyRequest?->request_code ?? $targetSr->request_code),
                'is_follow_up' => true,
                'is_follow_up_fulfillment' => true,
                'status' => (string) $r->status,
                'release_date' => optional($r->release_date)?->toDateString(),
                'received_date' => optional($r->received_date)?->toDateString(),
                'storage_location' => (string) ($r->storage_location ?? 'GSO Storage'),
                'items' => $items,
            ]);
        }

        return [
            'requested' => $totalReq,
            'released' => $totalRel,
            'received' => $totalRec,
            'is_fully_received' => $isFullyReceived,
            'is_fully_released' => $isFullyReleased,
            'by_item' => $byItem,
            'allocated_records' => $allocatedRecords->sortByDesc('distribution_record_id')->values(),
        ];
    }
}
