<?php

namespace App\Policies;

use App\Models\User;

class DashboardAccessPolicy
{
    public function adminTech(User $user): bool
    {
        return $user->isAdmin();
    }

    public function office(User $user): bool
    {
        return $user->isOfficeUser();
    }

    public function procurement(User $user): bool
    {
        return $user->isProcurement();
    }

    public function inventory(User $user): bool
    {
        return $user->isGsoOfficer();
    }

    public function executive(User $user): bool
    {
        return $user->isExecutive();
    }
}
