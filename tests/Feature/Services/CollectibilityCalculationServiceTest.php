<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\CollectibilityStatus;
use App\Models\Agreement;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Services\CollectibilityCalculationService;
use App\Services\ScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CollectibilityCalculationService (DEC-007, Collectibility Label v1)', function () {
    it('evaluates timeline progression across DEC-007 bands for unpaid schedules', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 20_000_000,
            'interest_amount' => 1_200_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 21_300_000,
            'tenor_months' => 12,
            'first_due_date' => '2026-02-01',
        ]);

        app(ScheduleGeneratorService::class)->generateForAgreement($agreement, persist: true);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);

        // Before first due date: 0 late months -> Lancar
        $resBefore = $service->calculate($agreement, Carbon::parse('2026-01-15'));
        expect($resBefore['collectibility_status'])->toBe(CollectibilityStatus::Lancar)
            ->and($resBefore['late_months'])->toBe(0)
            ->and($resBefore['status'])->toBe('lancar')
            ->and($resBefore['label'])->toBe('Lancar');

        // On first due date: 0 late months -> Lancar
        $resOnDue = $service->calculate($agreement, Carbon::parse('2026-02-01'));
        expect($resOnDue['collectibility_status'])->toBe(CollectibilityStatus::Lancar)
            ->and($resOnDue['late_months'])->toBe(0);

        // 1 month elapsed (2026-03-01): 1 late month -> Lancar (band 0-1)
        $res1m = $service->calculate($agreement, Carbon::parse('2026-03-01'));
        expect($res1m['collectibility_status'])->toBe(CollectibilityStatus::Lancar)
            ->and($res1m['late_months'])->toBe(1);

        // 2 months elapsed (2026-04-01): 2 late months -> Kurang Lancar (band 2-6)
        $res2m = $service->calculate($agreement, Carbon::parse('2026-04-01'));
        expect($res2m['collectibility_status'])->toBe(CollectibilityStatus::KurangLancar)
            ->and($res2m['late_months'])->toBe(2)
            ->and($res2m['label'])->toBe('Kurang Lancar');

        // 6 months elapsed (2026-08-01): 6 late months -> Kurang Lancar (band 2-6)
        $res6m = $service->calculate($agreement, Carbon::parse('2026-08-01'));
        expect($res6m['collectibility_status'])->toBe(CollectibilityStatus::KurangLancar)
            ->and($res6m['late_months'])->toBe(6);

        // 7 months elapsed (2026-09-01): 7 late months -> Diragukan (band 7-9)
        $res7m = $service->calculate($agreement, Carbon::parse('2026-09-01'));
        expect($res7m['collectibility_status'])->toBe(CollectibilityStatus::Diragukan)
            ->and($res7m['late_months'])->toBe(7)
            ->and($res7m['label'])->toBe('Diragukan');

        // 9 months elapsed (2026-11-01): 9 late months -> Diragukan (band 7-9)
        $res9m = $service->calculate($agreement, Carbon::parse('2026-11-01'));
        expect($res9m['collectibility_status'])->toBe(CollectibilityStatus::Diragukan)
            ->and($res9m['late_months'])->toBe(9);

        // 10 months elapsed (2026-12-01): 10 late months -> Bermasalah (>9)
        $res10m = $service->calculate($agreement, Carbon::parse('2026-12-01'));
        expect($res10m['collectibility_status'])->toBe(CollectibilityStatus::Bermasalah)
            ->and($res10m['late_months'])->toBe(10)
            ->and($res10m['label'])->toBe('Bermasalah');
    });

    it('advances qualifying unpaid installment when prior installments are fully cleared', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 10_000_000,
            'interest_amount' => 600_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 10_650_000,
            'tenor_months' => 6,
            'first_due_date' => '2026-02-01',
        ]);

        app(ScheduleGeneratorService::class)->generateForAgreement($agreement, persist: true);

        // Fully pay installment 1
        $schedule1 = InstallmentSchedule::where('agreement_id', $agreement->id)
            ->where('installment_number', 1)
            ->firstOrFail();

        $schedule1->update([
            'principal_paid' => $schedule1->principal_due,
            'interest_paid' => $schedule1->interest_due,
            'admin_charge_paid' => $schedule1->admin_charge_due,
            'total_paid' => $schedule1->total_due,
            'status' => 'paid',
        ]);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);

        // On 2026-04-01:
        // Installment 1 (due 2026-02-01) is paid.
        // Oldest unpaid is Installment 2 (due 2026-03-01).
        // Months elapsed from 2026-03-01 to 2026-04-01 is 1 month -> Lancar (not 2 months / Kurang Lancar)
        $res = $service->calculate($agreement, Carbon::parse('2026-04-01'));

        expect($res['collectibility_status'])->toBe(CollectibilityStatus::Lancar)
            ->and($res['late_months'])->toBe(1)
            ->and($res['qualifying_installment']['installment_number'])->toBe(2);
    });

    it('returns Lunas override when agreement has zero balance and verified data', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 0,
        ]);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);
        $res = $service->calculate($agreement, Carbon::parse('2026-05-01'));

        expect($res['collectibility_status'])->toBe(CollectibilityStatus::Lunas)
            ->and($res['is_lunas'])->toBeTrue()
            ->and($res['status'])->toBe('lunas')
            ->and($res['label'])->toBe('LUNAS');
    });

    it('guards against unverified data: unverified NEVER returns Lunas (formula-specification §9.4)', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::Unknown,
            'principal_amount' => 0,
            'total_amount' => 0,
        ]);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);
        $res = $service->calculate($agreement, Carbon::parse('2026-05-01'));

        expect($res['collectibility_status'])->toBe(CollectibilityStatus::Unknown)
            ->and($res['is_lunas'])->toBeFalse()
            ->and($res['status'])->toBe('unknown')
            ->and($res['label'])->toBe('Tidak Diketahui');
    });

    it('returns Unknown for draft agreements (creates no debt per PRD FR-02)', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'principal_amount' => 20_000_000,
            'total_amount' => 21_300_000,
        ]);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);
        $res = $service->calculate($agreement, Carbon::parse('2026-05-01'));

        expect($res['collectibility_status'])->toBe(CollectibilityStatus::Unknown)
            ->and($res['status'])->toBe('unknown');
    });

    it('synchronizes computed collectibility status to agreement column', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 10_000_000,
            'interest_amount' => 600_000,
            'admin_charge_amount' => 50_000,
            'total_amount' => 10_650_000,
            'tenor_months' => 12,
            'first_due_date' => '2026-02-01',
            'collectibility_status' => CollectibilityStatus::Unknown,
        ]);

        app(ScheduleGeneratorService::class)->generateForAgreement($agreement, persist: true);

        /** @var CollectibilityCalculationService $service */
        $service = app(CollectibilityCalculationService::class);

        // Evaluate on 2026-04-01 -> 2 late months -> Kurang Lancar
        $syncedStatus = $service->sync($agreement, Carbon::parse('2026-04-01'));

        expect($syncedStatus)->toBe(CollectibilityStatus::KurangLancar)
            ->and($agreement->fresh()->collectibility_status)->toBe(CollectibilityStatus::KurangLancar);
    });
});
