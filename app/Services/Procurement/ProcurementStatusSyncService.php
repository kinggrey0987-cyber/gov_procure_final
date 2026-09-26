<?php

namespace App\Services\Procurement;

use App\Models\ApprovalHistory;
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
                }

                $current = $parent;
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

    private function isFullyReceived(SupplyRequest $supplyRequest): bool
    {
        $requested = (float) $supplyRequest->items->sum('quantity_requested');
        if ($requested <= 0) {
            return false;
        }

        $received = (float) $this->allFulfillmentDistributionRecords($supplyRequest)
            ->where('status', 'RECEIVED')
            ->flatMap(fn (DistributionRecord $record) => $record->items)
            ->sum('quantity_received');

        return $received + 0.0001 >= $requested;
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

    /**
     * @param  EloquentCollection<int, PurchaseRequest>  $purchaseRequests
     */
    private function transitionPurchaseRequests(EloquentCollection $purchaseRequests, ?User $actor, string $remarks): void
    {
        foreach ($purchaseRequests as $purchaseRequest) {
            $oldStatus = strtoupper((string) $purchaseRequest->status);
            if (! in_array($oldStatus, ['OPEN', 'PROCESSING'], true)) {
                continue;
            }

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
            $oldStatus = strtoupper((string) $purchaseOrder->status);
            if ($oldStatus !== 'PLACED') {
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
