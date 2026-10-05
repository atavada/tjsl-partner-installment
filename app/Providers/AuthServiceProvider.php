<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Policies\FundLotPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\VirtualAccountPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Partner::class => PartnerPolicy::class,
        VirtualAccount::class => VirtualAccountPolicy::class,
        FundLot::class => FundLotPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // System Admin superadmin bypass: can perform any gate or policy check
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSystemAdmin()) {
                return true;
            }

            return null;
        });

        // Register Gates for all DEC-004 and DEC-009 permissions
        // Deny-by-default for non-admin: evaluates explicit permissions only
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user) use ($permission): bool {
                return $user->hasPermission($permission);
            });
        }
    }
}
