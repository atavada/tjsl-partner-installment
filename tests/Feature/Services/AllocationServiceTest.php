<?php

declare(strict_types=1);

use App\Data\AllocationResult;
use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\AllocationInstallmentLine;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Policies\PaymentPolicy;
use App\Services\AllocationService;
use App\Services\PaymentStagingService;

describe('AllocationService per DEC-008 §6 (FIMPL-007)', function () {
    beforeEach(function () {
        $this->service = app(AllocationService::class);
    });

    it('allocates exact single-installment payment: alloc_C = out_C, alloc_P = out_P, leftover = 0', function () {
        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 40_000,
            'other_charge_due' => 0,
            'principal_due' => 417_000,
            'total_due' => 467_000,
            'admin_charge_paid' => 0,
            'interest_paid' => 0,
            'other_charge_paid' => 0,
            'principal_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 467_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 40_000,
            'other_charge_amount' => 0,
            'principal_amount' => 417_000,
            'total_amount' => 467_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->service->allocate($allocation, $agreement);

        expect($result)->toBeInstanceOf(AllocationResult::class)
            ->and($result->totalAllocated)->toBe(467_000)
            ->and($result->excessAmount)->toBe(0)
            ->and($result->adminChargeAllocated)->toBe(10_000)
            ->and($result->interestAllocated)->toBe(40_000)
            ->and($result->otherChargeAllocated)->toBe(0)
            ->and($result->principalAllocated)->toBe(417_000)
            ->and($result->lines)->toHaveCount(1);

        $line = $result->lines->first();
        expect($line->installmentScheduleId)->toBe((string) $schedule->id)
            ->and($line->adminChargeAllocated)->toBe(10_000)
            ->and($line->interestAllocated)->toBe(40_000)
            ->and($line->otherChargeAllocated)->toBe(0)
            ->and($line->principalAllocated)->toBe(417_000)
            ->and($line->totalAllocated)->toBe(467_000);

        // Verify schedule update
        $schedule->refresh();
        expect($schedule->total_paid)->toBe(467_000)
            ->and($schedule->principal_paid)->toBe(417_000)
            ->and($schedule->interest_paid)->toBe(40_000)
            ->and($schedule->admin_charge_paid)->toBe(10_000)
            ->and($schedule->status)->toBe('paid');
    });

    it('allocates partial payment admin-first then principal per formula-spec §6 worked example', function () {
        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 40_000,
            'other_charge_due' => 0,
            'principal_due' => 417_000,
            'total_due' => 467_000,
            'admin_charge_paid' => 0,
            'interest_paid' => 0,
            'other_charge_paid' => 0,
            'principal_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 300_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 40_000,
            'other_charge_amount' => 0,
            'principal_amount' => 250_000,
            'total_amount' => 300_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        // Payment 300,000: alloc_C = 50,000 (10k admin + 40k interest), alloc_P = 250,000
        $result = $this->service->allocate($allocation, $agreement);

        expect($result->totalAllocated)->toBe(300_000)
            ->and($result->excessAmount)->toBe(0)
            ->and($result->adminChargeAllocated)->toBe(10_000)
            ->and($result->interestAllocated)->toBe(40_000)
            ->and($result->principalAllocated)->toBe(250_000);

        $schedule->refresh();
        expect($schedule->admin_charge_paid)->toBe(10_000)
            ->and($schedule->interest_paid)->toBe(40_000)
            ->and($schedule->principal_paid)->toBe(250_000)
            ->and($schedule->total_paid)->toBe(300_000)
            ->and($schedule->status)->toBe('partially_paid')
            ->and($schedule->calculateOutstanding())->toBe(167_000);

        $outBreakdown = $schedule->calculateComponentOutstanding();
        expect($outBreakdown['admin_charge'])->toBe(0)
            ->and($outBreakdown['interest'])->toBe(0)
            ->and($outBreakdown['principal'])->toBe(167_000)
            ->and($outBreakdown['total'])->toBe(167_000);
    });

    it('allocates across two installments oldest-first with partial second per formula-spec §6', function () {
        $agreement = Agreement::factory()->active()->create();

        // Installment 1: due 467,000 (due 2026-03-01)
        $schedule1 = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 40_000,
            'other_charge_due' => 0,
            'principal_due' => 417_000,
            'total_due' => 467_000,
            'status' => 'pending',
        ]);

        // Installment 2: due 467,000 (due 2026-04-01)
        $schedule2 = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 2,
            'due_date' => '2026-04-01',
            'admin_charge_due' => 8_000,
            'interest_due' => 40_000,
            'other_charge_due' => 0,
            'principal_due' => 419_000,
            'total_due' => 467_000,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 500_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 18_000,
            'interest_amount' => 65_000,
            'other_charge_amount' => 0,
            'principal_amount' => 417_000,
            'total_amount' => 500_000,
            'effective_date' => '2026-04-15',
            'state' => PaymentState::Submitted,
        ]);

        // Payment 500,000:
        // Inst 1 takes 50k C (10k admin + 40k interest) + 417k P = 467k.
        // Leftover 33k goes to Inst 2: 8k admin + 25k interest = 33k C, 0 P.
        $result = $this->service->allocate($allocation, $agreement);

        expect($result->totalAllocated)->toBe(500_000)
            ->and($result->excessAmount)->toBe(0)
            ->and($result->adminChargeAllocated)->toBe(18_000) // 10k + 8k
            ->and($result->interestAllocated)->toBe(65_000)     // 40k + 25k
            ->and($result->principalAllocated)->toBe(417_000)   // 417k + 0
            ->and($result->lines)->toHaveCount(2);

        $line1 = $result->lines->first();
        expect($line1->installmentScheduleId)->toBe((string) $schedule1->id)
            ->and($line1->totalAllocated)->toBe(467_000)
            ->and($line1->adminChargeAllocated)->toBe(10_000)
            ->and($line1->interestAllocated)->toBe(40_000)
            ->and($line1->principalAllocated)->toBe(417_000);

        $line2 = $result->lines->last();
        expect($line2->installmentScheduleId)->toBe((string) $schedule2->id)
            ->and($line2->totalAllocated)->toBe(33_000)
            ->and($line2->adminChargeAllocated)->toBe(8_000)
            ->and($line2->interestAllocated)->toBe(25_000)
            ->and($line2->principalAllocated)->toBe(0);

        $schedule1->refresh();
        expect($schedule1->status)->toBe('paid')
            ->and($schedule1->total_paid)->toBe(467_000);

        $schedule2->refresh();
        expect($schedule2->status)->toBe('partially_paid')
            ->and($schedule2->admin_charge_paid)->toBe(8_000)
            ->and($schedule2->interest_paid)->toBe(25_000)
            ->and($schedule2->principal_paid)->toBe(0)
            ->and($schedule2->total_paid)->toBe(33_000);

        $inst2Out = $schedule2->calculateComponentOutstanding();
        expect($inst2Out['admin_charge'])->toBe(0)
            ->and($inst2Out['interest'])->toBe(15_000) // 40k - 25k
            ->and($inst2Out['principal'])->toBe(419_000)
            ->and($inst2Out['total'])->toBe(434_000);
    });

    it('creates excess FundLot when payment exceeds all installments per formula-spec §7', function () {
        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 40_000,
            'other_charge_due' => 0,
            'principal_due' => 400_000,
            'total_due' => 450_000,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 600_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 40_000,
            'other_charge_amount' => 0,
            'principal_amount' => 550_000,
            'total_amount' => 600_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->service->allocate($allocation, $agreement);

        expect($result->totalAllocated)->toBe(450_000)
            ->and($result->excessAmount)->toBe(150_000)
            ->and($result->lines)->toHaveCount(1);

        // Verify allocation record was updated to actual allocated amount
        $allocation->refresh();
        expect($allocation->total_amount)->toBe(450_000)
            ->and($allocation->principal_amount)->toBe(400_000)
            ->and($allocation->admin_charge_amount)->toBe(10_000)
            ->and($allocation->interest_amount)->toBe(40_000);

        // Verify true excess FundLot record created for excess per DEC-006 & formula-spec §7
        $excessLot = FundLot::where('bank_transaction_id', $txn->id)->first();
        expect($excessLot)->not->toBeNull()
            ->and($excessLot->amount)->toBe(150_000)
            ->and($excessLot->partner_id)->toBe($agreement->partner_id)
            ->and($excessLot->source_agreement_id)->toBe($agreement->id)
            ->and($excessLot->lot_type)->toBe(FundLotType::Excess);
    });

    it('records allocation-to-installment links in allocation_installment_lines', function () {
        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 20_000,
            'other_charge_due' => 0,
            'principal_due' => 200_000,
            'total_due' => 230_000,
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 230_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 20_000,
            'other_charge_amount' => 0,
            'principal_amount' => 200_000,
            'total_amount' => 230_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $this->service->allocate($allocation, $agreement);

        $lines = AllocationInstallmentLine::where('payment_allocation_id', $allocation->id)->get();
        expect($lines)->toHaveCount(1);

        $line = $lines->first();
        expect($line->installment_schedule_id)->toBe($schedule->id)
            ->and($line->principal_amount)->toBe(200_000)
            ->and($line->interest_amount)->toBe(20_000)
            ->and($line->admin_charge_amount)->toBe(10_000)
            ->and($line->total_amount)->toBe(230_000);

        // Check Eloquent relations
        expect($allocation->installmentLines)->toHaveCount(1)
            ->and($allocation->installmentLines->first()->id)->toBe($line->id)
            ->and($schedule->allocationLines)->toHaveCount(1)
            ->and($schedule->allocationLines->first()->id)->toBe($line->id);
    });

    it('handles reversed allocation without affecting subsequent allocation runs', function () {
        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 20_000,
            'other_charge_due' => 0,
            'principal_due' => 200_000,
            'total_due' => 230_000,
            'status' => 'pending',
        ]);

        $txn1 = BankTransaction::factory()->create(['amount' => 230_000]);
        $alloc1 = PaymentAllocation::factory()->posted()->create([
            'bank_transaction_id' => $txn1->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 20_000,
            'other_charge_amount' => 0,
            'principal_amount' => 200_000,
            'total_amount' => 230_000,
            'effective_date' => '2026-03-15',
        ]);

        // First allocation clears schedule
        $this->service->allocate($alloc1, $agreement);
        $schedule->refresh();
        expect($schedule->status)->toBe('paid');

        // Reverse allocation 1
        $alloc1->update(['state' => PaymentState::Reversed]);

        // Allocation 2 comes in
        $txn2 = BankTransaction::factory()->create(['amount' => 230_000]);
        $alloc2 = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn2->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 20_000,
            'other_charge_amount' => 0,
            'principal_amount' => 200_000,
            'total_amount' => 230_000,
            'effective_date' => '2026-03-20',
            'state' => PaymentState::Submitted,
        ]);

        $result2 = $this->service->allocate($alloc2, $agreement);

        // Schedule was recognized as unpaid due to reversal, and alloc2 allocated fully
        expect($result2->totalAllocated)->toBe(230_000)
            ->and($result2->lines)->toHaveCount(1)
            ->and($result2->lines->first()->totalAllocated)->toBe(230_000);
    });

    it('rejects zero-amount allocation', function () {
        $agreement = Agreement::factory()->active()->create();
        $allocation = PaymentAllocation::factory()->make([
            'total_amount' => 0,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
        ]);

        expect(fn () => $this->service->allocate($allocation, $agreement))
            ->toThrow(InvalidArgumentException::class, 'Payment allocation amount must be strictly positive');
    });

    it('rejects negative-amount allocation', function () {
        $agreement = Agreement::factory()->active()->create();
        $allocation = PaymentAllocation::factory()->make([
            'total_amount' => -50_000,
        ]);

        expect(fn () => $this->service->allocate($allocation, $agreement))
            ->toThrow(InvalidArgumentException::class, 'Payment allocation amount must be strictly positive');
    });

    it('throws ScheduleIntegrityException when schedule has paid amount exceeding due', function () {
        $agreement = Agreement::factory()->active()->create();

        // Corrupted schedule row: interest_paid > interest_due
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 20_000,
            'other_charge_due' => 0,
            'principal_due' => 200_000,
            'total_due' => 230_000,
            'admin_charge_paid' => 10_000,
            'interest_paid' => 30_000, // 30k paid > 20k due!
            'other_charge_paid' => 0,
            'principal_paid' => 100_000,
            'total_paid' => 140_000,
            'status' => 'partially_paid',
        ]);

        $allocation = PaymentAllocation::factory()->make([
            'total_amount' => 50_000,
        ]);

        expect(fn () => $this->service->allocate($allocation, $agreement))
            ->toThrow(ScheduleIntegrityException::class, 'Schedule #1 integrity error: interest paid (30000) exceeds due (20000).');
    });
});

describe('PaymentStagingService::post per DEC-008 & DEC-005', function () {
    beforeEach(function () {
        $this->stagingService = app(PaymentStagingService::class);
    });

    it('posts submitted allocation to receivable ledger without throwing NotApprovedException', function () {
        $user = User::factory()->operator()->create();
        $user->grantPermission(Permission::PaymentPost);

        $agreement = Agreement::factory()->active()->create();

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 10_000,
            'interest_due' => 20_000,
            'other_charge_due' => 0,
            'principal_due' => 200_000,
            'total_due' => 230_000,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 230_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 10_000,
            'interest_amount' => 20_000,
            'other_charge_amount' => 0,
            'principal_amount' => 200_000,
            'total_amount' => 230_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $posted = $this->stagingService->post($allocation, $user);

        expect($posted->state)->toBe(PaymentState::Posted)
            ->and($posted->approved_by_id)->toBe($user->id)
            ->and($posted->approved_at)->not->toBeNull()
            ->and($posted->installmentLines)->toHaveCount(1);

        $schedule->refresh();
        expect($schedule->status)->toBe('paid')
            ->and($schedule->total_paid)->toBe(230_000);
    });

    it('rejects posting an allocation not in draft or submitted state', function () {
        $user = User::factory()->operator()->create();
        $user->grantPermission(Permission::PaymentPost);
        $allocation = PaymentAllocation::factory()->posted()->create();

        expect(fn () => $this->stagingService->post($allocation, $user))
            ->toThrow(InvalidArgumentException::class, "Allocation must be in 'draft' or 'submitted' state to be posted.");
    });
});

describe('PaymentPolicy::post authorization', function () {
    it('allows posting when user has PaymentPost permission', function () {
        $policy = new PaymentPolicy;

        $operator = User::factory()->operator()->create();
        $operator->grantPermission(Permission::PaymentPost);

        expect($policy->post($operator))->toBeTrue();
    });

    it('denies posting when user lacks PaymentPost permission', function () {
        $policy = new PaymentPolicy;

        $auditor = User::factory()->auditor()->create();

        expect($policy->post($auditor))->toBeFalse();
    });
});
