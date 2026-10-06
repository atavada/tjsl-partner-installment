<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiscrepancyType;
use App\Enums\ReconciliationStatus;
use App\Models\Agreement;
use App\Models\ReconciliationCase;
use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use Illuminate\Support\Str;

class WorkbookExceptionWorkbenchService
{
    /**
     * Analyze a single source row against an optional ledger agreement.
     * PRD §5 FR-08, FR-09: Detect anomalies and create auditable ReconciliationCase.
     */
    public function analyzeRow(SourceRow $row, ?Agreement $agreement = null): ?ReconciliationCase
    {
        $rawValues = $row->raw_values ?? [];
        $formulaText = $row->formula_text ?? [];
        $cachedValues = $row->cached_values ?? [];

        // Check 1: Formula errors (#REF!, #VALUE!, #DIV/0!, #NAME?, #N/A)
        if ($this->hasFormulaErrors($formulaText, $cachedValues, $rawValues)) {
            return $this->createCase(
                caseType: 'workbook_exception',
                discrepancyType: DiscrepancyType::FormulaError,
                row: $row,
                agreement: $agreement,
                evidence: 'Formula corruption or evaluation error detected in workbook cell coordinates: '.($row->cell_coordinates ?? "Row {$row->row_number}"),
                resolutionNotes: 'Raw error values preserved per PRD §5 FR-07. Do not replace with zero.'
            );
        }

        // Check 2: LUNAS status with positive remaining balance (PRD §1, §5 FR-08, DEC-008)
        $statusRaw = mb_strtoupper(trim((string) ($rawValues['status'] ?? $rawValues['kolektibilitas'] ?? $rawValues['kategori'] ?? '')));
        if ($statusRaw === 'LUNAS') {
            $remainingBalance = $agreement !== null
                ? (int) app(BalanceService::class)->getBalance($agreement)['principal_remaining']
                : (int) ($cachedValues['sisa_pokok'] ?? $rawValues['sisa_pokok'] ?? 0);
            if ($remainingBalance > 0) {
                return $this->createCase(
                    caseType: 'workbook_exception',
                    discrepancyType: DiscrepancyType::LunasWithPositiveBalance,
                    row: $row,
                    agreement: $agreement,
                    evidence: 'Legacy workbook marked LUNAS, but remaining balance is Rp '.number_format($remainingBalance, 0, ',', '.'),
                    resolutionNotes: 'Requires reconciler verification before confirming payoff status.'
                );
            }
        }

        // Check 3: Missing Partner ID
        $partnerIdRaw = trim((string) ($rawValues['no_id'] ?? $rawValues['partner_no_id'] ?? $rawValues['id_mitra'] ?? ''));
        if ($partnerIdRaw === '' && $agreement === null) {
            return $this->createCase(
                caseType: 'workbook_exception',
                discrepancyType: DiscrepancyType::MissingPartnerId,
                row: $row,
                agreement: null,
                evidence: "No valid partner NO ID found in row {$row->row_number} of sheet {$row->sheet_name}",
                resolutionNotes: 'Staged without partner link. Requires manual partner identification.'
            );
        }

        // Check 4: Amount Discrepancy (if agreement provided)
        if ($agreement !== null) {
            $legacyPaid = (int) ($rawValues['total_angsuran'] ?? $rawValues['bayar_pokok'] ?? 0);
            $ledgerPaid = (int) app(BalanceService::class)->getBalance($agreement)['paid_total'];
            if ($legacyPaid > 0 && $ledgerPaid > 0 && abs($legacyPaid - $ledgerPaid) > 0) {
                return $this->createCase(
                    caseType: 'workbook_exception',
                    discrepancyType: DiscrepancyType::AmountDiscrepancy,
                    row: $row,
                    agreement: $agreement,
                    evidence: 'Variance detected: legacy paid = Rp '.number_format($legacyPaid, 0, ',', '.').', ledger paid = Rp '.number_format($ledgerPaid, 0, ',', '.'),
                    resolutionNotes: 'Investigate payment allocation history against bank records.'
                );
            }
        }

        return null;
    }

    /**
     * Generate an auditable exception package for a source snapshot (PRD §5 FR-09).
     *
     * @return array{
     *     snapshot_id: string,
     *     filename: string,
     *     as_of_date: string,
     *     total_rows_inspected: int,
     *     cases_count: int,
     *     by_type: array<string, int>,
     *     by_status: array<string, int>,
     *     generated_at: string
     * }
     */
    public function generateExceptionPackage(SourceSnapshot $snapshot): array
    {
        $cases = ReconciliationCase::whereHas('sourceRow', function ($query) use ($snapshot) {
            $query->where('snapshot_id', $snapshot->id);
        })->get();

        $byType = [];
        $byStatus = [];

        foreach ($cases as $case) {
            $t = $case->discrepancy_type->value;
            $s = $case->status->value;
            $byType[$t] = ($byType[$t] ?? 0) + 1;
            $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
        }

        return [
            'snapshot_id' => $snapshot->id,
            'filename' => $snapshot->filename,
            'as_of_date' => $snapshot->as_of_date->toDateString(),
            'total_rows_inspected' => $snapshot->rows()->count(),
            'cases_count' => $cases->count(),
            'by_type' => $byType,
            'by_status' => $byStatus,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if values or formulas contain Excel error markers.
     */
    protected function hasFormulaErrors(array ...$payloads): bool
    {
        $errorMarkers = ['#REF!', '#VALUE!', '#DIV/0!', '#NAME?', '#N/A', '#NULL!', '#NUM!'];

        foreach ($payloads as $payload) {
            foreach ($payload as $val) {
                if (is_string($val)) {
                    foreach ($errorMarkers as $marker) {
                        if (str_contains($val, $marker)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Helper to instantiate and persist a ReconciliationCase.
     */
    protected function createCase(
        string $caseType,
        DiscrepancyType $discrepancyType,
        SourceRow $row,
        ?Agreement $agreement,
        string $evidence,
        string $resolutionNotes
    ): ReconciliationCase {
        $caseNumber = 'REC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

        return ReconciliationCase::create([
            'case_number' => $caseNumber,
            'case_type' => $caseType,
            'source_row_id' => $row->id,
            'agreement_id' => $agreement?->id,
            'discrepancy_type' => $discrepancyType,
            'status' => ReconciliationStatus::Unreviewed,
            'evidence' => $evidence,
            'resolution_notes' => $resolutionNotes,
            'version' => 1,
        ]);
    }
}
