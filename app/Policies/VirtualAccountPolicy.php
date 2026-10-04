<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\VirtualAccount;

class VirtualAccountPolicy
{
    public function view(User $user, VirtualAccount $va): bool
    {
        return $user->hasPermission(Permission::PartnerView);
    }

    /**
     * Reveal VA number: allowed for non-viewer roles by default, denied for Auditor (DEC-004).
     */
    public function revealVaNumber(User $user, VirtualAccount $va): bool
    {
        return $user->hasPermission(Permission::VaReveal);
    }

    public function delete(User $user, VirtualAccount $va): bool
    {
        // PRD §4 Invariant 4: No physical delete of financial records in normal use
        return false;
    }
}
