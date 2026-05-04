<?php

namespace App\Services\Notifications;

use App\Models\Alert;
use App\Models\Anomaly;
use App\Models\Claim;
use App\Models\Delivery;
use App\Models\DistributionRecord;
use App\Models\Notification;
use App\Models\Ppmp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PublicDisclosure;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationCenterService
{
    public function paginateForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->forUser((int) $user->id)
            ->latest('notification_id')
            ->paginate($perPage);
    }

    public function markAsRead(User $user, Notification $notification): Notification
    {
        $this->assertOwnership($user, $notification);

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
            ])->save();
        }

        return $notification;
    }

    public function markAllAsRead(User $user): int
    {
        return (int) DB::transaction(function () use ($user): int {
            return Notification::query()
                ->forUser((int) $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        });
    }

    public function resolveTargetUrl(User $user, Notification $notification): ?string
    {
        if (! $notification->reference_type || ! $notification->reference_id) {
            return null;
        }

        $referenceType = (string) $notification->reference_type;
        $referenceId = (int) $notification->reference_id;

        if ($referenceType === Ppmp::class) {
            $record = Ppmp::query()->where('ppmp_id', $referenceId)->first();

            if ($record && $user->can('executive.ppmp-approval.view', $record)) {
                return route('executive.ppmp-approvals.show', $record->ppmp_id);
            }

            return $record && $user->can('office.ppmp.view', $record)
                ? route('office.ppmp.show', $record->ppmp_id)
                : null;
        }

        if ($referenceType === SupplyRequest::class) {
            $record = SupplyRequest::query()->where('supply_request_id', $referenceId)->first();

            return $record && $user->can('office.supply-request.view', $record)
                ? route('office.supply-requests.show', $record->supply_request_id)
                : null;
        }

        if ($referenceType === PurchaseRequest::class) {
            $record = PurchaseRequest::query()->where('purchase_request_id', $referenceId)->first();

            return $record && $user->can('procurement.purchase-request.view', $record)
                ? route('procurement.purchase-requests.show', $record->purchase_request_id)
                : null;
        }

        if ($referenceType === PurchaseOrder::class) {
            $record = PurchaseOrder::query()->where('purchase_order_id', $referenceId)->first();

            return $record && $user->can('procurement.purchase-order.view', $record)
                ? route('procurement.purchase-orders.show', $record->purchase_order_id)
                : null;
        }

        if ($referenceType === Delivery::class) {
            $record = Delivery::query()->where('delivery_id', $referenceId)->first();

            return $record && $user->can('delivery.view', $record)
                ? route('inventory.deliveries.show', $record->delivery_id)
                : null;
        }

        if ($referenceType === DistributionRecord::class) {
            $record = DistributionRecord::query()->where('distribution_record_id', $referenceId)->first();

            if (! $record || ! $user->can('distribution.view', $record)) {
                return null;
            }

            if ($user->can('domain.office.access') && ! $user->can('domain.inventory.access')) {
                return route('office.distribution-records.show', $record->distribution_record_id);
            }

            return route('inventory.distribution-records.show', $record->distribution_record_id);
        }

        if ($referenceType === Claim::class) {
            $record = Claim::query()->where('claims_id', $referenceId)->first();

            if (! $record || ! $user->can('claim.view', $record)) {
                return null;
            }

            if ($user->can('domain.office.access') && ! $user->can('domain.inventory.access')) {
                return route('office.claims.show', $record->claims_id);
            }

            return route('inventory.claims.show', $record->claims_id);
        }

        if ($referenceType === Alert::class) {
            $record = Alert::query()->where('alerts_id', $referenceId)->first();

            if (! $record || ! $user->can('alert.view-any')) {
                return null;
            }

            if ($user->can('domain.executive.access')) {
                return route('executive.alerts.show', $record->alerts_id);
            }

            if ($user->can('domain.procurement.access')) {
                return route('procurement.alerts.index', [
                    'search' => (string) $record->alert_type,
                    'status' => (string) $record->status,
                ]);
            }

            if ($user->can('domain.inventory.access')) {
                return route('inventory.alerts.index', [
                    'search' => (string) $record->alert_type,
                    'status' => (string) $record->status,
                ]);
            }

            return null;
        }

        if ($referenceType === Anomaly::class) {
            $record = Anomaly::query()->where('anomaly_id', $referenceId)->first();

            return $record && $user->can('anomaly.view', $record)
                ? route('executive.anomalies.show', $record->anomaly_id)
                : null;
        }

        if ($referenceType === PublicDisclosure::class) {
            $record = PublicDisclosure::query()->where('disclosure_id', $referenceId)->first();

            if (! $record) {
                return null;
            }

            if ($user->can('public-disclosure.manage')) {
                return route('executive.disclosures.show', $record->disclosure_id);
            }

            if ($user->isPublicUser() && (string) $record->status === 'PUBLISHED' && $record->published_at) {
                return route('public.disclosures.show', $record->disclosure_id);
            }

            return null;
        }

        return null;
    }

    public function unreadCountForUser(User $user): int
    {
        return Notification::query()
            ->forUser((int) $user->id)
            ->unread()
            ->count();
    }

    public function recentForUser(User $user, int $limit = 5): Collection
    {
        return Notification::query()
            ->forUser((int) $user->id)
            ->latest('notification_id')
            ->limit($limit)
            ->get();
    }

    private function assertOwnership(User $user, Notification $notification): void
    {
        if ((int) $notification->user_id !== (int) $user->id || $user->isPublicUser()) {
            throw ValidationException::withMessages([
                'notification' => 'You are not authorized to access this notification.',
            ]);
        }
    }
}
