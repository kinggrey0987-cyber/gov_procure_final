<?php

namespace App\Services;

use App\Models\Notification as GovernanceNotification;
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
}
