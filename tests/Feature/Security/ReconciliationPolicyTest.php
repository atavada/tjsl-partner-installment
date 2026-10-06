<?php

declare(strict_types=1);

use App\Enums\DiscrepancyType;
use App\Enums\ExportType;
use App\Enums\Permission;
use App\Enums\ReconciliationStatus;
use App\Models\ExportJob;
use App\Models\ReconciliationCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

describe('Reconciliation & Export Policy Gates (TASK-REM-010, Findings #7, #16)', function () {
    beforeEach(function () {
        $this->viewer = User::factory()->viewer()->create();

        $this->operator = User::factory()->operator()->create();
        $this->operator->grantPermission(Permission::MatchPropose);

        $this->reviewer = User::factory()->reconciliationReviewer()->create();
        $this->reviewer->grantPermission(Permission::SensitiveExport);

        $this->case = ReconciliationCase::create([
            'case_number' => 'REC-POL-001',
            'case_type' => 'unmatched_deposit',
            'discrepancy_type' => DiscrepancyType::UnmatchedDeposit,
            'status' => ReconciliationStatus::Unreviewed,
        ]);

        $this->exportJob = ExportJob::create([
            'user_id' => $this->reviewer->id,
            'export_type' => ExportType::ReconciliationDifferences,
            'scope' => 'all',
            'status' => 'completed',
        ]);
    });

    it('denies viewer from proposing match, resolving, or approving reconciliation cases', function () {
        expect(Gate::forUser($this->viewer)->allows('proposeMatch', $this->case))->toBeFalse()
            ->and(Gate::forUser($this->viewer)->allows('resolve', $this->case))->toBeFalse()
            ->and(Gate::forUser($this->viewer)->allows('approve', $this->case))->toBeFalse();
    });

    it('allows operator to propose match but denies approval', function () {
        expect(Gate::forUser($this->operator)->allows('proposeMatch', $this->case))->toBeTrue()
            ->and(Gate::forUser($this->operator)->allows('approve', $this->case))->toBeFalse();
    });

    it('allows reconciliation reviewer to propose match, resolve, and approve reconciliation cases', function () {
        expect(Gate::forUser($this->reviewer)->allows('proposeMatch', $this->case))->toBeTrue()
            ->and(Gate::forUser($this->reviewer)->allows('resolve', $this->case))->toBeTrue()
            ->and(Gate::forUser($this->reviewer)->allows('approve', $this->case))->toBeTrue();
    });

    it('enforces SensitiveExport permission on ExportJob operations', function () {
        expect(Gate::forUser($this->viewer)->allows('viewAny', ExportJob::class))->toBeFalse()
            ->and(Gate::forUser($this->viewer)->allows('create', ExportJob::class))->toBeFalse()
            ->and(Gate::forUser($this->reviewer)->allows('viewAny', ExportJob::class))->toBeTrue()
            ->and(Gate::forUser($this->reviewer)->allows('download', $this->exportJob))->toBeTrue();
    });
});
