<?php

namespace App\Policies;

use App\Models\User;

class PublicDisclosurePolicy
{
    /**
     * Public disclosures are visible to everyone, but only after publication.
     * Record-level publish-state checks should be applied when disclosure models are introduced.
     */
    public function viewPublished(?User $user = null): bool
    {
        return true;
    }
}
