<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExportJob;
use App\Models\User;

class ExportJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SensitiveExport);
    }

    public function view(User $user, ExportJob $job): bool
    {
        if (! $user->hasPermission(Permission::SensitiveExport)) {
            return false;
        }

        return $user->id === $job->user_id
            || $user->isReconciliationReviewer()
            || $user->isProcessOwner();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::SensitiveExport);
    }

    public function download(User $user, ExportJob $job): bool
    {
        return $this->view($user, $job);
    }
}
