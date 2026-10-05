<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollectibilityStatus;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\AllocationInstallmentLine;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dynamic collectibility calculation engine as of evaluation date.
 *
 * Implements DEC-007 (RESOLVED 2026-10-03), TASK-REM-006, and docs/formula-specification.md §9.
 * Metric reference: Collectibility Label v1.
 *
 * Rules:
 * - Data verification guard: unverified agreement data NEVER returns Lunas (always Unknown).
 * - Draft guard: draft agreements create no debt (PRD FR-02), return Unknown.
 * - Lunas override: verified agreements with remaining balance 0 return Lunas.
 * - Delinquency: calculated from oldest unpaid installment due date relative to as_of date.
 * - DEC-007 bands:
 *   - 0–1 month: Lancar
 *   - 2–6 months: Kurang Lancar
 *   - 7–9 months: Diragukan
 *   - >9 months: Bermasalah
 */
class CollectibilityCalculationService
{
    public function __construct(
        protected BalanceService $balanceService
    ) {}

    /**
     * Calculate dynamic collectibility status and delinquency metrics as of a given date.
     *
     * @return array{
     *     agreement_id: string,
     *     collectibility_status: CollectibilityStatus,
     *     status: string,
     *     label: string,
     *     late_months: int,
     *     as_of: string,
     *     rule_version: string,
     *     data_verified: bool,
     *     is_lunas: bool,
     *     remaining_balance: int|string,
     *     qualifying_installment: array{
     *         id: string,
     *         installment_number: int,
     *         due_date: string,
     *         total_due: int,
     *         total_paid: int,
     *         outstanding: int
     *     }|null,
     *     warnings: list<string>
     * }
     */
    public function calculate(
        Agreement $agreement,
        ?CarbonInterface $asOf = null,
        string $ruleVersion = CollectibilityStatus::ACTIVE_RULE_VERSION,
        ?bool $dataVerified = null
    ): array {
        $evaluationDate = $asOf ?? Carbon::now();
        $asOfIso = $evaluationDate->toIso8601String();
        $asOfDate = $evaluationDate->toDateString();

        $balance = $this->balanceService->getBalance(
            $agreement,
            $evaluationDate,
            null,
            $dataVerified
        );

        $isDraft = $balance['is_draft'];
        $isDataVerified = $balance['data_verified'];
        $isLunas = $balance['is_lunas'];
        $remainingTotal = is_numeric($balance['total_remaining']) ? (int) $balance['total_remaining'] : null;
        $hasIntegrityError = ($balance['status'] === 'exception');

        // Rule 1: Draft agreements do not create debt (PRD FR-02)
        if ($isDraft) {
            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => CollectibilityStatus::Unknown,
                'status' => CollectibilityStatus::Unknown->value,
                'label' => CollectibilityStatus::Unknown->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => false,
                'is_lunas' => false,
                'remaining_balance' => 'unverified',
                'qualifying_installment' => null,
                'warnings' => ['Perjanjian draft belum aktif dan tidak menimbulkan kewajiban piutang (PRD FR-02).'],
            ];
        }

        // Rule 2: Unverified data MUST NEVER return Lunas (formula-specification §9.4, DEC-007)
        if (! $isDataVerified) {
            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => CollectibilityStatus::Unknown,
                'status' => CollectibilityStatus::Unknown->value,
                'label' => CollectibilityStatus::Unknown->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => false,
                'is_lunas' => false,
                'remaining_balance' => $remainingTotal ?? 'unverified',
                'qualifying_installment' => null,
                'warnings' => array_values(array_unique(array_merge(
                    $balance['warnings'],
                    ['Data perjanjian belum terverifikasi (DEC-007 / DEC-008).']
                ))),
            ];
        }

        // Rule 3: LUNAS override when verified and remaining balance is zero
        if ($isLunas || ($isDataVerified && ! $hasIntegrityError && $remainingTotal === 0)) {
            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => CollectibilityStatus::Lunas,
                'status' => CollectibilityStatus::Lunas->value,
                'label' => CollectibilityStatus::Lunas->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => true,
                'is_lunas' => true,
                'remaining_balance' => 0,
                'qualifying_installment' => null,
                'warnings' => $balance['warnings'],
            ];
        }

        // Rule 4: Delinquency evaluation from installment schedules
        $schedules = InstallmentSchedule::query()
            ->where('agreement_id', $agreement->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('due_date', 'asc')
            ->orderBy('installment_number', 'asc')
            ->get();

        if ($schedules->isEmpty()) {
            $fallbackStatus = $agreement->collectibility_status ?? CollectibilityStatus::Unknown;

            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => $fallbackStatus,
                'status' => $fallbackStatus->value,
                'label' => $fallbackStatus->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => $isDataVerified,
                'is_lunas' => false,
                'remaining_balance' => $remainingTotal ?? 0,
                'qualifying_installment' => null,
                'warnings' => ['Tidak ada baris jadwal angsuran ditemukan; menggunakan status tersimpan.'],
            ];
        }

        // Query allocation lines effective on or before asOfDate
        $validAllocationIds = PaymentAllocation::query()
            ->where('agreement_id', $agreement->id)
            ->whereNull('reversal_of_id')
            ->where('effective_date', '<=', $asOfDate)
            ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value])
            ->whereDoesntHave('reversals', function ($query) use ($asOfDate) {
                $query->where('effective_date', '<=', $asOfDate)
                    ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value]);
            })
            ->pluck('id');

        $hasAllocations = $validAllocationIds->isNotEmpty();

        $linesBySchedule = $hasAllocations
            ? AllocationInstallmentLine::query()
                ->whereIn('payment_allocation_id', $validAllocationIds)
                ->groupBy('installment_schedule_id')
                ->selectRaw('installment_schedule_id, SUM(total_amount) as total_paid')
                ->pluck('total_paid', 'installment_schedule_id')
            : collect();

        $dueSchedules = $schedules->filter(fn (InstallmentSchedule $s): bool => (string) $s->due_date <= $asOfDate);

        if ($dueSchedules->isEmpty()) {
            // First installment is not yet due
            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => CollectibilityStatus::Lancar,
                'status' => CollectibilityStatus::Lancar->value,
                'label' => CollectibilityStatus::Lancar->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => true,
                'is_lunas' => false,
                'remaining_balance' => $remainingTotal ?? 0,
                'qualifying_installment' => null,
                'warnings' => $balance['warnings'],
            ];
        }

        // Identify oldest unpaid or partially paid installment
        $oldestUnpaid = null;
        $oldestUnpaidOutstanding = 0;

        foreach ($dueSchedules as $dueSchedule) {
            $paidAmount = $hasAllocations
                ? (int) ($linesBySchedule[$dueSchedule->id] ?? 0)
                : (int) $dueSchedule->total_paid;

            $outstanding = (int) $dueSchedule->total_due - $paidAmount;

            if ($outstanding > 0) {
                $oldestUnpaid = $dueSchedule;
                $oldestUnpaidOutstanding = $outstanding;
                break;
            }
        }

        if ($oldestUnpaid === null) {
            // All installments due by as-of date are fully paid
            return [
                'agreement_id' => (string) $agreement->id,
                'collectibility_status' => CollectibilityStatus::Lancar,
                'status' => CollectibilityStatus::Lancar->value,
                'label' => CollectibilityStatus::Lancar->label(),
                'late_months' => 0,
                'as_of' => $asOfIso,
                'rule_version' => $ruleVersion,
                'data_verified' => true,
                'is_lunas' => false,
                'remaining_balance' => $remainingTotal ?? 0,
                'qualifying_installment' => null,
                'warnings' => $balance['warnings'],
            ];
        }

        // Calculate elapsed calendar months from oldest unpaid due date to as-of date
        $oldestDueDate = Carbon::parse($oldestUnpaid->due_date)->startOfDay();
        $evalDate = $evaluationDate->copy()->startOfDay();

        $lateMonths = 0;
        while (ScheduleGeneratorService::edate($oldestDueDate, $lateMonths + 1)->lte($evalDate)) {
            $lateMonths++;
        }

        $status = CollectibilityStatus::fromBalance(
            $remainingTotal ?? $oldestUnpaidOutstanding,
            $isDataVerified,
            $lateMonths,
            $ruleVersion
        );

        $paidSoFar = (int) $oldestUnpaid->total_due - $oldestUnpaidOutstanding;

        return [
            'agreement_id' => (string) $agreement->id,
            'collectibility_status' => $status,
            'status' => $status->value,
            'label' => $status->label(),
            'late_months' => $lateMonths,
            'as_of' => $asOfIso,
            'rule_version' => $ruleVersion,
            'data_verified' => true,
            'is_lunas' => false,
            'remaining_balance' => $remainingTotal ?? $oldestUnpaidOutstanding,
            'qualifying_installment' => [
                'id' => (string) $oldestUnpaid->id,
                'installment_number' => (int) $oldestUnpaid->installment_number,
                'due_date' => (string) $oldestUnpaid->due_date,
                'total_due' => (int) $oldestUnpaid->total_due,
                'total_paid' => $paidSoFar,
                'outstanding' => $oldestUnpaidOutstanding,
            ],
            'warnings' => $balance['warnings'],
        ];
    }

    /**
     * Calculate dynamic collectibility and synchronize the agreement model's stored column.
     */
    public function sync(Agreement $agreement, ?CarbonInterface $asOf = null): CollectibilityStatus
    {
        $result = $this->calculate($agreement, $asOf);
        $status = $result['collectibility_status'];

        if ($agreement->collectibility_status !== $status) {
            $agreement->update(['collectibility_status' => $status]);
        }

        return $status;
    }
}
