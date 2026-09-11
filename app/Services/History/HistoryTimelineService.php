<?php

namespace App\Services\History;

use App\Models\ApprovalHistory;
use App\Models\Claim;
use App\Models\Ppmp;
use App\Models\PublicDisclosure;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\StatusHistory;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HistoryTimelineService
{
    /**
     * @return array{status: Collection<int, StatusHistory>, approval: Collection<int, ApprovalHistory>}
     */
    public function forRecord(User $actor, Model $record): array
    {
        $this->assertCanViewRecord($actor, $record);

        $referenceType = $record::class;
        $referenceId = $this->resolveReferenceId($record);

        $statusHistory = StatusHistory::query()
            ->forReference($referenceType, $referenceId)
            ->with(['changedBy.role', 'changedBy.office'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get();

        $approvalHistory = ApprovalHistory::query()
            ->forReference($referenceType, $referenceId)
            ->with(['approver.role', 'approver.office'])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->get();

        return [
            'status' => $statusHistory,
            'approval' => $approvalHistory,
        ];
    }

    private function assertCanViewRecord(User $actor, Model $record): void
    {
        $allowed = match ($record::class) {
            Ppmp::class => $actor->can('office.ppmp.view', $record) || $actor->can('executive.ppmp-approval.view', $record),
            SupplyRequest::class => $actor->can('office.supply-request.view', $record),
            PurchaseRequest::class => $actor->can('procurement.purchase-request.view', $record),
            PurchaseOrder::class => $actor->can('procurement.purchase-order.view', $record),
            Claim::class => $actor->can('claim.view', $record),
            PublicDisclosure::class => $actor->can('public-disclosure.view'),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages([
                'authorization' => 'Not authorized to view history for this record.',
            ]);
        }
    }

    private function resolveReferenceId(Model $record): int
    {
        return match ($record::class) {
            Ppmp::class => (int) $record->getAttribute('ppmp_id'),
            SupplyRequest::class => (int) $record->getAttribute('supply_request_id'),
            PurchaseRequest::class => (int) $record->getAttribute('purchase_request_id'),
            PurchaseOrder::class => (int) $record->getAttribute('purchase_order_id'),
            Claim::class => (int) $record->getAttribute('claims_id'),
            PublicDisclosure::class => (int) $record->getAttribute('disclosure_id'),
            default => (int) $record->getKey(),
        };
    }
}
