<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SourceRow;
use App\Models\SourceSnapshot;
use App\Services\PhaseBGateService;
use App\Services\WorkbookExceptionWorkbenchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWorkbookIngestionJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{
     *     sheet_name: string,
     *     row_number: int,
     *     cell_coordinates?: string|null,
     *     raw_values: array<string, mixed>,
     *     formula_text?: array<string, mixed>|null,
     *     cached_values?: array<string, mixed>|null,
     *     is_hidden?: bool,
     *     parse_warnings?: array<int, string>|null
     * }>  $parsedRows
     */
    public function __construct(
        public string $snapshotId,
        public array $parsedRows = [],
    ) {}

    public function handle(
        PhaseBGateService $gateService,
        WorkbookExceptionWorkbenchService $workbenchService
    ): void {
        $snapshot = SourceSnapshot::findOrFail($this->snapshotId);

        // PRD §0, §8, §9: Strict Phase B gate guard.
        // Synthetic ingestion allowed in prototype. Production/live ingestion locked.
        $gateService->assertIngestionAllowed($snapshot->is_synthetic, $snapshot->filename);

        $snapshot->update(['status' => 'processing']);

        foreach ($this->parsedRows as $rowItem) {
            $sourceRow = SourceRow::create([
                'snapshot_id' => $snapshot->id,
                'sheet_name' => $rowItem['sheet_name'],
                'row_number' => $rowItem['row_number'],
                'cell_coordinates' => $rowItem['cell_coordinates'] ?? null,
                'raw_values' => $rowItem['raw_values'],
                'formula_text' => $rowItem['formula_text'] ?? null,
                'cached_values' => $rowItem['cached_values'] ?? null,
                'is_hidden' => $rowItem['is_hidden'] ?? false,
                'parse_warnings' => $rowItem['parse_warnings'] ?? null,
            ]);

            $workbenchService->analyzeRow($sourceRow);
        }

        $snapshot->update(['status' => 'completed']);
    }
}
