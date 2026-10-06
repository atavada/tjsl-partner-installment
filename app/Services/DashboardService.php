<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\CollectibilityStatus;
use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Service for computing live portfolio dashboard metrics and risk breakdowns.
 *
 * Implements PRD FR-11, DEC-006, DEC-007, and DEC-008.
 */
class DashboardService
{
    /**
     * Collectibility severity ranking for partner-level risk rollups.
     * Highest severity determines partner risk classification across active agreements.
     */
    protected const SEVERITY_RANK = [
        'bermasalah' => 6,
        'diragukan' => 5,
        'kurang_lancar' => 4,
        'lancar' => 3,
        'lunas' => 2,
        'unknown' => 1,
    ];

    public function __construct(
        protected BalanceService $balanceService,
        protected CollectibilityCalculationService $collectibilityService
    ) {}

    /**
     * Compute portfolio financial aggregates and collectibility distributions.
     *
     * @return array<string, mixed>
     */
    public function getPortfolioMetrics(
        ?CarbonInterface $asOf = null,
        ?string $batchYear = null,
        ?string $region = null
    ): array {
        $evaluationDate = $asOf ?? Carbon::now();
        $asOfDate = $evaluationDate->toDateString();
        $asOfIso = $evaluationDate->toIso8601String();
        $period = $evaluationDate->format('Y-m');

        // Query active agreements per PRD §4 & FR-11
        $agreementQuery = Agreement::query()
            ->where('lifecycle_status', AgreementLifecycleStatus::Active->value)
            ->with(['partner']);

        if ($batchYear !== null && trim($batchYear) !== '') {
            $agreementQuery->where('batch_year', trim($batchYear));
        }

        if ($region !== null && trim($region) !== '') {
            $agreementQuery->whereHas('partner', function ($q) use ($region): void {
                $q->where('region', trim($region));
            });
        }

        $agreements = $agreementQuery->get();
        $activeAgreementsCount = $agreements->count();

        $totalPrincipalOutstanding = 0;
        $totalChargeOutstanding = 0;
        $totalRemainingPortfolioBalance = 0;

        // Band accumulator initialized with canonical order
        $bandDefinitions = [
            CollectibilityStatus::Lancar->value => [
                'status' => CollectibilityStatus::Lancar->value,
                'label' => CollectibilityStatus::Lancar->label(),
                'months_range' => '0–1 bulan',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
            CollectibilityStatus::KurangLancar->value => [
                'status' => CollectibilityStatus::KurangLancar->value,
                'label' => CollectibilityStatus::KurangLancar->label(),
                'months_range' => '2–6 bulan',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
            CollectibilityStatus::Diragukan->value => [
                'status' => CollectibilityStatus::Diragukan->value,
                'label' => CollectibilityStatus::Diragukan->label(),
                'months_range' => '7–9 bulan',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
            CollectibilityStatus::Bermasalah->value => [
                'status' => CollectibilityStatus::Bermasalah->value,
                'label' => CollectibilityStatus::Bermasalah->label(),
                'months_range' => '> 9 bulan',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
            CollectibilityStatus::Lunas->value => [
                'status' => CollectibilityStatus::Lunas->value,
                'label' => CollectibilityStatus::Lunas->label(),
                'months_range' => 'Rp0 Lunas',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
            CollectibilityStatus::Unknown->value => [
                'status' => CollectibilityStatus::Unknown->value,
                'label' => CollectibilityStatus::Unknown->label(),
                'months_range' => 'Unverified / Exception',
                'agreements_count' => 0,
                'partners_count' => 0,
                'total_outstanding' => 0,
            ],
        ];

        // Track partner highest-risk agreement status
        /** @var array<string, string> $partnerHighestRisk */
        $partnerHighestRisk = [];

        foreach ($agreements as $agreement) {
            $balance = $this->balanceService->getBalance($agreement, $evaluationDate);
            $collectibility = $this->collectibilityService->calculate($agreement, $evaluationDate);

            $p = is_numeric($balance['principal_remaining']) ? (int) $balance['principal_remaining'] : 0;
            $c = is_numeric($balance['charge_remaining']) ? (int) $balance['charge_remaining'] : 0;
            $total = is_numeric($balance['total_remaining']) ? (int) $balance['total_remaining'] : 0;

            $totalPrincipalOutstanding += $p;
            $totalChargeOutstanding += $c;
            $totalRemainingPortfolioBalance += $total;

            $bandKey = $collectibility['status'];
            if (! isset($bandDefinitions[$bandKey])) {
                $bandKey = CollectibilityStatus::Unknown->value;
            }

            $bandDefinitions[$bandKey]['agreements_count']++;
            $bandDefinitions[$bandKey]['total_outstanding'] += $total;

            // Partner-level risk resolution
            $partnerId = (string) $agreement->partner_id;
            if (! isset($partnerHighestRisk[$partnerId])) {
                $partnerHighestRisk[$partnerId] = $bandKey;
            } else {
                $currentRank = self::SEVERITY_RANK[$partnerHighestRisk[$partnerId]] ?? 0;
                $newRank = self::SEVERITY_RANK[$bandKey] ?? 0;
                if ($newRank > $currentRank) {
                    $partnerHighestRisk[$partnerId] = $bandKey;
                }
            }
        }

        // Count partners per resolved highest-risk band
        foreach ($partnerHighestRisk as $resolvedBand) {
            if (isset($bandDefinitions[$resolvedBand])) {
                $bandDefinitions[$resolvedBand]['partners_count']++;
            } else {
                $bandDefinitions[CollectibilityStatus::Unknown->value]['partners_count']++;
            }
        }

        $activePartnersCount = count($partnerHighestRisk);

        // Portfolio total partner count
        $partnerCountQuery = Partner::query();
        if ($region !== null && trim($region) !== '') {
            $partnerCountQuery->where('region', trim($region));
        }
        $totalPortfolioPartnersCount = $partnerCountQuery->count();

        // Calculate percentages for each band
        $collectibilityBands = [];
        foreach ($bandDefinitions as $band) {
            $agrPct = $activeAgreementsCount > 0
                ? round(($band['agreements_count'] / $activeAgreementsCount) * 100, 1)
                : 0.0;
            $partnerPct = $activePartnersCount > 0
                ? round(($band['partners_count'] / $activePartnersCount) * 100, 1)
                : 0.0;

            $collectibilityBands[] = array_merge($band, [
                'agreements_percentage' => $agrPct,
                'partners_percentage' => $partnerPct,
            ]);
        }

        // Total payments collected in the period
        $allocQuery = PaymentAllocation::query()
            ->whereNull('reversal_of_id')
            ->where('period', $period)
            ->where('effective_date', '<=', $asOfDate)
            ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value])
            ->whereDoesntHave('reversals', function ($q) use ($asOfDate): void {
                $q->where('effective_date', '<=', $asOfDate)
                    ->whereIn('state', [PaymentState::Posted->value, PaymentState::Reversed->value]);
            });

        if ($batchYear !== null && trim($batchYear) !== '') {
            $allocQuery->whereHas('agreement', function ($q) use ($batchYear): void {
                $q->where('batch_year', trim($batchYear));
            });
        }

        if ($region !== null && trim($region) !== '') {
            $allocQuery->whereHas('agreement.partner', function ($q) use ($region): void {
                $q->where('region', trim($region));
            });
        }

        $totalPaymentsCollectedPeriod = (int) $allocQuery->sum('total_amount');

        // Unallocated ABT total per DEC-006 (Abt and IdentifiedUnallocated)
        $abtQuery = FundLot::query()
            ->whereIn('lot_type', [
                FundLotType::Abt->value,
                FundLotType::IdentifiedUnallocated->value,
            ]);

        if ($region !== null && trim($region) !== '') {
            $abtQuery->where(function ($q) use ($region): void {
                $q->whereHas('partner', function ($pq) use ($region): void {
                    $pq->where('region', trim($region));
                })->orWhereNull('partner_id');
            });
        }

        $unallocatedAbtTotal = (int) $abtQuery->sum('amount');
        $unallocatedAbtCount = $abtQuery->count();
        $abtUnidentifiedAmount = (int) (clone $abtQuery)->where('lot_type', FundLotType::Abt->value)->sum('amount');
        $abtIdentifiedUnallocatedAmount = (int) (clone $abtQuery)->where('lot_type', FundLotType::IdentifiedUnallocated->value)->sum('amount');

        // Verify invariant: sum of bands must equal totals (PRD FR-11)
        $sumAgreements = array_sum(array_column($collectibilityBands, 'agreements_count'));
        $sumPartners = array_sum(array_column($collectibilityBands, 'partners_count'));
        $isValidSum = ($sumAgreements === $activeAgreementsCount) && ($sumPartners === $activePartnersCount);

        return [
            'as_of' => $asOfIso,
            'filters' => [
                'as_of' => $asOfDate,
                'batch_year' => $batchYear,
                'region' => $region,
            ],
            'filter_options' => $this->getFilterOptions(),
            'kpi' => [
                'active_agreements_count' => $activeAgreementsCount,
                'active_partners_count' => $activePartnersCount,
                'total_portfolio_partners_count' => $totalPortfolioPartnersCount,
                'total_principal_outstanding' => $totalPrincipalOutstanding,
                'total_charge_outstanding' => $totalChargeOutstanding,
                'total_remaining_portfolio_balance' => $totalRemainingPortfolioBalance,
                'total_payments_collected_period' => $totalPaymentsCollectedPeriod,
                'period_label' => $evaluationDate->translatedFormat('F Y'),
                'unallocated_abt_total' => $unallocatedAbtTotal,
                'unallocated_abt_count' => $unallocatedAbtCount,
                'abt_unidentified_amount' => $abtUnidentifiedAmount,
                'abt_identified_unallocated_amount' => $abtIdentifiedUnallocatedAmount,
            ],
            'collectibility' => [
                'bands' => $collectibilityBands,
                'total_agreements' => $sumAgreements,
                'total_partners' => $sumPartners,
                'total_outstanding' => $totalRemainingPortfolioBalance,
                'is_valid_sum' => $isValidSum,
            ],
        ];
    }

    /**
     * Get distinct filter options for cohort year and partner regions.
     *
     * @return array{batch_years: list<string>, regions: list<string>}
     */
    public function getFilterOptions(): array
    {
        $batchYears = Agreement::query()
            ->whereNotNull('batch_year')
            ->distinct()
            ->orderByDesc('batch_year')
            ->pluck('batch_year')
            ->filter(fn ($v): bool => trim((string) $v) !== '')
            ->values()
            ->all();

        $regions = Partner::query()
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->filter(fn ($v): bool => trim((string) $v) !== '')
            ->values()
            ->all();

        return [
            'batch_years' => array_values(array_map('strval', $batchYears)),
            'regions' => array_values(array_map('strval', $regions)),
        ];
    }
}
