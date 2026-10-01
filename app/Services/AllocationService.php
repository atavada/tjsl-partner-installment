<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\Overpayment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AllocationService
{
    /**
     * Compute remaining unallocated capacity on a bank transaction.
     */
    public function computeRemainingCapacity(BankTransaction $transaction): int
    {
        $allocated = (int) PaymentAllocation::where('bank_transaction_id', $transaction->id)
            ->where('state', '!=', PaymentState::Reversed->value)
            ->sum('total_amount');

        $unapplied = (int) Overpayment::where('bank_transaction_id', $transaction->id)
            ->sum('unapplied_amount');

        return max(0, $transaction->amount - $allocated - $unapplied);
    }

    /**
     * Propose an allocation of funds from a bank transaction to an agreement.
     *
     * @param  array{
     *     principal_amount: int,
     *     interest_amount: int,
     *     admin_charge_amount: int,
     *     other_charge_amount: int,
     *     effective_date: string,
     *     period?: string|null,
     *     evidence?: string|null,
     *     idempotency_key?: string|null,
     * }  $components
     */
    public function proposeAllocation(
        BankTransaction $transaction,
        Agreement $agreement,
        array $components,
        ?User $actor = null
    ): PaymentAllocation {
        $principal = (int) ($components['principal_amount'] ?? 0);
        $interest = (int) ($components['interest_amount'] ?? 0);
        $admin = (int) ($components['admin_charge_amount'] ?? 0);
        $other = (int) ($components['other_charge_amount'] ?? 0);
        $total = $principal + $interest + $admin + $other;

        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation component sum must be strictly positive.');
        }

        $remaining = $this->computeRemainingCapacity($transaction);
        if ($total > $remaining) {
            throw new InvalidArgumentException(
                "Allocation amount ({$total}) exceeds remaining transaction capacity ({$remaining})."
            );
        }

        $idempotencyKey = $components['idempotency_key'] ?? (string) Str::uuid();

        return PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'admin_charge_amount' => $admin,
            'other_charge_amount' => $other,
            'total_amount' => $total,
            'effective_date' => $components['effective_date'],
            'period' => $components['period'] ?? null,
            'state' => PaymentState::Draft,
            'evidence' => $components['evidence'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'version' => 1,
        ]);
    }
}
