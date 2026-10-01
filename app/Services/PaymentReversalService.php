<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentState;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentReversalService
{
    /**
     * Create a compensating reversal entry for a payment allocation.
     * Preserves original record and audit trail (PRD §4 invariant 4).
     */
    public function reverse(PaymentAllocation $allocation, string $reason, User $actor): PaymentAllocation
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reason is mandatory for payment reversal.');
        }

        if ($allocation->state === PaymentState::Reversed) {
            throw new InvalidArgumentException('Allocation is already reversed.');
        }

        if ($allocation->reversal_of_id !== null) {
            throw new InvalidArgumentException('Cannot reverse a compensating reversal entry.');
        }

        return DB::transaction(function () use ($allocation, $reason, $actor): PaymentAllocation {
            // Update original allocation state to reversed (releases allocated capacity)
            $allocation->update([
                'state' => PaymentState::Reversed,
                'reason' => $reason,
            ]);

            // Create compensating allocation linked via reversal_of_id
            $reversal = PaymentAllocation::create([
                'bank_transaction_id' => $allocation->bank_transaction_id,
                'agreement_id' => $allocation->agreement_id,
                'principal_amount' => $allocation->principal_amount,
                'interest_amount' => $allocation->interest_amount,
                'admin_charge_amount' => $allocation->admin_charge_amount,
                'other_charge_amount' => $allocation->other_charge_amount,
                'total_amount' => $allocation->total_amount,
                'effective_date' => $allocation->effective_date,
                'period' => $allocation->period,
                'state' => PaymentState::Reversed,
                'reversal_of_id' => $allocation->id,
                'reason' => $reason,
                'evidence' => $allocation->evidence,
                'idempotency_key' => (string) Str::uuid(),
                'approved_by_id' => $actor->id,
                'approved_at' => now(),
                'version' => 1,
            ]);

            // Emit reversal audit event linking to original allocation (PRD §4 invariant 4, FR-06)
            if (app()->bound(AuditService::class)) {
                app(AuditService::class)->logReversal($reversal, $allocation, $reason, $actor);
            }

            return $reversal;
        });
    }
}
