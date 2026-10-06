<?php

declare(strict_types=1);

use App\Enums\DiscrepancyType;
use App\Enums\ReconciliationStatus;
use App\Models\Agreement;
use App\Models\Partner;
use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use App\Services\WorkbookExceptionWorkbenchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('WorkbookExceptionWorkbenchService (PRD §5 FR-08, FR-09, Findings #9, #10)', function () {
    beforeEach(function () {
        $this->service = new WorkbookExceptionWorkbenchService;

        $this->snapshot = SourceSnapshot::create([
            'file_hash' => hash('sha256', 'test_wb'),
            'filename' => 'MASTER_All_Piutang_MB.xlsx',
            'as_of_date' => '2026-09-30',
        ]);
    });

    it('detects formula errors (#REF!, #VALUE!) and creates ReconciliationCase', function () {
        $row = SourceRow::create([
            'snapshot_id' => $this->snapshot->id,
            'sheet_name' => '121.20',
            'row_number' => 88,
            'cell_coordinates' => 'L88',
            'raw_values' => ['pokok' => 10000000],
            'formula_text' => ['sisa' => '=SUM(#REF!, K88)'],
            'cached_values' => ['sisa' => '#REF!'],
        ]);

        $case = $this->service->analyzeRow($row);

        expect($case)->not->toBeNull()
            ->and($case->discrepancy_type)->toBe(DiscrepancyType::FormulaError)
            ->and($case->status)->toBe(ReconciliationStatus::Unreviewed)
            ->and($case->evidence)->toContain('Formula corruption');
    });

    it('detects LUNAS status with positive remaining balance anomaly', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 10_000_000,
        ]);

        $row = SourceRow::create([
            'snapshot_id' => $this->snapshot->id,
            'sheet_name' => 'MASTER_All Piutang MB',
            'row_number' => 20,
            'raw_values' => [
                'no_id' => $partner->partner_no_id,
                'status' => 'LUNAS',
            ],
            'cached_values' => [
                'sisa_pokok' => 5_000_000,
            ],
        ]);

        $case = $this->service->analyzeRow($row, $agreement);

        expect($case)->not->toBeNull()
            ->and($case->discrepancy_type)->toBe(DiscrepancyType::LunasWithPositiveBalance)
            ->and($case->evidence)->toContain('Legacy workbook marked LUNAS, but remaining balance is');
    });

    it('detects missing partner ID on row', function () {
        $row = SourceRow::create([
            'snapshot_id' => $this->snapshot->id,
            'sheet_name' => 'MASTER_All Piutang MB',
            'row_number' => 35,
            'raw_values' => [
                'nama' => 'Peminjam Tanpa ID',
                'sisa_pokok' => 2_000_000,
            ],
        ]);

        $case = $this->service->analyzeRow($row);

        expect($case)->not->toBeNull()
            ->and($case->discrepancy_type)->toBe(DiscrepancyType::MissingPartnerId)
            ->and($case->evidence)->toContain('No valid partner NO ID found');
    });

    it('compiles accurate exception package summary for snapshot', function () {
        // Create 2 rows with errors
        $row1 = SourceRow::create([
            'snapshot_id' => $this->snapshot->id,
            'sheet_name' => 'Sheet1',
            'row_number' => 1,
            'raw_values' => ['status' => 'LUNAS', 'sisa_pokok' => 1000000],
        ]);
        $this->service->analyzeRow($row1);

        $row2 = SourceRow::create([
            'snapshot_id' => $this->snapshot->id,
            'sheet_name' => 'Sheet1',
            'row_number' => 2,
            'raw_values' => ['formula' => '#VALUE!'],
            'cached_values' => ['formula' => '#VALUE!'],
        ]);
        $this->service->analyzeRow($row2);

        $package = $this->service->generateExceptionPackage($this->snapshot);

        expect($package['snapshot_id'])->toBe($this->snapshot->id)
            ->and($package['filename'])->toBe($this->snapshot->filename)
            ->and($package['total_rows_inspected'])->toBe(2)
            ->and($package['cases_count'])->toBe(2)
            ->and($package['by_type'])->toHaveKeys([
                DiscrepancyType::LunasWithPositiveBalance->value,
                DiscrepancyType::FormulaError->value,
            ]);
    });
});
