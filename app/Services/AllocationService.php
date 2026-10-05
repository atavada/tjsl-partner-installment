<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\AllocationLine;
use App\Data\AllocationResult;
use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\AllocationInstallmentLine;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for allocating payment amounts across installment schedules.
 *
 * Implements DEC-008 (RESOLVED IN PART 2026-10-03) and docs/formula-specification.md §6.
 *
 * Rules:
 * - Admin/bunga first, then principal: alloc_C = MIN(A, out_C), alloc_P = MIN(A - alloc_C, out_P) (DP-1 DEFAULT_ACTIVE).
 * - Priority order stored as data constant, not hardcoded if/else (DP-1).
 * - Across installments: Oldest due date first (DP-6 DEFAULT_ACTIVE).
 * - Leftover after all installments becomes excess / Overpayment record (docs/formula-specification.md §7, FIMPL-008).
 * - Records allocation-to-installment links via AllocationInstallmentLine.
 *
 * Metric reference: Allocation Order v1 (docs/metric-definitions.md).
 */
class AllocationService
{
    /**
     * Component allocation priority order per DP-1 (DEFAULT_ACTIVE).
     * Admin/bunga first, then other charges, then principal (pokok).
     * Stored as data, not hardcoded logic.
     *
     * @var list<string>
     */
    public const COMPONENT_PRIORITY = [
        'admin_charge',
        'interest',
        'other_charge',
        'principal',
    ];

    /**
     * Allocate payment amount across agreement installment schedules.
     *
     * Implements DEC-008 §6 (Allocation Order v1, DP-1, DP-6).
     *
     * @throws InvalidArgumentException When allocation total is zero or negative.
     */
    public function allocate(
        PaymentAllocation $allocation,
        Agreement $agreement,
        ?CarbonInterface $asOf = null
    ): AllocationResult {
        $totalPayment = (int) $allocation->total_amount;

        if ($totalPayment <= 0) {
            throw new InvalidArgumentException('Payment allocation amount must be strictly positive (zero and negative rejection).');
        }

        $evaluationDate = $asOf ?? ($allocation->effective_date ? Carbon::parse($allocation->effective_date) : Carbon::now());

        // Load installments ordered by oldest due date first (DP-6)
        $schedules = InstallmentSchedule::query()
            ->where('agreement_id', $agreement->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('due_date', 'asc')
            ->orderBy('installment_number', 'asc')
            ->get();

        $remainingPayment = $totalPayment;
        /** @var Collection<int, AllocationLine> $lines */
        $lines = collect();

        $totalPrincipal = 0;
        $totalInterest = 0;
        $totalAdmin = 0;
        $totalOther = 0;

        foreach ($schedules as $schedule) {
            if ($remainingPayment <= 0) {
                break;
            }

            // Calculate current paid amounts, accounting for reversals
            $paidComponents = $this->calculatePaidComponents($schedule, $allocation);

            $outMap = [
                'admin_charge' => max(0, (int) $schedule->admin_charge_due - $paidComponents['admin_charge']),
                'interest' => max(0, (int) $schedule->interest_due - $paidComponents['interest']),
                'other_charge' => max(0, (int) $schedule->other_charge_due - $paidComponents['other_charge']),
                'principal' => max(0, (int) $schedule->principal_due - $paidComponents['principal']),
            ];

            $totalOut = array_sum($outMap);
            if ($totalOut <= 0) {
                continue;
            }

            $allocatedForSchedule = [
                'admin_charge' => 0,
                'interest' => 0,
                'other_charge' => 0,
                'principal' => 0,
            ];

            // Apply allocation per priority order (DP-1)
            foreach (self::COMPONENT_PRIORITY as $component) {
                $needed = $outMap[$component];
                if ($needed <= 0) {
                    continue;
                }

                $alloc = min($remainingPayment, $needed);
                $allocatedForSchedule[$component] = $alloc;
                $remainingPayment -= $alloc;

                if ($remainingPayment <= 0) {
                    break;
                }
            }

            $scheduleAllocTotal = array_sum($allocatedForSchedule);
            if ($scheduleAllocTotal > 0) {
                $lines->push(new AllocationLine(
                    installmentScheduleId: (string) $schedule->id,
                    principalAllocated: $allocatedForSchedule['principal'],
                    interestAllocated: $allocatedForSchedule['interest'],
                    adminChargeAllocated: $allocatedForSchedule['admin_charge'],
                    otherChargeAllocated: $allocatedForSchedule['other_charge'],
                    totalAllocated: $scheduleAllocTotal,
                ));

                $totalPrincipal += $allocatedForSchedule['principal'];
                $totalInterest += $allocatedForSchedule['interest'];
                $totalAdmin += $allocatedForSchedule['admin_charge'];
                $totalOther += $allocatedForSchedule['other_charge'];
            }
        }

        $totalAllocated = $totalPrincipal + $totalInterest + $totalAdmin + $totalOther;
        $excessAmount = $remainingPayment;

        // Persist allocation lines and update schedules if allocation model is persisted
        if ($allocation->exists) {
            DB::transaction(function () use (
                $allocation,
                $agreement,
                $lines,
                $totalPrincipal,
                $totalInterest,
                $totalAdmin,
                $totalOther,
                $totalAllocated,
                $excessAmount
            ): void {
                // Remove any pre-existing lines for this allocation (idempotency)
                AllocationInstallmentLine::where('payment_allocation_id', $allocation->id)->delete();

                foreach ($lines as $line) {
                    AllocationInstallmentLine::create([
                        'payment_allocation_id' => $allocation->id,
                        'installment_schedule_id' => $line->installmentScheduleId,
                        'principal_amount' => $line->principalAllocated,
                        'interest_amount' => $line->interestAllocated,
                        'admin_charge_amount' => $line->adminChargeAllocated,
                        'other_charge_amount' => $line->otherChargeAllocated,
                        'total_amount' => $line->totalAllocated,
                    ]);

                    /** @var InstallmentSchedule|null $schedule */
                    $schedule = InstallmentSchedule::find($line->installmentScheduleId);
                    if ($schedule !== null) {
                        $schedule->admin_charge_paid = (int) $schedule->admin_charge_paid + $line->adminChargeAllocated;
                        $schedule->interest_paid = (int) $schedule->interest_paid + $line->interestAllocated;
                        $schedule->other_charge_paid = (int) $schedule->other_charge_paid + $line->otherChargeAllocated;
                        $schedule->principal_paid = (int) $schedule->principal_paid + $line->principalAllocated;
                        $schedule->total_paid = (int) $schedule->total_paid + $line->totalAllocated;
                        $schedule->status = $schedule->total_paid >= $schedule->total_due ? 'paid' : ($schedule->total_paid > 0 ? 'partially_paid' : 'pending');
                        $schedule->save();
                    }
                }

                // If excess exists, update allocation amounts to actually allocated components
                // and store leftover as true excess FundLot record (DEC-006, DEC-008 §6, §7, FIMPL-008)
                if ($excessAmount > 0) {
                    $allocation->update([
                        'principal_amount' => $totalPrincipal,
                        'interest_amount' => $totalInterest,
                        'admin_charge_amount' => $totalAdmin,
                        'other_charge_amount' => $totalOther,
                        'total_amount' => $totalAllocated,
                    ]);

                    if ($allocation->bank_transaction_id !== null) {
                        FundLot::create([
                            'bank_transaction_id' => $allocation->bank_transaction_id,
                            'partner_id' => $agreement->partner_id,
                            'source_agreement_id' => $agreement->id,
                            'lot_type' => FundLotType::Excess,
                            'amount' => $excessAmount,
                            'evidence' => $allocation->evidence,
                            'idempotency_key' => (string) Str::uuid(),
                            'version' => 1,
                        ]);
                    }
                } else {
                    // Auto-correct allocation components to match calculated amounts (formula spec §6)
                    $allocation->update([
                        'principal_amount' => $totalPrincipal,
                        'interest_amount' => $totalInterest,
                        'admin_charge_amount' => $totalAdmin,
                        'other_charge_amount' => $totalOther,
                        'total_amount' => $totalAllocated,
                    ]);
                }
            });
        }

        return new AllocationResult(
            lines: $lines,
            totalAllocated: $totalAllocated,
            excessAmount: $excessAmount,
            principalAllocated: $totalPrincipal,
            interestAllocated: $totalInterest,
            adminChargeAllocated: $totalAdmin,
            otherChargeAllocated: $totalOther,
        );
    }

    /**
     * Calculate current paid components for an installment schedule,
     * ignoring reversed allocations.
     *
     * @return array{admin_charge: int, interest: int, other_charge: int, principal: int}
     */
    private function calculatePaidComponents(InstallmentSchedule $schedule, PaymentAllocation $currentAllocation): array
    {
        $hasLines = AllocationInstallmentLine::where('installment_schedule_id', $schedule->id)->exists();

        if (! $hasLines) {
            return [
                'admin_charge' => (int) $schedule->admin_charge_paid,
                'interest' => (int) $schedule->interest_paid,
                'other_charge' => (int) $schedule->other_charge_paid,
                'principal' => (int) $schedule->principal_paid,
            ];
        }

        // Query lines from active (non-reversed) allocations
        $activeLines = AllocationInstallmentLine::query()
            ->where('installment_schedule_id', $schedule->id)
            ->whereHas('paymentAllocation', function ($query) use ($currentAllocation): void {
                $query->where('id', '!=', $currentAllocation->id ?? '')
                    ->where('state', '!=', PaymentState::Reversed->value)
                    ->whereNull('reversal_of_id')
                    ->whereDoesntHave('reversals');
            })
            ->get();

        return [
            'admin_charge' => (int) $activeLines->sum('admin_charge_amount'),
            'interest' => (int) $activeLines->sum('interest_amount'),
            'other_charge' => (int) $activeLines->sum('other_charge_amount'),
            'principal' => (int) $activeLines->sum('principal_amount'),
        ];
    }

    /**
     * Compute remaining unallocated capacity on a bank transaction.
     */
    public function computeRemainingCapacity(BankTransaction $transaction): int
    {
        $allocated = (int) PaymentAllocation::where('bank_transaction_id', $transaction->id)
            ->where('state', '!=', PaymentState::Reversed->value)
            ->sum('total_amount');

        $unapplied = (int) FundLot::where('bank_transaction_id', $transaction->id)
            ->sum('amount');

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
