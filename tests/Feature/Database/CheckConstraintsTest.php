<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('Database CHECK Constraints (PRD §4 Invariants 1-2, TASK-REM-004)', function () {
    it('rejects raw SQL insert on bank_transactions with zero amount', function () {
        expect(fn () => DB::table('bank_transactions')->insert([
            'id' => (string) Str::uuid(),
            'transaction_datetime' => now(),
            'amount' => 0,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects raw SQL insert on payment_allocations with zero total_amount', function () {
        $txnId = (string) Str::uuid();
        $agreementId = (string) Str::uuid();

        // Seed parent records via DB directly
        $partnerId = (string) Str::uuid();
        DB::table('partners')->insert([
            'id' => $partnerId,
            'partner_no_id' => 'CHK001',
            'partner_no_id_normalized' => 'CHK001',
            'name' => 'Check Partner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'id' => $agreementId,
            'partner_id' => $partnerId,
            'agreement_number' => 'CHK/001/2026',
            'agreement_number_normalized' => 'CHK/001/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bank_transactions')->insert([
            'id' => $txnId,
            'transaction_datetime' => now(),
            'amount' => 1_000_000,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payment_allocations')->insert([
            'id' => (string) Str::uuid(),
            'bank_transaction_id' => $txnId,
            'agreement_id' => $agreementId,
            'principal_amount' => 0,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 0,
            'effective_date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects raw SQL insert on payment_allocations when total does not equal sum of components', function () {
        $partnerId = (string) Str::uuid();
        $agreementId = (string) Str::uuid();
        $txnId = (string) Str::uuid();

        DB::table('partners')->insert([
            'id' => $partnerId,
            'partner_no_id' => 'CHK002',
            'partner_no_id_normalized' => 'CHK002',
            'name' => 'Check Partner 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'id' => $agreementId,
            'partner_id' => $partnerId,
            'agreement_number' => 'CHK/002/2026',
            'agreement_number_normalized' => 'CHK/002/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bank_transactions')->insert([
            'id' => $txnId,
            'transaction_datetime' => now(),
            'amount' => 1_000_000,
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('payment_allocations')->insert([
            'id' => (string) Str::uuid(),
            'bank_transaction_id' => $txnId,
            'agreement_id' => $agreementId,
            'principal_amount' => 500_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 10_000,
            'other_charge_amount' => 0,
            'total_amount' => 999_999, // Mismatched sum (560_000 != 999_999)
            'effective_date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects raw SQL insert on installment_schedules when total_due does not match components', function () {
        $partnerId = (string) Str::uuid();
        $agreementId = (string) Str::uuid();

        DB::table('partners')->insert([
            'id' => $partnerId,
            'partner_no_id' => 'CHK003',
            'partner_no_id_normalized' => 'CHK003',
            'name' => 'Check Partner 3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'id' => $agreementId,
            'partner_id' => $partnerId,
            'agreement_number' => 'CHK/003/2026',
            'agreement_number_normalized' => 'CHK/003/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('installment_schedules')->insert([
            'id' => (string) Str::uuid(),
            'agreement_id' => $agreementId,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'principal_due' => 1_000_000,
            'interest_due' => 100_000,
            'admin_charge_due' => 10_000,
            'other_charge_due' => 0,
            'total_due' => 2_000_000, // Mismatched sum (1_110_000 != 2_000_000)
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects raw SQL insert on installment_schedules when total_paid does not match components', function () {
        $partnerId = (string) Str::uuid();
        $agreementId = (string) Str::uuid();

        DB::table('partners')->insert([
            'id' => $partnerId,
            'partner_no_id' => 'CHK004',
            'partner_no_id_normalized' => 'CHK004',
            'name' => 'Check Partner 4',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('agreements')->insert([
            'id' => $agreementId,
            'partner_id' => $partnerId,
            'agreement_number' => 'CHK/004/2026',
            'agreement_number_normalized' => 'CHK/004/2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(fn () => DB::table('installment_schedules')->insert([
            'id' => (string) Str::uuid(),
            'agreement_id' => $agreementId,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'principal_due' => 1_000_000,
            'interest_due' => 100_000,
            'admin_charge_due' => 10_000,
            'other_charge_due' => 0,
            'total_due' => 1_110_000,
            'principal_paid' => 500_000,
            'interest_paid' => 100_000,
            'admin_charge_paid' => 10_000,
            'other_charge_paid' => 0,
            'total_paid' => 999_999, // Mismatched paid sum (610_000 != 999_999)
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });
});
