<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\ReceivableAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReceivableAdjustment>
 */
class ReceivableAdjustmentFactory extends Factory
{
    protected $model = ReceivableAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = 100_000;
        $interest = 0;
        $admin = 0;
        $other = 0;
        $total = $principal + $interest + $admin + $other;

        return [
            'agreement_id' => Agreement::factory(),
            'adjustment_type' => 'correction',
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'admin_charge_amount' => $admin,
            'other_charge_amount' => $other,
            'total_amount' => $total,
            'effective_date' => '2026-03-15',
            'reason' => 'Synthetic adjustment reason',
            'evidence' => 'adjustment_memo.pdf',
            'idempotency_key' => Str::uuid()->toString(),
            'state' => PaymentState::Draft,
            'approved_by_id' => null,
            'approved_at' => null,
            'reversal_of_id' => null,
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

    public function openingBalance(): static
    {
        return $this->state(fn () => [
            'adjustment_type' => 'opening_balance',
        ]);
    }

    public function reversal(): static
    {
        return $this->state(fn () => [
            'adjustment_type' => 'reversal',
        ]);
    }
}
