<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\MonitoringExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $exportJobId,
    ) {}

    public function handle(MonitoringExportService $exportService): void
    {
        $exportJob = ExportJob::findOrFail($this->exportJobId);

        try {
            $exportService->executeJob($exportJob);
        } catch (Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
