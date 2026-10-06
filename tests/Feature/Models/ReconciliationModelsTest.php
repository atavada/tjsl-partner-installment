<?php

declare(strict_types=1);

use App\Enums\DiscrepancyType;
use App\Enums\ExportType;
use App\Enums\PaymentState;
use App\Enums\ReconciliationStatus;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\ExportJob;
use App\Models\ParallelRunDiscrepancy;
use App\Models\Partner;
use App\Models\ReconciliationCase;
use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Reconciliation & Staging Models (TASK-REM-010, Findings #7, #8, #11, #16)', function () {
    it('creates and relates SourceSnapshot and SourceRow models with correct casts', function () {
        $user = User::factory()->create();

        $snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'synthetic_workbook_content'),
            'filename' => 'MASTER_All_Piutang_MB.xlsx',
            'as_of_date' => '2026-09-30',
            'parser_version' => 'v1.0.0',
            'sheet_inventory' => ['sheets' => ['MASTER_All Piutang MB', '121.20']],
            'status' => 'completed',
            'is_synthetic' => true,
            'recorded_by_id' => $user->id,
        ]);

        expect($snapshot->id)->toBeString()
            ->and($snapshot->as_of_date->toDateString())->toBe('2026-09-30')
            ->and($snapshot->sheet_inventory['sheets'])->toContain('MASTER_All Piutang MB')
            ->and($snapshot->is_synthetic)->toBeTrue()
            ->and($snapshot->recordedBy->id)->toBe($user->id);

        $row = SourceRow::create([
            'snapshot_id' => $snapshot->id,
            'sheet_name' => 'MASTER_All Piutang MB',
            'row_number' => 12,
            'cell_coordinates' => 'A12:R12',
            'raw_values' => ['no_id' => '000000000012345', 'nama' => 'Mitra Uji'],
            'formula_text' => ['sisa' => '=K12-M12'],
            'cached_values' => ['sisa' => 5000000],
            'is_hidden' => false,
            'parse_warnings' => ['Minor date formatting warning'],
        ]);

        expect($row->id)->toBeString()
            ->and($row->snapshot->id)->toBe($snapshot->id)
            ->and($row->raw_values['no_id'])->toBe('000000000012345')
            ->and($row->formula_text['sisa'])->toBe('=K12-M12')
            ->and($row->cached_values['sisa'])->toBe(5000000)
            ->and($row->is_hidden)->toBeFalse()
            ->and($row->parse_warnings)->toContain('Minor date formatting warning');
    });

    it('creates ReconciliationCase with enum casting and relationships', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);
        $bankTx = BankTransaction::factory()->create([
            'amount' => 1_000_000,
            'state' => PaymentState::Draft,
        ]);

        $snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'wb'),
            'filename' => 'wb.xlsx',
            'as_of_date' => '2026-09-30',
        ]);

        $row = SourceRow::create([
            'snapshot_id' => $snapshot->id,
            'sheet_name' => 'Sheet1',
            'row_number' => 5,
            'raw_values' => ['status' => 'LUNAS'],
        ]);

        $case = ReconciliationCase::create([
            'case_number' => 'REC-20261006-TEST01',
            'case_type' => 'workbook_exception',
            'source_row_id' => $row->id,
            'bank_transaction_id' => $bankTx->id,
            'agreement_id' => $agreement->id,
            'discrepancy_type' => DiscrepancyType::LunasWithPositiveBalance,
            'status' => ReconciliationStatus::Unreviewed,
            'candidate_matches' => [['partner_id' => $partner->id, 'score' => 0.9]],
            'evidence' => 'Marked LUNAS with positive balance',
            'resolution_notes' => 'Awaiting confirmation',
        ]);

        expect($case->discrepancy_type)->toBe(DiscrepancyType::LunasWithPositiveBalance)
            ->and($case->status)->toBe(ReconciliationStatus::Unreviewed)
            ->and($case->agreement->id)->toBe($agreement->id)
            ->and($case->bankTransaction->id)->toBe($bankTx->id)
            ->and($case->sourceRow->id)->toBe($row->id)
            ->and($case->status->isFinal())->toBeFalse();
    });

    it('creates ParallelRunDiscrepancy with signed variance amount and agreement link', function () {
        $agreement = Agreement::factory()->active()->create();

        $discrepancy = ParallelRunDiscrepancy::create([
            'run_date' => '2026-10-06',
            'agreement_id' => $agreement->id,
            'legacy_values' => ['principal' => 20_000_000, 'balance' => 10_000_000],
            'ledger_values' => ['principal' => 20_000_000, 'balance' => 9_500_000],
            'variance_amount' => 500_000,
            'variance_type' => 'balance_mismatch',
            'status' => 'open',
            'notes' => 'Legacy balance is Rp 500.000 higher than ledger',
        ]);

        expect($discrepancy->id)->toBeString()
            ->and($discrepancy->variance_amount)->toBe(500_000)
            ->and($discrepancy->variance_type)->toBe('balance_mismatch')
            ->and($discrepancy->agreement->id)->toBe($agreement->id);
    });

    it('creates ExportJob with ExportType casting and user link', function () {
        $user = User::factory()->create();

        $job = ExportJob::create([
            'user_id' => $user->id,
            'export_type' => ExportType::ReconciliationDifferences,
            'scope' => 'all',
            'filter_criteria' => ['status' => 'unreviewed'],
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        expect($job->id)->toBeString()
            ->and($job->export_type)->toBe(ExportType::ReconciliationDifferences)
            ->and($job->user->id)->toBe($user->id)
            ->and($job->expires_at)->not->toBeNull();
    });
});
