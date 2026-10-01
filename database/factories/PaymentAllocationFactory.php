<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = 800_000;
        $interest = 150_000;
        $admin = 50_000;
        $other = 0;
        $total = $principal + $interest + $admin + $other;

        return [
            'bank_transaction_id' => BankTransaction::factory(),
            'agreement_id' => Agreement::factory(),
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'admin_charge_amount' => $admin,
            'other_charge_amount' => $other,
            'total_amount' => $total,
            'effective_date' => '2026-03-15',
            'period' => '2026-03',
            'state' => PaymentState::Draft,
            'evidence' => 'synthetic_evidence_doc.pdf',
            'idempotency_key' => Str::uuid()->toString(),
            'approved_by_id' => null,
            'approved_at' => null,
            'approved_source' => null,
            'reversal_of_id' => null,
            'reason' => null,
            'version' => 1,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'state' => PaymentState::Posted,
            'approved_at' => now(),
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'state' => PaymentState::Reversed,
        ]);
    }
}
