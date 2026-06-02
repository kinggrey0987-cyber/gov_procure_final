<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\DistributionRecord;
use App\Models\Notification as GovernanceNotification;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyUser(
        User $user,
        string $title,
        string $message,
        string $notificationType,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): GovernanceNotification {
        return GovernanceNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'notification_type' => $notificationType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, GovernanceNotification>
     */
    public function notifyUsers(
        Collection $users,
        string $title,
        string $message,
        string $notificationType,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Collection {
        return $users->map(function (User $user) use (
            $title,
            $message,
            $notificationType,
            $referenceType,
            $referenceId
        ): GovernanceNotification {
            return $this->notifyUser(
                $user,
                $title,
                $message,
                $notificationType,
                $referenceType,
                $referenceId
            );
        });
    }

    /**
     * @return Collection<int, GovernanceNotification>
     */
    public function notifyRole(
        UserRole|string $role,
        string $title,
        string $message,
        string $notificationType,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Collection {
        $roleCode = $role instanceof UserRole ? $role->value : $role;

        $users = User::query()
            ->whereHas('role', fn ($query) => $query->where('code', $roleCode))
            ->get();

        return $this->notifyUsers(
            users: $users,
            title: $title,
            message: $message,
            notificationType: $notificationType,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    public function notifySupplyRequestOfficeRequester(
        SupplyRequest $supplyRequest,
        string $title,
        string $message,
        string $notificationType,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): ?GovernanceNotification {
        $supplyRequest->loadMissing('requester');

        $requester = $supplyRequest->requester;
        if (! $requester instanceof User || ! $this->isOfficeUserForOffice($requester, (int) $supplyRequest->office_id)) {
            return null;
        }

        return $this->notifyUser(
            user: $requester,
            title: $title,
            message: $message,
            notificationType: $notificationType,
            referenceType: $referenceType ?? SupplyRequest::class,
            referenceId: $referenceId ?? (int) $supplyRequest->supply_request_id,
        );
    }

    public function notifyDistributionOfficeRecipient(
        DistributionRecord $record,
        string $title,
        string $message,
        string $notificationType
    ): ?GovernanceNotification {
        $record->loadMissing('receiver', 'supplyRequest.requester');

        $recipient = $record->receiver instanceof User
            && $this->isOfficeUserForOffice($record->receiver, (int) $record->office_id)
                ? $record->receiver
                : null;

        if (! $recipient instanceof User) {
            $requester = $record->supplyRequest?->requester;
            $recipient = $requester instanceof User && $this->isOfficeUserForOffice($requester, (int) $record->office_id)
                ? $requester
                : null;
        }

        if (! $recipient instanceof User) {
            return null;
        }

        return $this->notifyUser(
            user: $recipient,
            title: $title,
            message: $message,
            notificationType: $notificationType,
            referenceType: DistributionRecord::class,
            referenceId: (int) $record->distribution_record_id,
        );
    }

    private function isOfficeUserForOffice(User $user, int $officeId): bool
    {
        return $user->isOfficeUser() && (int) $user->office_id === $officeId;
    }
}
