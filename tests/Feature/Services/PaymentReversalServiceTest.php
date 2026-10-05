<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\AllocationInstallmentLine;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('PaymentReversalService Schedule Balance Reopening (TASK-REM-004, Finding #25)', function () {
    beforeEach(function () {
        $this->operator = User::factory()->operator()->create();
        $this->partner = Partner::factory()->create();
        $this->agreement = Agreement::factory()->active()->create(['partner_id' => $this->partner->id]);

        $this->schedule1 = InstallmentSchedule::create([
            'agreement_id' => $this->agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 1_000_000,
            'interest_due' => 100_000,
            'admin_charge_due' => 10_000,
            'other_charge_due' => 0,
            'total_due' => 1_110_000,
            'principal_paid' => 1_000_000,
            'interest_paid' => 100_000,
            'admin_charge_paid' => 10_000,
            'other_charge_paid' => 0,
            'total_paid' => 1_110_000,
            'status' => 'paid',
            'version' => 1,
        ]);

        $this->txn = BankTransaction::factory()->create(['amount' => 1_110_000]);

        $this->allocation = PaymentAllocation::create([
            'bank_transaction_id' => $this->txn->id,
            'agreement_id' => $this->agreement->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 10_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_110_000,
            'effective_date' => '2026-03-01',
            'period' => '2026-03',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        // Create line linking allocation to schedule
        AllocationInstallmentLine::create([
            'payment_allocation_id' => $this->allocation->id,
            'installment_schedule_id' => $this->schedule1->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 10_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_110_000,
        ]);
    });

    it('reopens schedule balances and resets status when posted allocation is reversed', function () {
        $service = app(PaymentReversalService::class);

        $compensating = $service->reverse(
            $this->allocation,
            'Koreksi pembatalan pembayaran mitra',
            $this->operator
        );

        expect($compensating)->toBeInstanceOf(PaymentAllocation::class)
            ->and($compensating->reversal_of_id)->toBe($this->allocation->id)
            ->and($compensating->state)->toBe(PaymentState::Reversed);

        $this->schedule1->refresh();

        expect($this->schedule1->principal_paid)->toBe(0)
            ->and($this->schedule1->interest_paid)->toBe(0)
            ->and($this->schedule1->admin_charge_paid)->toBe(0)
            ->and($this->schedule1->other_charge_paid)->toBe(0)
            ->and($this->schedule1->total_paid)->toBe(0)
            ->and($this->schedule1->status)->toBe('pending');
    });

    it('rejects double reversal of already reversed allocation', function () {
        $service = app(PaymentReversalService::class);

        $service->reverse(
            $this->allocation,
            'First reversal',
            $this->operator
        );

        expect(fn () => $service->reverse(
            $this->allocation,
            'Second attempt',
            $this->operator
        ))->toThrow(InvalidArgumentException::class, 'Allocation is already reversed.');
    });

    it('rejects reversal of compensating reversal entry', function () {
        $service = app(PaymentReversalService::class);

        $compensating = $service->reverse(
            $this->allocation,
            'Initial reversal',
            $this->operator
        );

        expect(fn () => $service->reverse(
            $compensating,
            'Reversing the reversal',
            $this->operator
        ))->toThrow(InvalidArgumentException::class, 'Cannot reverse a compensating reversal entry.');
    });
});
