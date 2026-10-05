<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ScheduleIntegrityException;
use App\Models\Agreement;
use App\Models\InstallmentSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Deterministic schedule generator with EDATE calendar math and integer rounding.
 *
 * Implements DEC-008, TASK-REM-006, and docs/formula-specification.md §3, §4 (Schedule and Due Dates).
 * - EDATE calendar arithmetic avoids month-end drift (Jan 31 -> Feb 28/29 -> Mar 31).
 * - Exact integer rounding with remainder placed on final installment for 0 IDR difference.
 * - Enforces DB check constraints and non-negative dues.
 */
class ScheduleGeneratorService
{
    public const DEFAULT_TENOR = 24;

    /**
     * Compute date shifted by specified number of months from anchor date using EDATE rules.
     *
     * Clamps day of month to target month's maximum days without month-end drift.
     */
    public static function edate(CarbonInterface|string $anchorDate, int $months): CarbonImmutable
    {
        $anchor = $anchorDate instanceof CarbonInterface
            ? CarbonImmutable::instance($anchorDate)
            : CarbonImmutable::parse($anchorDate);

        if ($months === 0) {
            return $anchor->startOfDay();
        }

        $anchorDay = (int) $anchor->format('j');
        $anchorMonth = (int) $anchor->format('n');
        $anchorYear = (int) $anchor->format('Y');

        $totalMonths = ($anchorYear * 12) + ($anchorMonth - 1) + $months;
        $targetYear = intdiv($totalMonths, 12);
        $targetMonth = ($totalMonths % 12) + 1;

        if ($targetMonth <= 0) {
            $targetMonth += 12;
            $targetYear--;
        }

        $daysInTargetMonth = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
        $targetDay = min($anchorDay, $daysInTargetMonth);

        return CarbonImmutable::create($targetYear, $targetMonth, $targetDay, 0, 0, 0);
    }

    /**
     * Calculate monthly schedule rows with integer division and remainder on final installment.
     *
     * @return list<array{
     *     installment_number: int,
     *     due_date: string,
     *     principal_due: int,
     *     interest_due: int,
     *     admin_charge_due: int,
     *     other_charge_due: int,
     *     total_due: int
     * }>
     *
     * @throws InvalidArgumentException
     */
    public function calculateSchedules(
        int $principal,
        int $interest,
        int $adminCharge,
        int $otherCharge,
        int $tenor,
        CarbonInterface|string $firstDueDate
    ): array {
        if ($tenor < 1) {
            throw new InvalidArgumentException("Tenor months must be at least 1, {$tenor} given.");
        }

        if ($principal < 0 || $interest < 0 || $adminCharge < 0 || $otherCharge < 0) {
            throw new InvalidArgumentException('Financial components must be non-negative.');
        }

        $monthlyPrincipal = intdiv($principal, $tenor);
        $finalPrincipal = $principal - ($monthlyPrincipal * ($tenor - 1));

        $monthlyInterest = intdiv($interest, $tenor);
        $finalInterest = $interest - ($monthlyInterest * ($tenor - 1));

        $monthlyAdmin = intdiv($adminCharge, $tenor);
        $finalAdmin = $adminCharge - ($monthlyAdmin * ($tenor - 1));

        $monthlyOther = intdiv($otherCharge, $tenor);
        $finalOther = $otherCharge - ($monthlyOther * ($tenor - 1));

        $schedules = [];

        for ($n = 1; $n <= $tenor; $n++) {
            $isFinal = ($n === $tenor);

            $pDue = $isFinal ? $finalPrincipal : $monthlyPrincipal;
            $iDue = $isFinal ? $finalInterest : $monthlyInterest;
            $aDue = $isFinal ? $finalAdmin : $monthlyAdmin;
            $oDue = $isFinal ? $finalOther : $monthlyOther;
            $totalDue = $pDue + $iDue + $aDue + $oDue;

            $dueDate = self::edate($firstDueDate, $n - 1)->toDateString();

            $schedules[] = [
                'installment_number' => $n,
                'due_date' => $dueDate,
                'principal_due' => $pDue,
                'interest_due' => $iDue,
                'admin_charge_due' => $aDue,
                'other_charge_due' => $oDue,
                'total_due' => $totalDue,
            ];
        }

        return $schedules;
    }

    /**
     * Generate installment schedules for an agreement, optionally persisting to database.
     *
     * @return Collection<int, InstallmentSchedule>
     *
     * @throws ScheduleIntegrityException If persisting over schedules that have received payments.
     */
    public function generateForAgreement(Agreement $agreement, bool $persist = false): Collection
    {
        $principal = (int) ($agreement->principal_amount ?? 0);
        $interest = (int) ($agreement->interest_amount ?? 0);
        $admin = (int) ($agreement->admin_charge_amount ?? 0);
        $other = (int) ($agreement->other_charge_amount ?? 0);
        $tenor = (int) ($agreement->tenor_months ?? self::DEFAULT_TENOR);

        $firstDueDate = $agreement->first_due_date
            ?? $agreement->effective_date
            ?? $agreement->contract_date
            ?? $agreement->application_date
            ?? Carbon::now();

        $rows = $this->calculateSchedules($principal, $interest, $admin, $other, $tenor, $firstDueDate);

        if (! $persist) {
            return collect($rows)->map(fn (array $row): InstallmentSchedule => new InstallmentSchedule([
                'agreement_id' => $agreement->id,
                'installment_number' => $row['installment_number'],
                'due_date' => $row['due_date'],
                'principal_due' => $row['principal_due'],
                'interest_due' => $row['interest_due'],
                'admin_charge_due' => $row['admin_charge_due'],
                'other_charge_due' => $row['other_charge_due'],
                'total_due' => $row['total_due'],
                'principal_paid' => 0,
                'interest_paid' => 0,
                'admin_charge_paid' => 0,
                'other_charge_paid' => 0,
                'total_paid' => 0,
                'status' => 'pending',
                'is_calculated' => true,
                'version' => 1,
            ]));
        }

        return DB::transaction(function () use ($agreement, $rows): Collection {
            $existingWithPayments = InstallmentSchedule::query()
                ->where('agreement_id', $agreement->id)
                ->where('total_paid', '>', 0)
                ->exists();

            if ($existingWithPayments) {
                throw new ScheduleIntegrityException(
                    "Cannot regenerate schedule for agreement {$agreement->id}: installments with existing payments detected."
                );
            }

            // Remove existing unpaid schedules if regenerating
            InstallmentSchedule::query()
                ->where('agreement_id', $agreement->id)
                ->delete();

            $created = collect();

            foreach ($rows as $row) {
                $created->push(InstallmentSchedule::create([
                    'agreement_id' => $agreement->id,
                    'installment_number' => $row['installment_number'],
                    'due_date' => $row['due_date'],
                    'principal_due' => $row['principal_due'],
                    'interest_due' => $row['interest_due'],
                    'admin_charge_due' => $row['admin_charge_due'],
                    'other_charge_due' => $row['other_charge_due'],
                    'total_due' => $row['total_due'],
                    'principal_paid' => 0,
                    'interest_paid' => 0,
                    'admin_charge_paid' => 0,
                    'other_charge_paid' => 0,
                    'total_paid' => 0,
                    'status' => 'pending',
                    'is_calculated' => true,
                    'version' => 1,
                ]));
            }

            return $created;
        });
    }
}
