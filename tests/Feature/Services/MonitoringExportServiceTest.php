<?php

declare(strict_types=1);

use App\Enums\ExportType;
use App\Enums\Permission;
use App\Models\AuditEvent;
use App\Models\Partner;
use App\Models\User;
use App\Services\MonitoringExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('MonitoringExportService (PRD §5 FR-14, DEC-004, Finding #16)', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->service = app(MonitoringExportService::class);

        $this->authorizedUser = User::factory()->operator()->create();
        $this->authorizedUser->grantPermission(Permission::SensitiveExport);

        $this->unauthorizedUser = User::factory()->viewer()->create();
    });

    it('rejects export creation for users lacking SensitiveExport permission', function () {
        expect(fn () => $this->service->createExportJob(
            $this->unauthorizedUser,
            ExportType::PartnerLedger,
            'all'
        ))->toThrow(AuthorizationException::class);
    });

    it('creates and executes export job with SHA-256 checksum and audit event', function () {
        Partner::factory()->count(3)->create();

        $job = $this->service->createExportJob(
            $this->authorizedUser,
            ExportType::PartnerLedger,
            'all'
        );

        expect($job->status)->toBe('pending');

        $this->service->executeJob($job);

        $job->refresh();

        expect($job->status)->toBe('completed')
            ->and($job->file_path)->not->toBeNull()
            ->and($job->file_checksum)->not->toBeNull()
            ->and($job->record_count)->toBe(3);

        Storage::disk('local')->assertExists($job->file_path);

        $audit = AuditEvent::where('action', 'export')->first();
        expect($audit)->not->toBeNull()
            ->and($audit->actor_id)->toBe($this->authorizedUser->id)
            ->and($audit->delta['record_count'])->toBe(3);
    });

    it('allows authorized user to stream download export file and logs download audit', function () {
        $job = $this->service->createExportJob(
            $this->authorizedUser,
            ExportType::PartnerLedger,
            'all'
        );

        $this->service->executeJob($job);

        $response = $this->service->streamDownload($job, $this->authorizedUser);

        expect($response->getStatusCode())->toBe(200);

        $downloadAudit = AuditEvent::where('action', 'export_download')->first();
        expect($downloadAudit)->not->toBeNull()
            ->and($downloadAudit->actor_id)->toBe($this->authorizedUser->id);
    });
});
