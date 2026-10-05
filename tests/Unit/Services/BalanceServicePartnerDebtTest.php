<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Services\BalanceService;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

describe('BalanceService::getPartnerTotalRemaining per DP-7 & DEC-006', function () {
    beforeEach(function () {
        $this->service = app(BalanceService::class);
        $this->partner = Partner::factory()->verified()->create();
    });

    it('returns zero when partner has no agreements', function () {
        expect($this->service->getPartnerTotalRemaining($this->partner))->toBe(0);
    });

    it('aggregates remaining debt across multiple active agreements of a partner', function () {
        // Agreement 1: 5M contract, 1M paid via posted allocation
        $agr1 = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 5_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 5_000_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agr1->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 5_000_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 5_000_000,
            'principal_paid' => 1_000_000,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 1_000_000,
            'status' => 'partially_paid',
        ]);
        $txn1 = BankTransaction::factory()->create(['amount' => 1_000_000]);
        PaymentAllocation::create([
            'bank_transaction_id' => $txn1->id,
            'agreement_id' => $agr1->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        // Agreement 2: 3M contract, 500k paid via posted allocation
        $agr2 = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 3_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 3_000_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agr2->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 3_000_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 3_000_000,
            'principal_paid' => 500_000,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 500_000,
            'status' => 'partially_paid',
        ]);
        $txn2 = BankTransaction::factory()->create(['amount' => 500_000]);
        PaymentAllocation::create([
            'bank_transaction_id' => $txn2->id,
            'agreement_id' => $agr2->id,
            'principal_amount' => 500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 500_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        // Remaining: (5M - 1M) + (3M - 0.5M) = 4M + 2.5M = 6.5M
        expect($this->service->getPartnerTotalRemaining($this->partner))->toBe(6_500_000);
    });

    it('ignores draft, paidoff, and cancelled agreements when computing total debt', function () {
        // Active agreement: 2M remaining
        $agrActive = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 2_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_000_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agrActive->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 2_000_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 2_000_000,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        // Draft agreement: 10M (Drafts do not create debt per PRD FR-02)
        Agreement::factory()->create([
            'partner_id' => $this->partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'principal_amount' => 10_000_000,
            'total_amount' => 10_000_000,
        ]);

        // PaidOff agreement: 5M
        Agreement::factory()->create([
            'partner_id' => $this->partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::PaidOff,
            'principal_amount' => 5_000_000,
            'total_amount' => 5_000_000,
        ]);

        // Total remaining should only count active agreement: 2M
        expect($this->service->getPartnerTotalRemaining($this->partner))->toBe(2_000_000);
    });
});
