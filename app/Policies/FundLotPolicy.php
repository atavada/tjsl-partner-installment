<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\FundLotType;
use App\Enums\Permission;
use App\Models\FundLot;
use App\Models\User;

class FundLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PartnerView)
            || $user->hasPermission(Permission::PaymentStage);
    }

    public function view(User $user, FundLot $fundLot): bool
    {
        return $user->hasPermission(Permission::PartnerView)
            || $user->hasPermission(Permission::PaymentStage);
    }

    public function createAbt(User $user): bool
    {
        return $user->isOperator()
            || $user->hasPermission(Permission::PaymentStage);
    }

    public function identify(User $user, FundLot $fundLot): bool
    {
        return $user->isOperator()
            || $user->hasPermission(Permission::PaymentStage);
    }

    public function allocate(User $user, FundLot $fundLot): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        if ($fundLot->lot_type !== FundLotType::IdentifiedUnallocated) {
            return false;
        }

        return $user->isOperator()
            || $user->hasPermission(Permission::PaymentStage)
            || $user->hasPermission(Permission::PaymentPost);
    }

    public function delete(User $user, FundLot $fundLot): bool
    {
        // PRD §4 Invariant 4 & DEC-006: Fund lots cannot be deleted
        return false;
    }
}
