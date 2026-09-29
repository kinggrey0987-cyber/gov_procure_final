<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Services\Procurement\ConsolidationTraceabilityService;
use App\Services\Procurement\ProcurementStatusSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $traceability = app(ConsolidationTraceabilityService::class);
        $syncService = app(ProcurementStatusSyncService::class);

        // 1. Repair parent linkage for follow-up PRs created from consolidated partial deliveries
        $followUpPrs = PurchaseRequest::query()
            ->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP')
            ->whereNotNull('source_delivery_id')
            ->with(['sourceDelivery.purchaseOrder', 'supplyRequest'])
            ->get();

        foreach ($followUpPrs as $pr) {
            $delivery = $pr->sourceDelivery;
            $po = $delivery?->purchaseOrder;
            if (! $po) {
                continue;
            }

            $candidateRequests = $traceability->getSourceSupplyRequests($po);
            if ($candidateRequests->isEmpty() && $po->purchaseRequest) {
                $candidateRequests = $traceability->getSourceSupplyRequests($po->purchaseRequest);
            }

            if ($candidateRequests->count() > 1) {
                $correctParent = $traceability->resolveCandidateSupplyRequestWithDeficit($delivery, $candidateRequests);
                if ($correctParent instanceof SupplyRequest && (int) $pr->source_supply_request_id !== (int) $correctParent->supply_request_id) {
                    $oldParentId = (int) $pr->source_supply_request_id;
                    $oldCode = $candidateRequests->firstWhere('supply_request_id', $oldParentId)?->request_code;

                    $pr->forceFill(['source_supply_request_id' => (int) $correctParent->supply_request_id])->save();

                    // Update child supply request review notes if referencing wrong parent
                    $childSr = $pr->supplyRequest;
                    if ($childSr instanceof SupplyRequest && $oldCode) {
                        $childSr->forceFill([
                            'review_notes' => str_replace((string) $oldCode, (string) $correctParent->request_code, (string) $childSr->review_notes),
                            'purpose' => str_replace((string) $oldCode, (string) $correctParent->request_code, (string) $childSr->purpose),
                        ])->save();
                    }
                }
            }
        }

        // 2. Revert premature COMPLETED status on follow-up PRs with unfulfilled procurement
        $completedFollowUpPrs = PurchaseRequest::query()
            ->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP')
            ->where('status', 'COMPLETED')
            ->with(['purchaseOrders.deliveries.items', 'purchaseOrders.items'])
            ->get();

        foreach ($completedFollowUpPrs as $pr) {
            if (! $syncService->canTransitionPurchaseRequestToCompleted($pr)) {
                $pr->forceFill(['status' => 'OPEN'])->save();

                StatusHistory::query()->create([
                    'reference_type' => PurchaseRequest::class,
                    'reference_id' => (int) $pr->purchase_request_id,
                    'old_status' => 'COMPLETED',
                    'new_status' => 'OPEN',
                    'changed_by' => null,
                    'remarks' => 'Status reverted from COMPLETED to OPEN: Follow-up purchase request was prematurely marked completed prior to replacement procurement.',
                    'changed_at' => now(),
                ]);
            }
        }

        // 3. Revert premature RECEIVED status on follow-up child supply requests that received no goods
        $completedChildSrs = SupplyRequest::query()
            ->where('status', 'RECEIVED')
            ->whereHas('purchaseRequests', fn ($q) => $q->where('source_type', 'PARTIAL_DELIVERY_FOLLOW_UP'))
            ->with(['distributionRecords.items'])
            ->get();

        foreach ($completedChildSrs as $childSr) {
            $hasReceivedGoods = $childSr->distributionRecords
                ->where('status', 'RECEIVED')
                ->flatMap->items
                ->sum('quantity_received') > 0.0001;

            if (! $hasReceivedGoods) {
                $childSr->forceFill(['status' => 'SUBMITTED'])->save();

                StatusHistory::query()->create([
                    'reference_type' => SupplyRequest::class,
                    'reference_id' => (int) $childSr->supply_request_id,
                    'old_status' => 'RECEIVED',
                    'new_status' => 'SUBMITTED',
                    'changed_by' => null,
                    'remarks' => 'Status reverted from RECEIVED to SUBMITTED: Follow-up requisition was prematurely marked received prior to distribution fulfillment.',
                    'changed_at' => now(),
                ]);
            }
        }

        // 4. Revert premature COMPLETED status on consolidated PRs with unfulfilled constituent requests or deliveries
        $completedConsolidatedPrs = PurchaseRequest::query()
            ->where('status', 'COMPLETED')
            ->with(['purchaseOrders.deliveries.items', 'purchaseOrders.items'])
            ->get();

        foreach ($completedConsolidatedPrs as $pr) {
            $sourceRequests = $traceability->getSourceSupplyRequests($pr);
            if ($sourceRequests->count() >= 2) {
                if (! $syncService->canTransitionPurchaseRequestToCompleted($pr)) {
                    $pr->forceFill(['status' => 'APPROVED'])->save();

                    StatusHistory::query()->create([
                        'reference_type' => PurchaseRequest::class,
                        'reference_id' => (int) $pr->purchase_request_id,
                        'old_status' => 'COMPLETED',
                        'new_status' => 'APPROVED',
                        'changed_by' => null,
                        'remarks' => 'Status reverted from COMPLETED to APPROVED: Consolidated purchase request has unfulfilled constituent requisitions or incomplete deliveries.',
                        'changed_at' => now(),
                    ]);

                    foreach ($pr->purchaseOrders as $po) {
                        if ((string) $po->status === 'COMPLETED' && ! $syncService->canTransitionPurchaseOrderToCompleted($po)) {
                            $po->forceFill(['status' => 'PLACED'])->save();

                            StatusHistory::query()->create([
                                'reference_type' => PurchaseOrder::class,
                                'reference_id' => (int) $po->purchase_order_id,
                                'old_status' => 'COMPLETED',
                                'new_status' => 'PLACED',
                                'changed_by' => null,
                                'remarks' => 'Status reverted from COMPLETED to PLACED: Purchase order has pending delivery discrepancy follow-ups.',
                                'changed_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data repair migration does not require rollback
    }
};
