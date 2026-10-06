<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\ExportJob;
use App\Models\Partner;
use App\Models\ReconciliationCase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringExportService
{
    public const DISK = 'local';

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Create and stage an export job with permission check (PRD §5 FR-14, DEC-004, DEC-009).
     *
     * @throws AuthorizationException
     */
    public function createExportJob(
        User $user,
        ExportType $type,
        string $scope,
        array $filters = []
    ): ExportJob {
        if (! $user->hasPermission(Permission::SensitiveExport)) {
            throw new AuthorizationException('Unauthorized: SensitiveExport permission required.');
        }

        return ExportJob::create([
            'user_id' => $user->id,
            'export_type' => $type,
            'scope' => $scope,
            'filter_criteria' => $filters,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);
    }

    /**
     * Execute and generate the export file.
     */
    public function executeJob(ExportJob $job): void
    {
        $job->update(['status' => 'processing']);

        $filename = "exports/{$job->id}.csv";
        $csvContent = '';
        $recordCount = 0;

        switch ($job->export_type) {
            case ExportType::ReconciliationDifferences:
                $cases = ReconciliationCase::with(['sourceRow', 'agreement'])->get();
                $recordCount = $cases->count();
                $csvContent .= "case_number,case_type,discrepancy_type,status,evidence\n";
                foreach ($cases as $c) {
                    $csvContent .= sprintf(
                        "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                        $c->case_number,
                        $c->case_type,
                        $c->discrepancy_type->value,
                        $c->status->value,
                        str_replace('"', '""', (string) $c->evidence)
                    );
                }
                break;

            case ExportType::PartnerLedger:
            case ExportType::MonitoringMonthly:
            case ExportType::AuditExtract:
            default:
                $partners = Partner::with('agreements')->limit(500)->get();
                $recordCount = $partners->count();
                $csvContent .= "partner_no_id,name,agreement_count\n";
                foreach ($partners as $p) {
                    $csvContent .= sprintf(
                        "\"%s\",\"%s\",%d\n",
                        $p->partner_no_id,
                        str_replace('"', '""', $p->name),
                        $p->agreements->count()
                    );
                }
                break;
        }

        Storage::disk(self::DISK)->put($filename, $csvContent);
        $checksum = hash('sha256', $csvContent);

        $job->update([
            'file_path' => $filename,
            'file_checksum' => $checksum,
            'record_count' => $recordCount,
            'status' => 'completed',
        ]);

        // Audit log export action (PRD §4, FR-06)
        $this->auditService->logExport(
            scope: $job->scope,
            count: $recordCount,
            actor: $job->user,
            reason: "Generated export for type [{$job->export_type->value}]"
        );
    }

    /**
     * Stream download for authorized user.
     *
     * @throws AuthorizationException
     */
    public function streamDownload(ExportJob $job, User $actor): StreamedResponse
    {
        if (! $actor->hasPermission(Permission::SensitiveExport)) {
            throw new AuthorizationException('Unauthorized: SensitiveExport permission required.');
        }

        if ($job->status !== 'completed' || $job->file_path === null || ! Storage::disk(self::DISK)->exists($job->file_path)) {
            abort(404, 'Export file not found or not yet completed.');
        }

        $this->auditService->log(
            action: 'export_download',
            target: $job,
            delta: [
                'job_id' => $job->id,
                'export_type' => $job->export_type->value,
                'record_count' => $job->record_count,
            ],
            reason: 'User downloaded exported file',
            actor: $actor
        );

        return Storage::disk(self::DISK)->download($job->file_path, "export-{$job->export_type->value}.csv");
    }
}
