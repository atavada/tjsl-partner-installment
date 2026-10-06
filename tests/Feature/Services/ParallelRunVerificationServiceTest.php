<?php

declare(strict_types=1);

use App\Models\Agreement;
use App\Models\ParallelRunDiscrepancy;
use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use App\Services\ParallelRunVerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ParallelRunVerificationService (PRD §5 FR-10, Finding #11)', function () {
    beforeEach(function () {
        $this->service = new ParallelRunVerificationService;
        $this->runDate = Carbon::parse('2026-10-06');
    });

    it('returns null when legacy values match ledger values exactly', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
        ]);

        $legacyValues = [
            'principal' => 10_000_000,
            'remaining_balance' => 10_000_000,
            'paid_amount' => 0,
            'collectibility' => $agreement->collectibility_status?->value ?? 'unknown',
        ];

        $discrepancy = $this->service->compareAgreement($agreement, $legacyValues, $this->runDate);

        expect($discrepancy)->toBeNull()
            ->and(ParallelRunDiscrepancy::count())->toBe(0);
    });

    it('records ParallelRunDiscrepancy on principal variance', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
        ]);

        $legacyValues = [
            'principal' => 12_000_000, // Rp 2.000.000 higher
            'remaining_balance' => 10_000_000,
            'paid_amount' => 0,
        ];

        $discrepancy = $this->service->compareAgreement($agreement, $legacyValues, $this->runDate);

        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->variance_type)->toBe('principal_mismatch')
            ->and($discrepancy->variance_amount)->toBe(2_000_000)
            ->and($discrepancy->agreement_id)->toBe($agreement->id);
    });

    it('records ParallelRunDiscrepancy on remaining balance variance', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
        ]);

        $legacyValues = [
            'principal' => 10_000_000,
            'remaining_balance' => 8_000_000, // Rp 2.000.000 lower
            'paid_amount' => 2_000_000,
        ];

        $discrepancy = $this->service->compareAgreement($agreement, $legacyValues, $this->runDate);

        expect($discrepancy)->not->toBeNull()
            ->and($discrepancy->variance_type)->toBe('balance_mismatch')
            ->and($discrepancy->variance_amount)->toBe(-2_000_000);
    });

    it('runs batch daily dual-run across legacy snapshot rows', function () {
        $agreement1 = Agreement::factory()->active()->create([
            'agreement_number' => 'AGR-DUAL-001',
            'principal_amount' => 5_000_000,
        ]);

        $agreement2 = Agreement::factory()->active()->create([
            'agreement_number' => 'AGR-DUAL-002',
            'principal_amount' => 15_000_000,
        ]);

        $snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'dual_run_wb'),
            'filename' => 'dual_run.xlsx',
            'as_of_date' => '2026-10-06',
        ]);

        // Row 1: matches
        SourceRow::create([
            'snapshot_id' => $snapshot->id,
            'sheet_name' => 'Sheet1',
            'row_number' => 1,
            'raw_values' => [
                'nomor_perjanjian' => 'AGR-DUAL-001',
                'pokok' => 5_000_000,
                'sisa_pokok' => 5_000_000,
                'bayar_pokok' => 0,
            ],
        ]);

        // Row 2: has balance mismatch
        SourceRow::create([
            'snapshot_id' => $snapshot->id,
            'sheet_name' => 'Sheet1',
            'row_number' => 2,
            'raw_values' => [
                'nomor_perjanjian' => 'AGR-DUAL-002',
                'pokok' => 15_000_000,
                'sisa_pokok' => 12_000_000, // 3M variance
                'bayar_pokok' => 3_000_000,
            ],
        ]);

        $summary = $this->service->runDailyDualRun($snapshot, $this->runDate);

        expect($summary['checked_count'])->toBe(2)
            ->and($summary['discrepancies_count'])->toBe(1)
            ->and($summary['total_variance'])->toBe(3_000_000);
    });
});
