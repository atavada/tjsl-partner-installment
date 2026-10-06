<?php

declare(strict_types=1);

use App\Enums\DiscrepancyType;
use App\Enums\ExportType;
use App\Enums\Permission;
use App\Exceptions\NotApprovedException;
use App\Jobs\GenerateExportJob;
use App\Jobs\ProcessBankMutasiJob;
use App\Jobs\ProcessWorkbookIngestionJob;
use App\Models\BankTransaction;
use App\Models\ExportJob;
use App\Models\ReconciliationCase;
use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use App\Models\User;
use App\Services\CandidateMatchingService;
use App\Services\MonitoringExportService;
use App\Services\PhaseBGateService;
use App\Services\WorkbookExceptionWorkbenchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Queue Jobs & Ingestion Processing (PRD §5 FR-04, FR-07, FR-14, Findings #7, #8, #16, #26)', function () {
    it('processes bank mutasi records with SHA-256 idempotency and creates unmatched reconciliation cases', function () {
        $matchingService = app(CandidateMatchingService::class);

        $records = [
            [
                'reference' => 'TX-MUTASI-001',
                'datetime' => '2026-10-01 08:30:00',
                'amount' => 1_250_000,
                'payer_name' => 'Unknown Depositor',
                'payer_va' => '9999000011112222',
            ],
            [
                'reference' => 'TX-MUTASI-002',
                'datetime' => '2026-10-01 09:15:00',
                'amount' => 500_000,
                'payer_name' => 'Second Depositor',
            ],
        ];

        $job = new ProcessBankMutasiJob($records, 'bank_bni_upload');
        $job->handle($matchingService);

        expect(BankTransaction::count())->toBe(2);

        $case = ReconciliationCase::where('case_type', 'unmatched_deposit')->first();
        expect($case)->not->toBeNull()
            ->and($case->discrepancy_type)->toBe(DiscrepancyType::UnmatchedDeposit);

        // Re-run identical job: idempotency ensures count stays 2 (PRD §4 Invariant 5)
        $job->handle($matchingService);
        expect(BankTransaction::count())->toBe(2);
    });

    it('processes synthetic workbook ingestion job cleanly', function () {
        $gateService = app(PhaseBGateService::class);
        $workbenchService = app(WorkbookExceptionWorkbenchService::class);

        $snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'synthetic_file_test'),
            'filename' => 'synthetic_sample.xlsx',
            'as_of_date' => '2026-10-01',
            'is_synthetic' => true,
            'status' => 'pending',
        ]);

        $rows = [
            [
                'sheet_name' => 'MASTER_All Piutang MB',
                'row_number' => 10,
                'raw_values' => ['no_id' => '000000000012345', 'nama' => 'Test Partner'],
            ],
            [
                'sheet_name' => 'MASTER_All Piutang MB',
                'row_number' => 11,
                'raw_values' => ['formula_err' => '#REF!'],
                'cached_values' => ['formula_err' => '#REF!'],
            ],
        ];

        $job = new ProcessWorkbookIngestionJob($snapshot->id, $rows);
        $job->handle($gateService, $workbenchService);

        $snapshot->refresh();

        expect($snapshot->status)->toBe('completed')
            ->and(SourceRow::where('snapshot_id', $snapshot->id)->count())->toBe(2);

        $case = ReconciliationCase::where('discrepancy_type', DiscrepancyType::FormulaError)->first();
        expect($case)->not->toBeNull();
    });

    it('blocks live production workbook ingestion in job when Phase B is unapproved', function () {
        config(['finance.phase_b_approved' => false]);
        $gateService = app(PhaseBGateService::class);
        $workbenchService = app(WorkbookExceptionWorkbenchService::class);

        $snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'real_prod_file'),
            'filename' => 'REAL_PRODUCTION_WORKBOOK_2026.xlsx',
            'as_of_date' => '2026-10-01',
            'is_synthetic' => false, // Live production workbook
            'status' => 'pending',
        ]);

        $job = new ProcessWorkbookIngestionJob($snapshot->id, []);

        expect(fn () => $job->handle($gateService, $workbenchService))
            ->toThrow(NotApprovedException::class);
    });

    it('processes GenerateExportJob and updates ExportJob status to completed', function () {
        Storage::fake('local');
        $exportService = app(MonitoringExportService::class);

        $user = User::factory()->operator()->create();
        $user->grantPermission(Permission::SensitiveExport);

        $exportJob = ExportJob::create([
            'user_id' => $user->id,
            'export_type' => ExportType::MonitoringMonthly,
            'scope' => 'all',
            'status' => 'pending',
        ]);

        $job = new GenerateExportJob($exportJob->id);
        $job->handle($exportService);

        $exportJob->refresh();

        expect($exportJob->status)->toBe('completed')
            ->and($exportJob->file_path)->not->toBeNull();
    });
});
