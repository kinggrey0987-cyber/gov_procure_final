<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;

class ConsolidationTraceabilityService
{
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

            $remarks = (string) $purchaseRequest->remarks;
            $prIdPad = sprintf('%06d', (int) $purchaseRequest->purchase_request_id);

            $linkedPrs = PurchaseRequest::query()
                ->where('remarks', 'like', "%PR-{$prIdPad}%")
                ->where('purchase_request_id', '!=', $purchaseRequest->purchase_request_id)
                ->with([
                    'supplyRequest.office',
                    'supplyRequest.requester',
                    'supplyRequest.items.item',
                    'supplyRequest.items.ppmpItem',
                ])
                ->get();

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

            $historySrIds = StatusHistory::query()
                ->where('reference_type', SupplyRequest::class)
                ->where('remarks', 'like', "%PR-{$prIdPad}%")
                ->pluck('reference_id')
                ->toArray();

            // Also search by pr_number since consolidated PR status history
            // uses the full pr_number format (e.g. "PR-2026-09-0015"), not the
            // zero-padded ID format (e.g. "PR-000103").
            $prNumber = (string) $purchaseRequest->pr_number;
            if ($prNumber !== '') {
                $historySrIdsByPrNumber = StatusHistory::query()
                    ->where('reference_type', SupplyRequest::class)
                    ->where('remarks', 'like', "%{$prNumber}%")
                    ->pluck('reference_id')
                    ->toArray();
                $historySrIds = array_unique(array_merge($historySrIds, $historySrIdsByPrNumber));
            }

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

        return [
            'is_consolidated' => $isConsolidated,
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
        if ($purchaseOrder->approver instanceof User) {
            $roleCode = (string) ($purchaseOrder->approver->role?->code ?? '');
            $isExec = $roleCode === 'EXEC_OFFICER';
            $approverData = [
                'name' => (string) $purchaseOrder->approver->name,
                'role' => $isExec ? 'Executive Officer / Municipal Mayor' : 'Procurement Unit / Authorized Placer',
                'approved_at' => optional($purchaseOrder->date_approved ?? $purchaseOrder->updated_at)->format('M d, Y'),
            ];
        }

        return [
            'is_consolidated' => $prTraceability['is_consolidated'] ?? false,
            'gso_forwarder' => $prTraceability['gso_forwarder'] ?? null,
            'procurement_officer' => $procurementOfficer,
            'executive_approver' => $approverData,
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
}
