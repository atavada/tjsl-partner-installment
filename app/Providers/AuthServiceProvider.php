<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\ExportJob;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\ReconciliationCase;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Policies\AgreementPolicy;
use App\Policies\ExportJobPolicy;
use App\Policies\FundLotPolicy;
use App\Policies\PartnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReconciliationCasePolicy;
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
        Agreement::class => AgreementPolicy::class,
        BankTransaction::class => PaymentPolicy::class,
        PaymentAllocation::class => PaymentPolicy::class,
        ReconciliationCase::class => ReconciliationCasePolicy::class,
        ExportJob::class => ExportJobPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // System Admin non-bypass for financial integrity (PRD §3, §4 Invariant 4, DEC-009, TASK-REM-005)
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            // Invariant 4: No physical delete of financial/business records in normal use
            if ($ability === 'delete') {
                return false;
            }

            if ($user->isSystemAdmin()) {
                // Financial & Ledger Integrity: System Admin has NO implicit authority over financial
                // transactions, posting, staging, reversal, or fund allocation without explicit cashier permissions.
                $financialAbilities = [
                    'post',
                    'reverse',
                    'createAbt',
                    'identify',
                    'payment.post',
                    'payment.stage',
                    'agreement.activate',
                ];

                if (in_array($ability, $financialAbilities, true)) {
                    return null; // Fall through to policy or gate permission check
                }

                $target = $arguments[0] ?? null;
                $targetClass = is_object($target) ? get_class($target) : (is_string($target) ? $target : null);
                $financialClasses = [
                    BankTransaction::class,
                    PaymentAllocation::class,
                    FundLot::class,
                    Agreement::class,
                ];

                if (in_array($targetClass, $financialClasses, true)) {
                    if (in_array($ability, ['create', 'update', 'post', 'reverse', 'createAbt', 'identify', 'activate'], true)) {
                        return null; // Fall through to policy
                    }
                }

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
