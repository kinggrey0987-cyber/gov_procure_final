<?php

namespace App\Policies;

use App\Models\User;

class AnalyticsVisibilityPolicy
{
    public function viewExecutiveAnomalies(User $user): bool
    {
        return $user->isExecutive();
    }

    public function viewGsoForecasting(User $user): bool
    {
        return $user->isGsoOfficer();
    }

    public function viewGsoReorderAlerts(User $user): bool
    {
        return $user->isGsoOfficer();
    }

    public function viewGsoDelayedDelivery(User $user): bool
    {
        return $user->isGsoOfficer();
    }

    public function viewInventoryMonitoring(User $user): bool
    {
        return $user->isGsoOfficer();
    }
}
