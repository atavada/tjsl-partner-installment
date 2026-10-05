<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Services\AllocationService;

describe('AllocationService DP-7 Partner-Level Excess Scope (TASK-REM-007)', function () {
    beforeEach(function () {
        $this->service = app(AllocationService::class);
        $this->partner = Partner::factory()->verified()->create();

        // Agreement 1: 500k obligation
        $this->agr1 = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 500_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $this->agr1->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 500_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 500_000,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);
    });

    it('creates IdentifiedUnallocated lot when partner has other active debt exceeding excess', function () {
        // Agreement 2: active with 1M remaining debt
        $agr2 = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agr2->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 1_000_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 1_000_000,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        // Payment of 800k on Agreement 1 (needs 500k -> 300k leftover)
        $txn = BankTransaction::factory()->create(['amount' => 800_000]);
        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $this->agr1->id,
            'principal_amount' => 800_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 800_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->service->allocate($allocation, $this->agr1);

        expect($result->totalAllocated)->toBe(500_000)
            ->and($result->excessAmount)->toBe(300_000);

        // Per DP-7: Partner owes 1M on Agreement 2, so 300k is parked as IdentifiedUnallocated (NOT Excess)
        $lot = FundLot::where('bank_transaction_id', $txn->id)->first();
        expect($lot)->not->toBeNull()
            ->and($lot->lot_type)->toBe(FundLotType::IdentifiedUnallocated)
            ->and($lot->amount)->toBe(300_000)
            ->and($lot->partner_id)->toBe($this->partner->id)
            ->and($lot->source_agreement_id)->toBe($this->agr1->id);
    });

    it('creates true Excess lot when partner has zero other active debt', function () {
        // No Agreement 2. Partner only has Agreement 1 (500k).
        // Payment of 700k on Agreement 1 -> 200k leftover.
        $txn = BankTransaction::factory()->create(['amount' => 700_000]);
        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $this->agr1->id,
            'principal_amount' => 700_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 700_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->service->allocate($allocation, $this->agr1);

        expect($result->totalAllocated)->toBe(500_000)
            ->and($result->excessAmount)->toBe(200_000);

        // Per DP-7: Partner has no other debt, so 200k is true Excess
        $lot = FundLot::where('bank_transaction_id', $txn->id)->first();
        expect($lot)->not->toBeNull()
            ->and($lot->lot_type)->toBe(FundLotType::Excess)
            ->and($lot->amount)->toBe(200_000);
    });

    it('splits leftover between IdentifiedUnallocated and Excess when leftover exceeds other active debt', function () {
        // Agreement 2: active with 200k remaining debt
        $agr2 = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 200_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 200_000,
        ]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agr2->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 200_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 200_000,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        // Payment of 1,000,000 on Agreement 1 (needs 500k -> 500k leftover)
        // Partner owes 200k on Agreement 2.
        // Leftover (500k) > other debt (200k):
        // 200k -> IdentifiedUnallocated, 300k -> Excess
        $txn = BankTransaction::factory()->create(['amount' => 1_000_000]);
        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $this->agr1->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->service->allocate($allocation, $this->agr1);

        expect($result->totalAllocated)->toBe(500_000)
            ->and($result->excessAmount)->toBe(500_000);

        $unallocatedLot = FundLot::where('bank_transaction_id', $txn->id)
            ->where('lot_type', FundLotType::IdentifiedUnallocated)
            ->first();

        $excessLot = FundLot::where('bank_transaction_id', $txn->id)
            ->where('lot_type', FundLotType::Excess)
            ->first();

        expect($unallocatedLot)->not->toBeNull()
            ->and($unallocatedLot->amount)->toBe(200_000)
            ->and($excessLot)->not->toBeNull()
            ->and($excessLot->amount)->toBe(300_000);
    });
});
