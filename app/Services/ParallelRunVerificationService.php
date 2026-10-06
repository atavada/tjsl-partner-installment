<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agreement;
use App\Models\ParallelRunDiscrepancy;
use App\Models\SourceSnapshot;
use Carbon\CarbonInterface;

class ParallelRunVerificationService
{
    /**
     * Compare legacy values against ledger values for a specific agreement (PRD §5 FR-10).
     *
     * @param  array{
     *     principal?: int,
     *     remaining_balance?: int,
     *     paid_amount?: int,
     *     collectibility?: string
     * }  $legacyValues
     */
    public function compareAgreement(
        Agreement $agreement,
        array $legacyValues,
        CarbonInterface $runDate
    ): ?ParallelRunDiscrepancy {
        $balanceInfo = app(BalanceService::class)->getBalance($agreement, $runDate);

        $ledgerPrincipal = $agreement->principal_amount;
        $ledgerBalance = (int) $balanceInfo['principal_remaining'];
        $ledgerPaid = (int) $balanceInfo['paid_principal'];
        $ledgerCollectibility = $agreement->collectibility_status?->value ?? 'unknown';

        $ledgerValues = [
            'principal' => $ledgerPrincipal,
            'remaining_balance' => $ledgerBalance,
            'paid_amount' => $ledgerPaid,
            'collectibility' => $ledgerCollectibility,
        ];

        // 1. Check Principal Mismatch
        if (isset($legacyValues['principal']) && $legacyValues['principal'] !== $ledgerPrincipal) {
            $variance = $legacyValues['principal'] - $ledgerPrincipal;

            return ParallelRunDiscrepancy::create([
                'run_date' => $runDate->toDateString(),
                'agreement_id' => $agreement->id,
                'legacy_values' => $legacyValues,
                'ledger_values' => $ledgerValues,
                'variance_amount' => $variance,
                'variance_type' => 'principal_mismatch',
                'status' => 'open',
                'notes' => 'Principal variance of Rp '.number_format($variance, 0, ',', '.').' between legacy and ledger',
            ]);
        }

        // 2. Check Balance Mismatch
        if (isset($legacyValues['remaining_balance']) && $legacyValues['remaining_balance'] !== $ledgerBalance) {
            $variance = $legacyValues['remaining_balance'] - $ledgerBalance;

            return ParallelRunDiscrepancy::create([
                'run_date' => $runDate->toDateString(),
                'agreement_id' => $agreement->id,
                'legacy_values' => $legacyValues,
                'ledger_values' => $ledgerValues,
                'variance_amount' => $variance,
                'variance_type' => 'balance_mismatch',
                'status' => 'open',
                'notes' => 'Remaining balance variance of Rp '.number_format($variance, 0, ',', '.').' between legacy and ledger',
            ]);
        }

        // 3. Check Collectibility Status Mismatch
        if (
            isset($legacyValues['collectibility']) &&
            trim((string) $legacyValues['collectibility']) !== '' &&
            mb_strtolower(trim((string) $legacyValues['collectibility'])) !== mb_strtolower(trim($ledgerCollectibility))
        ) {
            return ParallelRunDiscrepancy::create([
                'run_date' => $runDate->toDateString(),
                'agreement_id' => $agreement->id,
                'legacy_values' => $legacyValues,
                'ledger_values' => $ledgerValues,
                'variance_amount' => 0,
                'variance_type' => 'status_mismatch',
                'status' => 'open',
                'notes' => "Collectibility band mismatch: legacy is [{$legacyValues['collectibility']}], ledger is [{$ledgerCollectibility}]",
            ]);
        }

        return null;
    }

    /**
     * Run daily dual-run comparison across all rows in a legacy snapshot against ledger agreements.
     *
     * @return array{
     *     checked_count: int,
     *     discrepancies_count: int,
     *     total_variance: int
     * }
     */
    public function runDailyDualRun(SourceSnapshot $legacySnapshot, CarbonInterface $runDate): array
    {
        $checkedCount = 0;
        $discrepanciesCount = 0;
        $totalVariance = 0;

        $rows = $legacySnapshot->rows()->with('snapshot')->get();

        foreach ($rows as $row) {
            $raw = $row->raw_values ?? [];
            $agreementNumber = $raw['nomor_perjanjian'] ?? $raw['no_perjanjian'] ?? null;

            if ($agreementNumber === null) {
                continue;
            }

            $agreement = Agreement::where('agreement_number', $agreementNumber)->first();
            if ($agreement === null) {
                continue;
            }

            $checkedCount++;

            $legacyValues = [
                'principal' => (int) ($raw['pokok'] ?? $raw['plafon'] ?? 0),
                'remaining_balance' => (int) ($raw['sisa_pokok'] ?? 0),
                'paid_amount' => (int) ($raw['bayar_pokok'] ?? 0),
                'collectibility' => (string) ($raw['kolektibilitas'] ?? ''),
            ];

            $discrepancy = $this->compareAgreement($agreement, $legacyValues, $runDate);

            if ($discrepancy !== null) {
                $discrepanciesCount++;
                $totalVariance += abs($discrepancy->variance_amount);
            }
        }

        return [
            'checked_count' => $checkedCount,
            'discrepancies_count' => $discrepanciesCount,
            'total_variance' => $totalVariance,
        ];
    }
}
