<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforces MySQL 8.0.16+ and TiDB 5.3+ CHECK constraints per PRD §4 (Invariants 1-2) and TASK-REM-004.
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        // Enable check constraints on TiDB if running on TiDB engine
        $version = DB::select('SELECT VERSION() as v')[0]->v ?? '';
        if (str_contains((string) $version, 'TiDB')) {
            DB::statement('SET GLOBAL tidb_enable_check_constraint = 1');
        }

        // bank_transactions: amount must be strictly positive
        DB::statement('ALTER TABLE bank_transactions ADD CONSTRAINT chk_bank_transactions_amount CHECK (amount > 0)');

        // payment_allocations: total > 0, non-negative components, sum match
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_total_amount CHECK (total_amount > 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_principal_non_negative CHECK (principal_amount >= 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_interest_non_negative CHECK (interest_amount >= 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_admin_non_negative CHECK (admin_charge_amount >= 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_other_non_negative CHECK (other_charge_amount >= 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_payment_allocations_sum_match CHECK (total_amount = (principal_amount + interest_amount + admin_charge_amount + other_charge_amount))');

        // installment_schedules: non-negative dues & paids, due sum match, paid sum match
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_principal_due CHECK (principal_due >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_interest_due CHECK (interest_due >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_admin_due CHECK (admin_charge_due >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_other_due CHECK (other_charge_due >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_total_due CHECK (total_due >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_principal_paid CHECK (principal_paid >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_interest_paid CHECK (interest_paid >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_admin_paid CHECK (admin_charge_paid >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_other_paid CHECK (other_charge_paid >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_total_paid CHECK (total_paid >= 0)');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_due_sum CHECK (total_due = (principal_due + interest_due + admin_charge_due + other_charge_due))');
        DB::statement('ALTER TABLE installment_schedules ADD CONSTRAINT chk_installment_schedules_paid_sum CHECK (total_paid = (principal_paid + interest_paid + admin_charge_paid + other_charge_paid))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        $dropConstraint = function (string $table, string $constraint): void {
            try {
                DB::statement("ALTER TABLE {$table} DROP CHECK {$constraint}");
            } catch (Throwable) {
                // Ignore if constraint does not exist
            }
        };

        // bank_transactions
        $dropConstraint('bank_transactions', 'chk_bank_transactions_amount');

        // payment_allocations
        $dropConstraint('payment_allocations', 'chk_payment_allocations_total_amount');
        $dropConstraint('payment_allocations', 'chk_payment_allocations_principal_non_negative');
        $dropConstraint('payment_allocations', 'chk_payment_allocations_interest_non_negative');
        $dropConstraint('payment_allocations', 'chk_payment_allocations_admin_non_negative');
        $dropConstraint('payment_allocations', 'chk_payment_allocations_other_non_negative');
        $dropConstraint('payment_allocations', 'chk_payment_allocations_sum_match');

        // installment_schedules
        $dropConstraint('installment_schedules', 'chk_installment_schedules_principal_due');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_interest_due');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_admin_due');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_other_due');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_total_due');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_principal_paid');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_interest_paid');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_admin_paid');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_other_paid');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_total_paid');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_due_sum');
        $dropConstraint('installment_schedules', 'chk_installment_schedules_paid_sum');
    }
};
