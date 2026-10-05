<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\AuditEvent;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\FundTransfer;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\FundTransferService;
use Illuminate\Support\Str;

describe('FundTransferService::allocateAbtToAgreement per DEC-006 & TASK-REM-007', function () {
    beforeEach(function () {
        $this->service = app(FundTransferService::class);
        $this->operator = User::factory()->operator()->create();
        $this->operator->grantPermission(Permission::PaymentStage);
        $this->operator->grantPermission(Permission::PaymentPost);

        $this->partner = Partner::factory()->verified()->create();
        $this->agreement = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
        ]);

        $this->schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $this->agreement->id,
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

        $this->transaction = BankTransaction::factory()->create(['amount' => 1_000_000]);
        $this->lot = FundLot::create([
            'bank_transaction_id' => $this->transaction->id,
            'partner_id' => $this->partner->id,
            'lot_type' => FundLotType::IdentifiedUnallocated,
            'amount' => 1_000_000,
            'evidence' => 'slip_setoran_valid.pdf',
            'reason' => 'Dana setoran teridentifikasi milik mitra',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);
    });

    it('allocates partial amount from identified ABT lot to agreement atomically', function () {
        $transfer = $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $this->agreement,
            amount: 600_000,
            actor: $this->operator,
            reason: 'Alokasi sebagian dana ABT',
        );

        expect($transfer)->toBeInstanceOf(FundTransfer::class)
            ->and($transfer->amount)->toBe(600_000)
            ->and($transfer->source_lot_id)->toBe($this->lot->id)
            ->and($transfer->target_agreement_id)->toBe($this->agreement->id)
            ->and($transfer->target_partner_id)->toBe($this->partner->id)
            ->and($transfer->actor_id)->toBe($this->operator->id)
            ->and($transfer->linked_allocation_id)->not->toBeNull();

        // Lot capacity decremented
        $this->lot->refresh();
        expect($this->lot->amount)->toBe(400_000)
            ->and($this->lot->lot_type)->toBe(FundLotType::IdentifiedUnallocated)
            ->and($this->lot->calculateRemainingCapacity())->toBe(400_000);

        // Schedule updated
        $this->schedule->refresh();
        expect($this->schedule->principal_paid)->toBe(600_000)
            ->and($this->schedule->total_paid)->toBe(600_000)
            ->and($this->schedule->status)->toBe('partially_paid');

        // Payment allocation posted
        $allocation = PaymentAllocation::find($transfer->linked_allocation_id);
        expect($allocation)->not->toBeNull()
            ->and($allocation->state)->toBe(PaymentState::Posted)
            ->and($allocation->total_amount)->toBe(600_000);

        // Audit event logged
        $audit = AuditEvent::where('action', 'fund_lot_allocated')
            ->where('target_id', $this->lot->id)
            ->first();
        expect($audit)->not->toBeNull()
            ->and($audit->delta['allocated_amount'])->toBe(600_000)
            ->and($audit->delta['remaining_amount'])->toBe(400_000);
    });

    it('transitions lot to allocated when full capacity is exhausted', function () {
        $transfer = $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $this->agreement,
            amount: 1_000_000,
            actor: $this->operator,
        );

        expect($transfer)->toBeInstanceOf(FundTransfer::class);

        $this->lot->refresh();
        expect($this->lot->amount)->toBe(0)
            ->and($this->lot->lot_type)->toBe(FundLotType::Allocated)
            ->and($this->lot->isAllocated())->toBeTrue()
            ->and($this->lot->calculateRemainingCapacity())->toBe(0);

        $this->schedule->refresh();
        expect($this->schedule->status)->toBe('paid');
    });

    it('rejects allocation when amount exceeds remaining lot capacity', function () {
        expect(fn () => $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $this->agreement,
            amount: 1_200_000,
            actor: $this->operator,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('rejects allocation when agreement belongs to different partner', function () {
        $otherPartner = Partner::factory()->verified()->create();
        $otherAgreement = Agreement::factory()->active()->create([
            'partner_id' => $otherPartner->id,
            'principal_amount' => 500_000,
            'total_amount' => 500_000,
        ]);

        expect(fn () => $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $otherAgreement,
            amount: 500_000,
            actor: $this->operator,
        ))->toThrow(InvalidArgumentException::class, 'Perjanjian tujuan bukan milik mitra pemilik dana parkir.');
    });

    it('rejects allocation of an unidentified ABT lot', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);

        expect(fn () => $this->service->allocateAbtToAgreement(
            lot: $abtLot,
            agreement: $this->agreement,
            amount: 500_000,
            actor: $this->operator,
        ))->toThrow(InvalidArgumentException::class);
    });

    it('returns existing transfer idempotently when called with same idempotency key', function () {
        $key = (string) Str::uuid();

        $transfer1 = $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $this->agreement,
            amount: 300_000,
            actor: $this->operator,
            idempotencyKey: $key,
        );

        $transfer2 = $this->service->allocateAbtToAgreement(
            lot: $this->lot,
            agreement: $this->agreement,
            amount: 300_000,
            actor: $this->operator,
            idempotencyKey: $key,
        );

        expect($transfer1->id)->toBe($transfer2->id);

        $this->lot->refresh();
        expect($this->lot->amount)->toBe(700_000); // Only deducted once
    });
});
