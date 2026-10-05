<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentState;
use App\Models\AllocationInstallmentLine;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentReversalService
{
    public function __construct(
        protected ?AuditService $auditService = null,
    ) {
        $this->auditService ??= app(AuditService::class);
    }

    /**
     * Create a compensating reversal entry for a payment allocation.
     * Preserves original record and audit trail (PRD §4 invariant 4).
     */
    public function reverse(PaymentAllocation $allocation, string $reason, User $actor): PaymentAllocation
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reason is mandatory for payment reversal.');
        }

        if ($allocation->reversal_of_id !== null) {
            throw new InvalidArgumentException('Cannot reverse a compensating reversal entry.');
        }

        if ($allocation->state === PaymentState::Reversed) {
            throw new InvalidArgumentException('Allocation is already reversed.');
        }

        if (PaymentAllocation::where('reversal_of_id', $allocation->id)->exists()) {
            throw new InvalidArgumentException('Allocation has already been reversed.');
        }

        return DB::transaction(function () use ($allocation, $reason, $actor): PaymentAllocation {
            // Reopen installment schedules if allocation was posted and has lines
            $lines = AllocationInstallmentLine::where('payment_allocation_id', $allocation->id)->get();
            foreach ($lines as $line) {
                /** @var InstallmentSchedule|null $schedule */
                $schedule = InstallmentSchedule::lockForUpdate()->find($line->installment_schedule_id);
                if ($schedule !== null) {
                    $schedule->principal_paid = max(0, (int) $schedule->principal_paid - (int) $line->principal_amount);
                    $schedule->interest_paid = max(0, (int) $schedule->interest_paid - (int) $line->interest_amount);
                    $schedule->admin_charge_paid = max(0, (int) $schedule->admin_charge_paid - (int) $line->admin_charge_amount);
                    $schedule->other_charge_paid = max(0, (int) $schedule->other_charge_paid - (int) $line->other_charge_amount);
                    $schedule->total_paid = max(0, (int) $schedule->total_paid - (int) $line->total_amount);

                    $schedule->status = $schedule->total_paid >= $schedule->total_due
                        ? 'paid'
                        : ($schedule->total_paid > 0 ? 'partially_paid' : 'pending');

                    $schedule->version = (int) $schedule->version + 1;
                    $schedule->save();
                }
            }

            // Update original allocation state to reversed (releases allocated capacity)
            $allocation->update([
                'state' => PaymentState::Reversed,
                'reason' => $reason,
                'version' => (int) $allocation->version + 1,
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
            $this->auditService?->logReversal($reversal, $allocation, $reason, $actor);

            return $reversal;
        });
    }
}
