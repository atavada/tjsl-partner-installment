<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\ReceivableAdjustment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class BalanceService
{
    public const RULE_VERSION = 'DEC-008-v1';

    /**
     * Return balance components for an agreement as of a given date.
     *
     * Implements DEC-008 (RESOLVED IN PART 2026-10-03) and docs/formula-specification.md §3.
     * Metric references:
     * - Remaining Principal v1: remaining_P = P_contract + adj_P - paid_P
     * - Remaining Charge v1: remaining_C = C_contract + adj_C - paid_C
     * - Total Remaining Balance v1: remaining = remaining_P + remaining_C
     * - LUNAS v1: is_lunas = data_verified AND remaining == 0 AND remaining_P == 0 AND remaining_C == 0
     *
     * Invariants (PRD §4, DEC-008):
     * - Money is integer Rupiah (BIGINT). No floats.
     * - Negative remaining values surface as an explicit 'exception' status, never floored to 0.
     * - Reversals are separately dated events; an as-of query before a reversal includes the payment.
     * - Draft agreements do not create debt (PRD FR-02).
     *
     * @return array{
     *     agreement_id: string,
     *     status: string,
     *     label: string,
     *     as_of: string,
     *     policy_version: string|null,
     *     rule_version: string,
     *     is_draft: bool,
     *     creates_debt: bool,
     *     data_verified: bool,
     *     is_lunas: bool,
     *     contract_principal: int,
     *     contract_charge: int,
     *     contract_total: int,
     *     paid_principal: int,
     *     paid_charge: int,
     *     paid_total: int,
     *     adjustment_principal: int,
     *     adjustment_charge: int,
     *     adjustment_total: int,
     *     principal_remaining: int|string,
     *     interest_remaining: int|string,
     *     admin_charge_remaining: int|string,
     *     other_charge_remaining: int|string,
     *     charge_remaining: int|string,
     *     total_remaining: int|string,
     *     included_event_ids: list<string>,
     *     warnings: list<string>
     * }
     */
    public function getBalance(
        Agreement|string $agreement,
        ?CarbonInterface $asOf = null,
        ?string $approvedPolicyVersion = null,
        ?bool $dataVerified = null
    ): array {
        $agreementModel = $agreement instanceof Agreement ? $agreement : Agreement::find($agreement);
        $agreementId = $agreementModel instanceof Agreement ? (string) $agreementModel->id : (string) $agreement;

        $evaluationDate = $asOf ?? Carbon::now();
        $asOfIso = $evaluationDate->toIso8601String();

        if (! $agreementModel instanceof Agreement) {
            return [
                'agreement_id' => $agreementId,
                'status' => 'unverified',
                'label' => 'Perjanjian Tidak Ditemukan',
                'as_of' => $asOfIso,
                'policy_version' => $approvedPolicyVersion,
                'rule_version' => self::RULE_VERSION,
                'is_draft' => false,
                'creates_debt' => false,
                'data_verified' => false,
                'is_lunas' => false,
                'contract_principal' => 0,
                'contract_charge' => 0,
                'contract_total' => 0,
                'paid_principal' => 0,
                'paid_charge' => 0,
                'paid_total' => 0,
                'adjustment_principal' => 0,
                'adjustment_charge' => 0,
                'adjustment_total' => 0,
                'principal_remaining' => 'unverified',
                'interest_remaining' => 'unverified',
                'admin_charge_remaining' => 'unverified',
                'other_charge_remaining' => 'unverified',
                'charge_remaining' => 'unverified',
                'total_remaining' => 'unverified',
                'included_event_ids' => [],
                'warnings' => ['Perjanjian tidak ditemukan.'],
            ];
        }

        $isDraft = $agreementModel->lifecycle_status === AgreementLifecycleStatus::Draft;

        if ($isDraft) {
            return [
                'agreement_id' => $agreementId,
                'status' => 'draft',
                'label' => 'Draft (Tidak Menimbulkan Piutang)',
                'as_of' => $asOfIso,
                'policy_version' => $approvedPolicyVersion,
                'rule_version' => self::RULE_VERSION,
                'is_draft' => true,
                'creates_debt' => false,
                'data_verified' => false,
                'is_lunas' => false,
                'contract_principal' => (int) ($agreementModel->principal_amount ?? 0),
                'contract_charge' => (int) (($agreementModel->interest_amount ?? 0)
                    + ($agreementModel->admin_charge_amount ?? 0)
                    + ($agreementModel->other_charge_amount ?? 0)),
                'contract_total' => (int) ($agreementModel->total_amount ?? 0),
                'paid_principal' => 0,
                'paid_charge' => 0,
                'paid_total' => 0,
                'adjustment_principal' => 0,
                'adjustment_charge' => 0,
                'adjustment_total' => 0,
                'principal_remaining' => 'unverified',
                'interest_remaining' => 'unverified',
                'admin_charge_remaining' => 'unverified',
                'other_charge_remaining' => 'unverified',
                'charge_remaining' => 'unverified',
                'total_remaining' => 'unverified',
                'included_event_ids' => [],
                'warnings' => [
                    'Perjanjian draft belum aktif dan tidak menimbulkan kewajiban piutang (PRD FR-02).',
                ],
            ];
        }

        // Determine data_verified: active/closed agreements with non-null contract amounts
        $isDataVerified = $dataVerified ?? (
            $agreementModel->lifecycle_status !== AgreementLifecycleStatus::Unknown
            && $agreementModel->lifecycle_status !== AgreementLifecycleStatus::Cancelled
            && $agreementModel->principal_amount !== null
            && $agreementModel->total_amount !== null
        );

        $asOfDateString = $evaluationDate->toDateString();

        // Query PaymentAllocation records:
        // - Belong to this agreement
        // - Original allocations (reversal_of_id is null)
        // - Effective on or before asOfDate
        // - Finalized states (posted or reversed)
        // - Excluding effectively-reversed allocations (where a reversal exists with effective_date <= asOfDate)
        $allocations = PaymentAllocation::query()
            ->where('agreement_id', $agreementId)
            ->whereNull('reversal_of_id')
            ->where('effective_date', '<=', $asOfDateString)
            ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value])
            ->whereDoesntHave('reversals', function ($query) use ($asOfDateString) {
                $query->where('effective_date', '<=', $asOfDateString)
                    ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value]);
            })
            ->get();

        // Query ReceivableAdjustment records:
        // - Belong to this agreement
        // - Posted state
        // - Effective on or before asOfDate
        $adjustments = ReceivableAdjustment::query()
            ->where('agreement_id', $agreementId)
            ->where('state', PaymentState::Posted->value)
            ->where('effective_date', '<=', $asOfDateString)
            ->get();

        // Contract components
        $pContract = (int) ($agreementModel->principal_amount ?? 0);
        $interestContract = (int) ($agreementModel->interest_amount ?? 0);
        $adminContract = (int) ($agreementModel->admin_charge_amount ?? 0);
        $otherContract = (int) ($agreementModel->other_charge_amount ?? 0);
        $cContract = $interestContract + $adminContract + $otherContract;
        $contractTotal = (int) ($agreementModel->total_amount ?? ($pContract + $cContract));

        // Paid components from allocations
        $paidP = (int) $allocations->sum('principal_amount');
        $paidInterest = (int) $allocations->sum('interest_amount');
        $paidAdmin = (int) $allocations->sum('admin_charge_amount');
        $paidOther = (int) $allocations->sum('other_charge_amount');
        $paidC = $paidInterest + $paidAdmin + $paidOther;
        $paidTotal = $paidP + $paidC;

        // Adjustments (signed values)
        $adjP = (int) $adjustments->sum('principal_amount');
        $adjInterest = (int) $adjustments->sum('interest_amount');
        $adjAdmin = (int) $adjustments->sum('admin_charge_amount');
        $adjOther = (int) $adjustments->sum('other_charge_amount');
        $adjC = $adjInterest + $adjAdmin + $adjOther;
        $adjTotal = $adjP + $adjC;

        // Remaining balances per DEC-008
        // remaining_P(as_of) = P_contract + adj_P(as_of) - paid_P(as_of)
        // remaining_C(as_of) = C_contract + adj_C(as_of) - paid_C(as_of)
        $remainingP = $pContract + $adjP - $paidP;
        $remainingInterest = $interestContract + $adjInterest - $paidInterest;
        $remainingAdmin = $adminContract + $adjAdmin - $paidAdmin;
        $remainingOther = $otherContract + $adjOther - $paidOther;
        $remainingC = $cContract + $adjC - $paidC;
        $remainingTotal = $remainingP + $remainingC;

        // Included event IDs
        $includedAllocationIds = $allocations->pluck('id')->map(fn ($id) => (string) $id)->all();
        $includedAdjustmentIds = $adjustments->pluck('id')->map(fn ($id) => (string) $id)->all();
        $includedEventIds = array_values(array_unique(array_merge($includedAllocationIds, $includedAdjustmentIds)));

        $warnings = [];

        // Invariant: Posting must never produce remaining_P < 0 or remaining_C < 0.
        // If a negative appears (data error), surface as an explicit exception state. Do not floor.
        $hasNegative = $remainingP < 0
            || $remainingC < 0
            || $remainingInterest < 0
            || $remainingAdmin < 0
            || $remainingOther < 0;

        if ($hasNegative) {
            $warnings[] = 'Terdeteksi saldo negatif pada komponen piutang (data error). Saldo tidak di-floor ke 0 (DEC-008).';
        }

        // Check if any linked installment schedule has overpaid components (PRD §4 invariant 1, TASK-REM-004)
        $hasCorruptedSchedules = InstallmentSchedule::where('agreement_id', $agreementId)
            ->where(function ($query): void {
                $query->whereColumn('principal_paid', '>', 'principal_due')
                    ->orWhereColumn('interest_paid', '>', 'interest_due')
                    ->orWhereColumn('admin_charge_paid', '>', 'admin_charge_due')
                    ->orWhereColumn('other_charge_paid', '>', 'other_charge_due')
                    ->orWhereColumn('total_paid', '>', 'total_due');
            })
            ->exists();

        if ($hasCorruptedSchedules) {
            $warnings[] = 'Terdeteksi baris jadwal angsuran dengan pembayaran melebihi kewajiban (schedule overpayment integrity error).';
        }

        $hasIntegrityError = $hasNegative || $hasCorruptedSchedules;

        // LUNAS rule: is_lunas(as_of) = data_verified AND remaining(as_of) == 0 AND remaining_P == 0 AND remaining_C == 0
        $isLunas = $isDataVerified
            && ! $hasIntegrityError
            && $remainingTotal === 0
            && $remainingP === 0
            && $remainingC === 0;

        if ($hasIntegrityError) {
            $status = 'exception';
            $label = 'Pengecualian Saldo (Data Error)';
        } elseif (! $isDataVerified) {
            $status = 'unverified';
            $label = 'Belum Terverifikasi';
            $warnings[] = 'Data perjanjian belum terverifikasi (DEC-008).';
        } elseif ($isLunas) {
            $status = 'lunas';
            $label = 'Lunas';
        } else {
            $status = 'computed';
            $label = 'Terhitung';
        }

        return [
            'agreement_id' => $agreementId,
            'status' => $status,
            'label' => $label,
            'as_of' => $asOfIso,
            'policy_version' => $approvedPolicyVersion,
            'rule_version' => self::RULE_VERSION,
            'is_draft' => false,
            'creates_debt' => true,
            'data_verified' => $isDataVerified,
            'is_lunas' => $isLunas,
            'contract_principal' => $pContract,
            'contract_charge' => $cContract,
            'contract_total' => $contractTotal,
            'paid_principal' => $paidP,
            'paid_charge' => $paidC,
            'paid_total' => $paidTotal,
            'adjustment_principal' => $adjP,
            'adjustment_charge' => $adjC,
            'adjustment_total' => $adjTotal,
            'principal_remaining' => $remainingP,
            'interest_remaining' => $remainingInterest,
            'admin_charge_remaining' => $remainingAdmin,
            'other_charge_remaining' => $remainingOther,
            'charge_remaining' => $remainingC,
            'total_remaining' => $remainingTotal,
            'included_event_ids' => $includedEventIds,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate partner total remaining debt across all active agreements.
     *
     * Per DP-7 (Excess Scope) & DEC-006:
     * partner_total_remaining(as_of) = SUM(remaining over that partner's active agreements)
     *
     * Only agreements with lifecycle_status = Active are included.
     * Draft agreements do not create debt (PRD FR-02).
     * Closed/completed/cancelled agreements have no active receivable.
     *
     * @param  Partner|string  $partner  Partner model or partner UUID
     * @param  CarbonInterface|null  $asOf  Evaluation date
     * @return int Total remaining debt in integer Rupiah
     */
    public function getPartnerTotalRemaining(
        Partner|string $partner,
        ?CarbonInterface $asOf = null
    ): int {
        $partnerId = $partner instanceof Partner ? $partner->id : $partner;

        $agreements = Agreement::query()
            ->where('partner_id', $partnerId)
            ->where('lifecycle_status', AgreementLifecycleStatus::Active->value)
            ->get();

        $totalRemaining = 0;
        foreach ($agreements as $agreement) {
            $balance = $this->getBalance($agreement, $asOf);
            if (is_int($balance['total_remaining']) && $balance['total_remaining'] > 0) {
                $totalRemaining += $balance['total_remaining'];
            }
        }

        return $totalRemaining;
    }
}
