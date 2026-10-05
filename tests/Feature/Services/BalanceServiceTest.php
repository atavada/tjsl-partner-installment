<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use App\Models\ReceivableAdjustment;
use App\Services\BalanceService;
use Carbon\Carbon;

describe('BalanceService::getBalance per DEC-008 (Formula Implementation)', function () {
    beforeEach(function () {
        $this->service = app(BalanceService::class);
    });

    it('returns contract amounts as remaining when agreement has no payments', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 20_000_000,
            'interest_amount' => 1_200_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 21_300_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['status'])->toBe('computed')
            ->and($balance['label'])->toBe('Terhitung')
            ->and($balance['is_draft'])->toBeFalse()
            ->and($balance['creates_debt'])->toBeTrue()
            ->and($balance['data_verified'])->toBeTrue()
            ->and($balance['is_lunas'])->toBeFalse()
            ->and($balance['principal_remaining'])->toBe(20_000_000)
            ->and($balance['interest_remaining'])->toBe(1_200_000)
            ->and($balance['admin_charge_remaining'])->toBe(100_000)
            ->and($balance['other_charge_remaining'])->toBe(0)
            ->and($balance['charge_remaining'])->toBe(1_300_000)
            ->and($balance['total_remaining'])->toBe(21_300_000)
            ->and($balance['contract_principal'])->toBe(20_000_000)
            ->and($balance['contract_charge'])->toBe(1_300_000)
            ->and($balance['contract_total'])->toBe(21_300_000)
            ->and($balance['paid_principal'])->toBe(0)
            ->and($balance['paid_charge'])->toBe(0)
            ->and($balance['paid_total'])->toBe(0)
            ->and($balance['adjustment_principal'])->toBe(0)
            ->and($balance['adjustment_charge'])->toBe(0)
            ->and($balance['adjustment_total'])->toBe(0)
            ->and($balance['included_event_ids'])->toBeEmpty()
            ->and($balance['warnings'])->toBeEmpty()
            ->and($balance['rule_version'])->toBe('DEC-008-v1')
            ->and($balance['as_of'])->toBeString();
    });

    it('computes correct remaining balance with posted payment allocations', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'interest_amount' => 600_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 10_700_000,
        ]);

        $alloc1 = PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 1_150_000])->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => 1_000_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_150_000,
        ]);

        $alloc2 = PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 2_100_000])->id,
            'effective_date' => '2026-03-20',
            'principal_amount' => 2_000_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_100_000,
        ]);

        // As of 2026-03-15: only alloc1 is included
        $balanceMid = $this->service->getBalance($agreement, Carbon::parse('2026-03-15'));

        expect($balanceMid['principal_remaining'])->toBe(9_000_000)
            ->and($balanceMid['interest_remaining'])->toBe(500_000)
            ->and($balanceMid['admin_charge_remaining'])->toBe(50_000)
            ->and($balanceMid['charge_remaining'])->toBe(550_000)
            ->and($balanceMid['total_remaining'])->toBe(9_550_000)
            ->and($balanceMid['paid_principal'])->toBe(1_000_000)
            ->and($balanceMid['paid_charge'])->toBe(150_000)
            ->and($balanceMid['paid_total'])->toBe(1_150_000)
            ->and($balanceMid['included_event_ids'])->toContain($alloc1->id)
            ->and($balanceMid['included_event_ids'])->not->toContain($alloc2->id);

        // As of 2026-03-25: both alloc1 and alloc2 are included
        $balanceEnd = $this->service->getBalance($agreement, Carbon::parse('2026-03-25'));

        expect($balanceEnd['principal_remaining'])->toBe(7_000_000)
            ->and($balanceEnd['interest_remaining'])->toBe(400_000)
            ->and($balanceEnd['admin_charge_remaining'])->toBe(50_000)
            ->and($balanceEnd['charge_remaining'])->toBe(450_000)
            ->and($balanceEnd['total_remaining'])->toBe(7_450_000)
            ->and($balanceEnd['paid_principal'])->toBe(3_000_000)
            ->and($balanceEnd['paid_charge'])->toBe(250_000)
            ->and($balanceEnd['paid_total'])->toBe(3_250_000)
            ->and($balanceEnd['included_event_ids'])->toContain($alloc1->id)
            ->and($balanceEnd['included_event_ids'])->toContain($alloc2->id);
    });

    it('handles reversals as separately dated events (temporal consistency)', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 10_000_000,
        ]);

        $originalAlloc = PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'effective_date' => '2026-03-01',
            'principal_amount' => 2_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_000_000,
            'state' => PaymentState::Reversed, // marked reversed
        ]);

        $reversalAlloc = PaymentAllocation::factory()->create([
            'agreement_id' => $agreement->id,
            'reversal_of_id' => $originalAlloc->id,
            'effective_date' => '2026-03-15',
            'principal_amount' => 2_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_000_000,
            'state' => PaymentState::Reversed,
        ]);

        // Balance as of 2026-03-10 (before reversal): payment is still counted
        $balanceBefore = $this->service->getBalance($agreement, Carbon::parse('2026-03-10'));

        expect($balanceBefore['principal_remaining'])->toBe(8_000_000)
            ->and($balanceBefore['total_remaining'])->toBe(8_000_000)
            ->and($balanceBefore['paid_principal'])->toBe(2_000_000)
            ->and($balanceBefore['included_event_ids'])->toContain($originalAlloc->id);

        // Balance as of 2026-03-20 (after reversal): payment is effectively reversed and excluded
        $balanceAfter = $this->service->getBalance($agreement, Carbon::parse('2026-03-20'));

        expect($balanceAfter['principal_remaining'])->toBe(10_000_000)
            ->and($balanceAfter['total_remaining'])->toBe(10_000_000)
            ->and($balanceAfter['paid_principal'])->toBe(0)
            ->and($balanceAfter['included_event_ids'])->not->toContain($originalAlloc->id)
            ->and($balanceAfter['included_event_ids'])->not->toContain($reversalAlloc->id);
    });

    it('adds posted receivable adjustments to remaining balance', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'interest_amount' => 500_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 10_500_000,
        ]);

        $adj = ReceivableAdjustment::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => 500_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 550_000,
        ]);

        // Before adjustment effective date: not included
        $balanceBefore = $this->service->getBalance($agreement, Carbon::parse('2026-03-05'));
        expect($balanceBefore['principal_remaining'])->toBe(10_000_000)
            ->and($balanceBefore['total_remaining'])->toBe(10_500_000)
            ->and($balanceBefore['adjustment_total'])->toBe(0)
            ->and($balanceBefore['included_event_ids'])->not->toContain($adj->id);

        // After adjustment effective date: included
        $balanceAfter = $this->service->getBalance($agreement, Carbon::parse('2026-03-15'));
        expect($balanceAfter['principal_remaining'])->toBe(10_500_000)
            ->and($balanceAfter['interest_remaining'])->toBe(550_000)
            ->and($balanceAfter['total_remaining'])->toBe(11_050_000)
            ->and($balanceAfter['adjustment_principal'])->toBe(500_000)
            ->and($balanceAfter['adjustment_charge'])->toBe(50_000)
            ->and($balanceAfter['adjustment_total'])->toBe(550_000)
            ->and($balanceAfter['included_event_ids'])->toContain($adj->id);
    });

    it('handles signed (negative) receivable adjustments', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'interest_amount' => 500_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 10_500_000,
        ]);

        $adj = ReceivableAdjustment::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => -1_000_000,
            'interest_amount' => -100_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => -1_100_000,
        ]);

        $balance = $this->service->getBalance($agreement, Carbon::parse('2026-03-15'));

        expect($balance['principal_remaining'])->toBe(9_000_000)
            ->and($balance['interest_remaining'])->toBe(400_000)
            ->and($balance['total_remaining'])->toBe(9_400_000)
            ->and($balance['adjustment_principal'])->toBe(-1_000_000)
            ->and($balance['adjustment_charge'])->toBe(-100_000)
            ->and($balance['adjustment_total'])->toBe(-1_100_000);
    });

    it('identifies draft agreements as creating no debt (PRD FR-02)', function () {
        $draftAgreement = Agreement::factory()->create([
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'principal_amount' => 15_000_000,
            'total_amount' => 16_000_000,
        ]);

        $balance = $this->service->getBalance($draftAgreement);

        expect($balance['is_draft'])->toBeTrue()
            ->and($balance['creates_debt'])->toBeFalse()
            ->and($balance['status'])->toBe('draft')
            ->and($balance['principal_remaining'])->toBe('unverified')
            ->and($balance['total_remaining'])->toBe('unverified')
            ->and($balance['warnings'])->toContain(
                'Perjanjian draft belum aktif dan tidak menimbulkan kewajiban piutang (PRD FR-02).'
            );
    });

    it('surfaces negative remaining as exception status without flooring to zero (DEC-008)', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 5_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 5_000_000,
        ]);

        // Overpayment allocation: 6M paid on 5M debt
        PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 6_000_000])->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => 6_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 6_000_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['status'])->toBe('exception')
            ->and($balance['label'])->toBe('Pengecualian Saldo (Data Error)')
            ->and($balance['principal_remaining'])->toBe(-1_000_000)
            ->and($balance['total_remaining'])->toBe(-1_000_000)
            ->and($balance['principal_remaining'])->not->toBe(0)
            ->and($balance['is_lunas'])->toBeFalse()
            ->and($balance['warnings'])->toContain(
                'Terdeteksi saldo negatif pada komponen piutang (data error). Saldo tidak di-floor ke 0 (DEC-008).'
            );
    });

    it('surfaces exception status when an individual component is negative despite positive total (TASK-REM-004)', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 5_000_000,
            'interest_amount' => 200_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 5_250_000,
        ]);

        // Overpayment on interest component specifically: 300k interest paid on 200k contract
        PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 1_300_000])->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => 1_000_000,
            'interest_amount' => 300_000, // Negative remaining: 200k - 300k = -100k
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_300_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['status'])->toBe('exception')
            ->and($balance['interest_remaining'])->toBe(-100_000)
            ->and($balance['principal_remaining'])->toBe(4_000_000)
            ->and($balance['total_remaining'])->toBe(3_950_000) // Total is positive, but component is negative!
            ->and($balance['warnings'])->toContain(
                'Terdeteksi saldo negatif pada komponen piutang (data error). Saldo tidak di-floor ke 0 (DEC-008).'
            );
    });

    it('surfaces exception status when linked schedule has overpaid components (TASK-REM-004)', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 5_000_000,
            'interest_amount' => 200_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 5_250_000,
        ]);

        // Create corrupted installment schedule
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 500_000,
            'interest_due' => 20_000,
            'admin_charge_due' => 5_000,
            'other_charge_due' => 0,
            'total_due' => 525_000,
            'principal_paid' => 500_000,
            'interest_paid' => 50_000, // 50k paid > 20k due!
            'admin_charge_paid' => 5_000,
            'other_charge_paid' => 0,
            'total_paid' => 555_000,
            'status' => 'paid',
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['status'])->toBe('exception')
            ->and($balance['label'])->toBe('Pengecualian Saldo (Data Error)')
            ->and($balance['warnings'])->toContain(
                'Terdeteksi baris jadwal angsuran dengan pembayaran melebihi kewajiban (schedule overpayment integrity error).'
            );
    });

    it('determines LUNAS when all remaining components are zero and data is verified', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 5_000_000,
            'interest_amount' => 300_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 5_350_000,
        ]);

        PaymentAllocation::factory()->posted()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 5_350_000])->id,
            'effective_date' => '2026-03-10',
            'principal_amount' => 5_000_000,
            'interest_amount' => 300_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 5_350_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['status'])->toBe('lunas')
            ->and($balance['label'])->toBe('Lunas')
            ->and($balance['is_lunas'])->toBeTrue()
            ->and($balance['data_verified'])->toBeTrue()
            ->and($balance['principal_remaining'])->toBe(0)
            ->and($balance['interest_remaining'])->toBe(0)
            ->and($balance['admin_charge_remaining'])->toBe(0)
            ->and($balance['charge_remaining'])->toBe(0)
            ->and($balance['total_remaining'])->toBe(0);
    });

    it('guards against legacy bug: unverified agreement with zero balance never becomes Lunas', function () {
        $agreement = Agreement::factory()->create([
            'lifecycle_status' => AgreementLifecycleStatus::Unknown,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 0,
        ]);

        $balance = $this->service->getBalance($agreement, dataVerified: false);

        expect($balance['status'])->toBe('unverified')
            ->and($balance['label'])->toBe('Belum Terverifikasi')
            ->and($balance['is_lunas'])->toBeFalse()
            ->and($balance['data_verified'])->toBeFalse()
            ->and($balance['total_remaining'])->toBe(0)
            ->and($balance['warnings'])->toContain('Data perjanjian belum terverifikasi (DEC-008).');
    });

    it('excludes unposted (draft or submitted) payment allocations', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 10_000_000,
        ]);

        PaymentAllocation::factory()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 2_000_000])->id,
            'state' => PaymentState::Draft,
            'principal_amount' => 2_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_000_000,
        ]);

        PaymentAllocation::factory()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => BankTransaction::factory()->create(['amount' => 3_000_000])->id,
            'state' => PaymentState::Submitted,
            'principal_amount' => 3_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 3_000_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['paid_principal'])->toBe(0)
            ->and($balance['principal_remaining'])->toBe(10_000_000)
            ->and($balance['total_remaining'])->toBe(10_000_000)
            ->and($balance['included_event_ids'])->toBeEmpty();
    });

    it('excludes unposted (draft) receivable adjustments', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 10_000_000,
            'total_amount' => 10_000_000,
        ]);

        ReceivableAdjustment::factory()->create([
            'agreement_id' => $agreement->id,
            'state' => PaymentState::Draft,
            'principal_amount' => 1_000_000,
            'total_amount' => 1_000_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect($balance['adjustment_total'])->toBe(0)
            ->and($balance['principal_remaining'])->toBe(10_000_000)
            ->and($balance['included_event_ids'])->toBeEmpty();
    });

    it('returns integer types for all remaining and financial balance fields', function () {
        $agreement = Agreement::factory()->active()->create([
            'principal_amount' => 20_000_000,
            'interest_amount' => 1_200_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 21_300_000,
        ]);

        $balance = $this->service->getBalance($agreement);

        expect(is_int($balance['principal_remaining']))->toBeTrue()
            ->and(is_int($balance['interest_remaining']))->toBeTrue()
            ->and(is_int($balance['admin_charge_remaining']))->toBeTrue()
            ->and(is_int($balance['other_charge_remaining']))->toBeTrue()
            ->and(is_int($balance['charge_remaining']))->toBeTrue()
            ->and(is_int($balance['total_remaining']))->toBeTrue()
            ->and(is_int($balance['contract_principal']))->toBeTrue()
            ->and(is_int($balance['contract_charge']))->toBeTrue()
            ->and(is_int($balance['contract_total']))->toBeTrue()
            ->and(is_int($balance['paid_principal']))->toBeTrue()
            ->and(is_int($balance['paid_charge']))->toBeTrue()
            ->and(is_int($balance['paid_total']))->toBeTrue()
            ->and(is_int($balance['adjustment_principal']))->toBeTrue()
            ->and(is_int($balance['adjustment_charge']))->toBeTrue()
            ->and(is_int($balance['adjustment_total']))->toBeTrue();
    });
});

describe('InstallmentSchedule::calculateOutstanding per DEC-008', function () {
    it('computes installment schedule outstanding amount and components', function () {
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
