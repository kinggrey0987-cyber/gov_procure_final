<?php

namespace App\Policies;

use App\Models\User;

class OfficeScopedAccessPolicy
{
    public function forOfficeId(User $user, int $officeId): bool
    {
        return $user->office_id === $officeId;
    }
}
