<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\CollectibilityStatus;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\VirtualAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

describe('Agreement model schema and DEC-001 grouping key', function () {
    it('creates an agreement with UUID primary key', function () {
        $agreement = Agreement::factory()->create();

        expect($agreement->id)->toBeString();
        expect(Str::isUuid($agreement->id))->toBeTrue();
    });

    it('preserves leading zeros and punctuation in agreement_number', function () {
        $agreement = Agreement::factory()->create([
            'agreement_number' => '0012/PUMK/2026',
            'agreement_number_normalized' => Agreement::normalizeAgreementNumber('0012/PUMK/2026'),
        ]);

        $retrieved = Agreement::find($agreement->id);

        expect($retrieved->agreement_number)->toBe('0012/PUMK/2026');
        expect($retrieved->agreement_number_normalized)->toBe('0012/PUMK/2026');
    });

    it('allows duplicate agreement numbers for the same partner (DEC-001 resolved)', function () {
        $partner = Partner::factory()->create();

        $first = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'agreement_number' => '0045/SP-TJSL/2026',
            'agreement_number_normalized' => '0045/SP-TJSL/2026',
        ]);

        $second = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'agreement_number' => '0045/SP-TJSL/2026',
            'agreement_number_normalized' => '0045/SP-TJSL/2026',
        ]);

        expect($first->id)->not->toBe($second->id);
        expect($first->agreement_number)->toBe($second->agreement_number);
        expect(Agreement::where('partner_id', $partner->id)->where('agreement_number_normalized', '0045/SP-TJSL/2026')->count())->toBe(2);
    });

    it('allows duplicate agreement numbers across different partners (DEC-001 grouping key)', function () {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();

        $agreementA = Agreement::factory()->create([
            'partner_id' => $partnerA->id,
            'agreement_number' => 'BATCH-2026-GROUP-1',
            'agreement_number_normalized' => 'BATCH-2026-GROUP-1',
        ]);

        $agreementB = Agreement::factory()->create([
            'partner_id' => $partnerB->id,
            'agreement_number' => 'BATCH-2026-GROUP-1',
            'agreement_number_normalized' => 'BATCH-2026-GROUP-1',
        ]);

        expect($agreementA->partner_id)->not->toBe($agreementB->partner_id);
        expect($agreementA->agreement_number)->toBe($agreementB->agreement_number);
    });

    it('stores integer minor units IDR for financial amounts', function () {
        $agreement = Agreement::factory()->create([
            'principal_amount' => 25_000_000,
            'interest_amount' => 1_500_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 26_600_000,
        ]);

        $retrieved = Agreement::find($agreement->id);

        expect($retrieved->principal_amount)->toBe(25_000_000);
        expect($retrieved->interest_amount)->toBe(1_500_000);
        expect($retrieved->admin_charge_amount)->toBe(100_000);
        expect($retrieved->total_amount)->toBe(26_600_000);
    });
});

describe('Distinct identifier fields (gate test)', function () {
    it('stores NO ID, NIK, VA, agreement number, and row number as distinct fields', function () {
        $partner = Partner::factory()->create([
            'partner_no_id' => '0001234567',
            'nik' => '3578012345670001',
        ]);

        $va = VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => '880123456789',
        ]);

        $agreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'agreement_number' => '0012/PUMK/2026',
            'source_row_number' => 42,
        ]);

        expect($partner->partner_no_id)->toBe('0001234567');
        expect($partner->nik)->toBe('3578012345670001');
        expect($va->va_number)->toBe('880123456789');
        expect($agreement->agreement_number)->toBe('0012/PUMK/2026');
        expect($agreement->source_row_number)->toBe(42);

        // Every identifier is distinct from the others
        $identifiers = [
            $partner->partner_no_id,
            $partner->nik,
            $va->va_number,
            $agreement->agreement_number,
            (string) $agreement->source_row_number,
        ];

        expect(array_unique($identifiers))->toHaveCount(5);
    });
});

describe('Three independent status dimensions and balance invariance (gate test)', function () {
    it('ensures lifecycle, collectibility, and signing are independent dimensions', function () {
        $agreement = Agreement::factory()->create([
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'collectibility_status' => CollectibilityStatus::Unknown,
            'signing_status' => AgreementSigningStatus::NotPrepared,
        ]);

        // Change lifecycle: collectibility and signing untouched
        $agreement->update(['lifecycle_status' => AgreementLifecycleStatus::Active]);
        $agreement->refresh();
        expect($agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);
        expect($agreement->collectibility_status)->toBe(CollectibilityStatus::Unknown);
        expect($agreement->signing_status)->toBe(AgreementSigningStatus::NotPrepared);

        // Change signing: lifecycle and collectibility untouched
        $agreement->update(['signing_status' => AgreementSigningStatus::Signed]);
        $agreement->refresh();
        expect($agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);
        expect($agreement->collectibility_status)->toBe(CollectibilityStatus::Unknown);
        expect($agreement->signing_status)->toBe(AgreementSigningStatus::Signed);

        // Change collectibility: lifecycle and signing untouched
        $agreement->update(['collectibility_status' => CollectibilityStatus::Lancar]);
        $agreement->refresh();
        expect($agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);
        expect($agreement->collectibility_status)->toBe(CollectibilityStatus::Lancar);
        expect($agreement->signing_status)->toBe(AgreementSigningStatus::Signed);
    });

    it('status changes (signing/lifecycle) do not change financial balances', function () {
        $agreement = Agreement::factory()->create([
            'principal_amount' => 15_000_000,
            'interest_amount' => 900_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 16_000_000,
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'signing_status' => AgreementSigningStatus::NotPrepared,
        ]);

        // Transition status multiple times
        $agreement->update(['lifecycle_status' => AgreementLifecycleStatus::Active]);
        $agreement->update(['signing_status' => AgreementSigningStatus::Signed]);
        $agreement->update(['lifecycle_status' => AgreementLifecycleStatus::PaidOff]);
        $agreement->refresh();

        expect($agreement->principal_amount)->toBe(15_000_000);
        expect($agreement->interest_amount)->toBe(900_000);
        expect($agreement->admin_charge_amount)->toBe(100_000);
        expect($agreement->other_charge_amount)->toBe(0);
        expect($agreement->total_amount)->toBe(16_000_000);
    });
});

describe('closed_by_rescheduling is not paid_off (gate test)', function () {
    it('distinguishes rescheduling closure from paid_off', function () {
        $rescheduled = Agreement::factory()->closedByRescheduling()->create();
        $paidOff = Agreement::factory()->paidOff()->create();

        expect($rescheduled->isClosedByRescheduling())->toBeTrue();
        expect($rescheduled->isPaidOff())->toBeFalse();

        expect($paidOff->isPaidOff())->toBeTrue();
        expect($paidOff->isClosedByRescheduling())->toBeFalse();

        // Querying paid off agreements never returns rescheduled agreements
        $paidOffIds = Agreement::where('lifecycle_status', AgreementLifecycleStatus::PaidOff)->pluck('id');
        expect($paidOffIds)->toContain($paidOff->id);
        expect($paidOffIds)->not->toContain($rescheduled->id);
    });
});

describe('completed (Selesai) status distinct from paid_off (Lunas) per DEC-002', function () {
    it('exposes exactly all 7 confirmed business labels', function () {
        $expectedLabels = [
            'draft' => 'Draft',
            'active' => 'Aktif',
            'paid_off' => 'Lunas',
            'completed' => 'Selesai',
            'closed_by_rescheduling' => 'Ditutup karena Rescheduling',
            'cancelled' => 'Dibatalkan',
            'unknown' => 'Tidak Diketahui',
        ];

        expect(count(AgreementLifecycleStatus::cases()))->toBe(7);

        foreach ($expectedLabels as $statusValue => $expectedLabel) {
            $status = AgreementLifecycleStatus::from($statusValue);
            expect($status->label())->toBe($expectedLabel);
        }
    });

    it('distinguishes contractual completion from financial payoff', function () {
        $completed = Agreement::factory()->completed()->create();
        $paidOff = Agreement::factory()->paidOff()->create();

        expect($completed->isCompleted())->toBeTrue();
        expect($completed->isPaidOff())->toBeFalse();
        expect($completed->lifecycle_status->isClosed())->toBeTrue();
        expect($completed->lifecycle_status->label())->toBe('Selesai');

        expect($paidOff->isPaidOff())->toBeTrue();
        expect($paidOff->isCompleted())->toBeFalse();
        expect($paidOff->lifecycle_status->isClosed())->toBeTrue();
        expect($paidOff->lifecycle_status->label())->toBe('Lunas');

        $completedIds = Agreement::where('lifecycle_status', AgreementLifecycleStatus::Completed)->pluck('id');
        expect($completedIds)->toContain($completed->id);
        expect($completedIds)->not->toContain($paidOff->id);
    });
});

describe('InstallmentSchedule schema and calculation guard (DEC-008)', function () {
    it('creates installment schedule with integer IDR components', function () {
        $agreement = Agreement::factory()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 1_000_000,
            'interest_due' => 60_000,
            'admin_charge_due' => 10_000,
            'other_charge_due' => 0,
            'total_due' => 1_070_000,
        ]);

        expect($schedule->installment_number)->toBe(1);
        expect($schedule->principal_due)->toBe(1_000_000);
        expect($schedule->total_due)->toBe(1_070_000);
        expect($schedule->status)->toBe('pending');
        expect($schedule->is_calculated)->toBeFalse();
    });

    it('enforces unique constraint on agreement_id and installment_number', function () {
        $agreement = Agreement::factory()->create();

        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
        ]);

        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
        ]);
    })->throws(QueryException::class);

    it('computes installment schedule outstanding amount per DEC-008', function () {
        $schedule = InstallmentSchedule::factory()->create([
            'principal_due' => 800_000,
            'interest_due' => 150_000,
            'admin_charge_due' => 50_000,
            'other_charge_due' => 0,
            'total_due' => 1_000_000,
            'principal_paid' => 400_000,
            'interest_paid' => 150_000,
            'admin_charge_paid' => 50_000,
            'other_charge_paid' => 0,
            'total_paid' => 600_000,
        ]);

        expect($schedule->calculateOutstanding())->toBe(400_000);
        expect($schedule->calculateComponentOutstanding())->toBe([
            'principal' => 400_000,
            'interest' => 0,
            'admin_charge' => 0,
            'other_charge' => 0,
            'total' => 400_000,
        ]);
    });
});

describe('Domain enums accounting terms and UI labels', function () {
    it('uses confirmed Indonesian terms and UI labels for CollectibilityStatus per DEC-007', function () {
        expect(CollectibilityStatus::Lancar->value)->toBe('lancar');
        expect(CollectibilityStatus::Lancar->label())->toBe('Lancar');

        expect(CollectibilityStatus::KurangLancar->value)->toBe('kurang_lancar');
        expect(CollectibilityStatus::KurangLancar->label())->toBe('Kurang Lancar');

        expect(CollectibilityStatus::Diragukan->value)->toBe('diragukan');
        expect(CollectibilityStatus::Diragukan->label())->toBe('Diragukan');

        expect(CollectibilityStatus::Bermasalah->value)->toBe('bermasalah');
        expect(CollectibilityStatus::Bermasalah->label())->toBe('Bermasalah');

        expect(CollectibilityStatus::Lunas->value)->toBe('lunas');
        expect(CollectibilityStatus::Lunas->label())->toBe('LUNAS');

        expect(CollectibilityStatus::Unknown->value)->toBe('unknown');
        expect(CollectibilityStatus::Unknown->label())->toBe('Tidak Diketahui');
    });

    it('uses English terms with Indonesian UI labels for SignatureSummary', function () {
        expect(SignatureSummary::Unsigned->value)->toBe('unsigned');
        expect(SignatureSummary::Unsigned->label())->toBe('Belum TTD');

        expect(SignatureSummary::Signed->value)->toBe('signed');
        expect(SignatureSummary::Signed->label())->toBe('Sudah TTD');

        expect(SignatureSummary::Unknown->value)->toBe('unknown');
        expect(SignatureSummary::Unknown->label())->toBe('Tidak Diketahui');
    });
});
