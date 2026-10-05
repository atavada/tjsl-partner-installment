<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentStage)
            || $user->hasPermission(Permission::PaymentPost);
    }

    public function view(User $user, BankTransaction|PaymentAllocation|null $model = null): bool
    {
        return $user->hasPermission(Permission::PaymentStage)
            || $user->hasPermission(Permission::PaymentPost);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentStage);
    }

    public function reverse(User $user, BankTransaction|PaymentAllocation|null $model = null): bool
    {
        return $user->hasPermission(Permission::PaymentPost);
    }

    public function post(User $user, BankTransaction|PaymentAllocation|null $model = null): bool
    {
        return $user->hasPermission(Permission::PaymentPost);
    }
}
