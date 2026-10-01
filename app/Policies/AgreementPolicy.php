<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\User;

class AgreementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::AgreementView);
    }

    public function view(User $user, Agreement $agreement): bool
    {
        return $user->hasPermission(Permission::AgreementView);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Agreement $agreement): bool
    {
        return false;
    }

    public function delete(User $user, Agreement $agreement): bool
    {
        // PRD §4 Invariant 4: No physical delete of financial records in normal use
        return false;
    }

    public function activate(User $user, Agreement $agreement): bool
    {
        return $user->hasPermission(Permission::AgreementActivate);
    }
}
