<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\PaymentAllocation;
use App\Models\ReceivableAdjustment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

describe('BankTransaction immutability and schema invariants', function () {
    it('creates a bank transaction with UUID primary key and integer IDR amount', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 5_000_000,
        ]);

        expect($txn->id)->toBeString();
        expect(Str::isUuid($txn->id))->toBeTrue();
        expect($txn->amount)->toBe(5_000_000);
        expect(is_int($txn->amount))->toBeTrue();
    });

    it('rejects updates on BankTransaction (model is immutable)', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        expect(fn () => $txn->update(['amount' => 2_000_000]))
            ->toThrow(LogicException::class, 'BankTransaction is immutable and cannot be updated.');
    });

    it('rejects deletion of BankTransaction (model is immutable)', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        expect(fn () => $txn->delete())
            ->toThrow(LogicException::class, 'BankTransaction is immutable and cannot be deleted.');
    });

    it('rejects negative amounts on BankTransaction (PRD §4 invariant 1)', function () {
        expect(fn () => BankTransaction::factory()->create(['amount' => -500_000]))
            ->toThrow(InvalidArgumentException::class, 'Bank transaction amount must be non-negative.');
    });

    it('rejects zero amount on posted BankTransaction (PRD §4 invariant 1)', function () {
        expect(fn () => BankTransaction::factory()->posted()->create(['amount' => 0]))
            ->toThrow(InvalidArgumentException::class, 'Posted bank transaction amount must be strictly positive.');
    });

    it('derives receipt_month YYYY-MM from transaction_datetime (DEC-010)', function () {
        $txn = BankTransaction::factory()->create([
            'transaction_datetime' => '2026-05-20 14:00:00',
            'receipt_month' => null,
        ]);

        expect($txn->receipt_month)->toBe('2026-05');
    });

    it('overwrites forged receipt_month with server-derived YYYY-MM from transaction_datetime (DEC-010)', function () {
        $txn = BankTransaction::factory()->create([
            'transaction_datetime' => '2026-05-20 14:00:00',
            'receipt_month' => '2099-12',
        ]);

        expect($txn->receipt_month)->toBe('2026-05');
    });

    it('normalizes reference to uppercase', function () {
        $txn = BankTransaction::factory()->create([
            'reference' => 'bca-ref-12345',
        ]);

        expect($txn->reference_normalized)->toBe('BCA-REF-12345');
    });
});

describe('Duplicate payment rejection and idempotency (FR-03, PRD §4)', function () {
    it('enforces unique idempotency_key on bank_transactions', function () {
        $key = Str::uuid()->toString();

        BankTransaction::factory()->create(['idempotency_key' => $key]);

        expect(fn () => BankTransaction::factory()->create(['idempotency_key' => $key]))
            ->toThrow(QueryException::class);
    });

    it('computes identical fingerprint for identical transaction data', function () {
        $fp1 = BankTransaction::computeFingerprint(
            source: 'CSV_IMPORT_20260315',
            datetime: '2026-03-15 10:00:00',
            amount: 1_500_000,
            reference: 'TRX-001',
            payerVa: '880012345678'
        );

        $fp2 = BankTransaction::computeFingerprint(
            source: 'CSV_IMPORT_20260315',
            datetime: '2026-03-15 10:00:00',
            amount: 1_500_000,
            reference: 'trx-001', // case insensitive
            payerVa: '880012345678'
        );

        expect($fp1)->toBe($fp2);
        expect(strlen($fp1))->toBe(64);
    });

    it('enforces unique idempotency_key on payment_allocations', function () {
        $key = Str::uuid()->toString();

        PaymentAllocation::factory()->create(['idempotency_key' => $key]);

        expect(fn () => PaymentAllocation::factory()->create(['idempotency_key' => $key]))
            ->toThrow(QueryException::class);
    });

    it('rejects double-posting of same approved source row (PRD §4 invariant 5)', function () {
        PaymentAllocation::factory()->posted()->create([
            'approved_source' => 'WORKBOOK_ROW_245',
        ]);

        expect(fn () => PaymentAllocation::factory()->posted()->create([
            'approved_source' => 'WORKBOOK_ROW_245',
        ]))->toThrow(InvalidArgumentException::class, "Approved source 'WORKBOOK_ROW_245' has already been posted.");
    });
});

describe('PaymentAllocation components and over-allocation constraints (PRD §4 invariant 2)', function () {
    it('stores integer IDR amounts for all component columns', function () {
        $alloc = PaymentAllocation::factory()->create([
            'principal_amount' => 500_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 10_000,
            'other_charge_amount' => 0,
            'total_amount' => 560_000,
        ]);

        expect($alloc->principal_amount)->toBe(500_000);
        expect($alloc->interest_amount)->toBe(50_000);
        expect($alloc->admin_charge_amount)->toBe(10_000);
        expect($alloc->other_charge_amount)->toBe(0);
        expect($alloc->total_amount)->toBe(560_000);
        expect(is_int($alloc->total_amount))->toBeTrue();
    });

    it('rejects allocation when component amounts do not sum to total_amount', function () {
        expect(fn () => PaymentAllocation::factory()->create([
            'principal_amount' => 500_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 10_000,
            'other_charge_amount' => 0,
            'total_amount' => 999_999, // Mismatched sum
        ]))->toThrow(InvalidArgumentException::class, 'Allocation total (999999) must equal component sum (560000).');
    });

    it('rejects negative component amounts on PaymentAllocation (PRD §4 invariant 1)', function () {
        expect(fn () => PaymentAllocation::factory()->create([
            'principal_amount' => -100_000,
            'total_amount' => -100_000,
        ]))->toThrow(InvalidArgumentException::class, 'Allocation component amounts must be non-negative.');
    });

    it('rejects zero total on posted PaymentAllocation (PRD §4 invariant 1)', function () {
        expect(fn () => PaymentAllocation::factory()->posted()->create([
            'principal_amount' => 0,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 0,
        ]))->toThrow(InvalidArgumentException::class, 'Posted payment allocation must be strictly positive.');
    });

    it('rejects allocation that exceeds transaction amount (over-allocation guard)', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        // Attempt to allocate 1,500,000 against a 1,000,000 transaction
        expect(fn () => PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'principal_amount' => 1_500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_500_000,
        ]))->toThrow(InvalidArgumentException::class, 'Allocation exceeds transaction capacity');
    });

    it('rejects cumulative allocations exceeding transaction amount', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        // First allocation of 700,000 is valid
        PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'principal_amount' => 700_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 700_000,
        ]);

        // Second allocation of 400,000 exceeds remaining 300,000
        expect(fn () => PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'principal_amount' => 400_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 400_000,
        ]))->toThrow(InvalidArgumentException::class, 'Allocation exceeds transaction capacity');
    });

    it('rejects allocation when allocated + unapplied exceeds transaction amount', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        // Unapplied/ABT amount of 400,000
        FundLot::factory()->create([
            'bank_transaction_id' => $txn->id,
            'amount' => 400_000,
        ]);

        // Allocation of 700,000 exceeds remaining 600,000 capacity
        expect(fn () => PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'principal_amount' => 700_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 700_000,
        ]))->toThrow(InvalidArgumentException::class, 'Allocation exceeds transaction capacity');
    });

    it('derives allocation period YYYY-MM from effective_date if null (DEC-010)', function () {
        $alloc = PaymentAllocation::factory()->create([
            'effective_date' => '2026-05-20',
            'period' => null,
        ]);

        expect($alloc->period)->toBe('2026-05');
    });
});

describe('Compensating entries and reversals (PRD §4 invariant 4)', function () {
    it('links allocation reversal to original allocation via reversal_of_id', function () {
        $original = PaymentAllocation::factory()->posted()->create([
            'principal_amount' => 500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 500_000,
        ]);

        $reversal = PaymentAllocation::factory()->reversed()->create([
            'bank_transaction_id' => $original->bank_transaction_id,
            'agreement_id' => $original->agreement_id,
            'reversal_of_id' => $original->id,
            'reason' => 'Salah alokasi mitra',
            'principal_amount' => 500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 500_000,
        ]);

        expect($reversal->reversal_of_id)->toBe($original->id);
        expect($reversal->reversalOf->id)->toBe($original->id);
        expect($original->reversals)->toHaveCount(1);
        expect($original->reversals->first()->id)->toBe($reversal->id);
    });

    it('links adjustment reversal to original adjustment via reversal_of_id', function () {
        $original = ReceivableAdjustment::factory()->posted()->create([
            'principal_amount' => 200_000,
            'total_amount' => 200_000,
        ]);

        $reversal = ReceivableAdjustment::factory()->reversal()->create([
            'agreement_id' => $original->agreement_id,
            'reversal_of_id' => $original->id,
            'reason' => 'Koreksi pembatalan',
            'principal_amount' => -200_000,
            'total_amount' => -200_000,
        ]);

        expect($reversal->reversal_of_id)->toBe($original->id);
        expect($reversal->reversalOf->id)->toBe($original->id);
        expect($original->reversals)->toHaveCount(1);
    });
});

describe('ReceivableAdjustment signed amounts and DEC-008 guard', function () {
    it('supports signed (negative) component amounts for adjustments', function () {
        $adj = ReceivableAdjustment::factory()->create([
            'principal_amount' => -100_000,
            'interest_amount' => -10_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => -110_000,
        ]);

        expect($adj->principal_amount)->toBe(-100_000);
        expect($adj->interest_amount)->toBe(-10_000);
        expect($adj->total_amount)->toBe(-110_000);
    });

    it('blocks balance calculation and posting effect per DEC-008', function () {
        $adj = ReceivableAdjustment::factory()->create();

        expect(fn () => $adj->applyToBalance())
            ->toThrow(NotApprovedException::class, 'Receivable adjustment posting and balance application is blocked pending DEC-008 approval.');
    });
});

describe('FundLot schema and DEC-006 guards', function () {
    it('creates an ABT fund lot with nullable partner (non-partner deposits)', function () {
        $lot = FundLot::factory()->abt()->create([
            'amount' => 350_000,
        ]);

        expect($lot->partner_id)->toBeNull();
        expect($lot->amount)->toBe(350_000);
        expect($lot->lot_type)->toBe(FundLotType::Abt);
        expect($lot->isAbt())->toBeTrue();
    });

    it('rejects fund lot deletion per DEC-006 (unmatched ABT stays queued permanently)', function () {
        $lot = FundLot::factory()->abt()->create();

        expect(fn () => $lot->delete())
            ->toThrow(LogicException::class, 'Fund lots cannot be deleted per DEC-006.');
    });

    it('rejects fund lot that exceeds remaining transaction capacity', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
        ]);

        PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'principal_amount' => 800_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 800_000,
        ]);

        // Attempt to create fund lot of 300,000 (total would be 1,100,000 > 1,000,000)
        expect(fn () => FundLot::factory()->create([
            'bank_transaction_id' => $txn->id,
            'amount' => 300_000,
        ]))->toThrow(InvalidArgumentException::class, 'Fund lot exceeds transaction capacity');
    });
});

describe('PaymentState enum', function () {
    it('provides Indonesian UI labels and state check methods', function () {
        expect(PaymentState::Draft->label())->toBe('Draft');
        expect(PaymentState::Submitted->label())->toBe('Diajukan');
        expect(PaymentState::Posted->label())->toBe('Dibukukan');
        expect(PaymentState::Reversed->label())->toBe('Dibalikkan');

        expect(PaymentState::Draft->isFinalized())->toBeFalse();
        expect(PaymentState::Submitted->isFinalized())->toBeFalse();
        expect(PaymentState::Posted->isFinalized())->toBeTrue();
        expect(PaymentState::Reversed->isFinalized())->toBeTrue();

        expect(PaymentState::Posted->isPosted())->toBeTrue();
        expect(PaymentState::Draft->isPosted())->toBeFalse();
        expect(PaymentState::Reversed->isReversed())->toBeTrue();
    });
});

describe('Agreement relationships to allocations and adjustments', function () {
    it('links agreement to its payment allocations and receivable adjustments', function () {
        $agreement = Agreement::factory()->create();

        $alloc = PaymentAllocation::factory()->create([
            'agreement_id' => $agreement->id,
        ]);

        $adj = ReceivableAdjustment::factory()->create([
            'agreement_id' => $agreement->id,
        ]);

        expect($agreement->paymentAllocations)->toHaveCount(1);
        expect($agreement->paymentAllocations->first()->id)->toBe($alloc->id);

        expect($agreement->receivableAdjustments)->toHaveCount(1);
        expect($agreement->receivableAdjustments->first()->id)->toBe($adj->id);
    });
});
