<?php

namespace App\Services\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SupplyRequest;
use App\Models\SupplyRequestItem;
use App\Models\User;

class ConsolidationTraceabilityService
{
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

        $remarks = (string) $purchaseRequest->remarks;
        $isConsolidated = str_contains($remarks, 'Consolidated PR') || str_contains($remarks, 'supply request(s)');

        // Find any other supply requests linked to this PR via remarks or direct linkage
        $prIdPad = sprintf('%06d', (int) $purchaseRequest->purchase_request_id);
        $linkedPrs = PurchaseRequest::query()
            ->where('remarks', 'like', "%PR-{$prIdPad}%")
            ->where('purchase_request_id', '!=', $purchaseRequest->purchase_request_id)
            ->with(['supplyRequest.office', 'supplyRequest.requester', 'supplyRequest.items.item'])
            ->get();

        $allSupplyRequests = collect();
        if ($purchaseRequest->supplyRequest instanceof SupplyRequest) {
            $allSupplyRequests->push($purchaseRequest->supplyRequest);
        }
        foreach ($linkedPrs as $lPr) {
            if ($lPr->supplyRequest instanceof SupplyRequest) {
                $allSupplyRequests->push($lPr->supplyRequest);
            }
        }

        // If remarks list request codes like SR-XXXX, also look them up if not yet in the collection
        preg_match_all('/SR-[A-Z0-9-]+/', $remarks, $matches);
        if (! empty($matches[0])) {
            $foundCodes = $matches[0];
            $extraRequests = SupplyRequest::query()
                ->whereIn('request_code', $foundCodes)
                ->with(['office', 'requester', 'items.item'])
                ->get();
            $allSupplyRequests = $allSupplyRequests->merge($extraRequests);
        }

        $allSupplyRequests = $allSupplyRequests->unique('supply_request_id')->values();

        // A PR is only considered consolidated if it merges 2 or more distinct supply requests
        $isConsolidated = $allSupplyRequests->count() >= 2;

        // Build GSO Forwarder attribution
        $gsoForwarder = null;
        if ($purchaseRequest->requester instanceof User) {
            $roleCode = (string) ($purchaseRequest->requester->role?->code ?? '');
            $gsoForwarder = [
                'name' => (string) $purchaseRequest->requester->name,
                'role' => $roleCode === 'GSO_OFFICER' ? 'GSO Property Custodian' : ($purchaseRequest->requester->role?->name ?? 'Forwarding Officer'),
                'forwarded_at' => optional($purchaseRequest->created_at)->format('M d, Y h:i A'),
            ];
        }

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

        // Build office allocations per item
        $officeAllocations = $this->buildOfficeAllocations($allSupplyRequests);

        return [
            'is_consolidated' => $isConsolidated,
            'gso_forwarder' => $gsoForwarder,
            'source_supply_requests' => $sourceRequests,
            'office_allocations' => $officeAllocations,
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
                'role' => 'BAC Procurement Officer',
                'processed_at' => optional($purchaseOrder->created_at)->format('M d, Y h:i A'),
            ];
        }

        // Executive Officer / Mayor attribution
        $executiveApprover = null;
        if ($purchaseOrder->approver instanceof User) {
            $executiveApprover = [
                'name' => (string) $purchaseOrder->approver->name,
                'role' => 'Executive Officer / Municipal Mayor',
                'approved_at' => optional($purchaseOrder->date_approved ?? $purchaseOrder->updated_at)->format('M d, Y'),
            ];
        }

        return [
            'is_consolidated' => $prTraceability['is_consolidated'] ?? false,
            'gso_forwarder' => $prTraceability['gso_forwarder'] ?? null,
            'procurement_officer' => $procurementOfficer,
            'executive_approver' => $executiveApprover,
            'source_supply_requests' => $prTraceability['source_supply_requests'] ?? [],
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
