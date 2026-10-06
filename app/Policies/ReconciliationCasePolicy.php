<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ReconciliationCase;
use App\Models\User;

class ReconciliationCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PartnerView)
            || $user->hasPermission(Permission::PaymentStage)
            || $user->isReconciliationReviewer()
            || $user->isProcessOwner();
    }

    public function view(User $user, ReconciliationCase $case): bool
    {
        return $this->viewAny($user);
    }

    public function proposeMatch(User $user, ReconciliationCase $case): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        return $user->hasPermission(Permission::MatchPropose)
            || $user->isOperator()
            || $user->isReconciliationReviewer();
    }

    public function resolve(User $user, ReconciliationCase $case): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        return $user->isReconciliationReviewer()
            || $user->isProcessOwner();
    }

    public function approve(User $user, ReconciliationCase $case): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        return $user->isReconciliationReviewer()
            || $user->isProcessOwner();
    }

    public function delete(User $user, ReconciliationCase $case): bool
    {
        // PRD §4 Invariant 4: No physical deletes of financial and reconciliation records
        return false;
    }
}
