<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PartnerView);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasPermission(Permission::PartnerView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PartnerCreate);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermission(Permission::PartnerUpdate);
    }

    public function delete(User $user, Partner $partner): bool
    {
        // PRD §4 Invariant 4: No physical delete of financial records in normal use
        return false;
    }

    public function revealNik(User $user, Partner $partner): bool
    {
        return $user->hasPermission(Permission::NikReveal);
    }

    public function revealPhone(User $user, Partner $partner): bool
    {
        return $user->hasPermission(Permission::PhoneReveal);
    }

    public function revealAddress(User $user, Partner $partner): bool
    {
        return $user->hasPermission(Permission::AddressReveal);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission(Permission::SensitiveExport);
    }
}
