<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankTransaction;
use App\Models\Overpayment;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Overpayment>
 */
class OverpaymentFactory extends Factory
{
    protected $model = Overpayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_transaction_id' => BankTransaction::factory(),
            'partner_id' => Partner::factory(),
            'unapplied_amount' => 200_000,
            'proposed_disposition' => 'offset',
            'disposition_status' => 'unresolved',
            'evidence' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'approved_by_id' => null,
            'approved_at' => null,
            'reason' => 'Synthetic overpayment notice',
            'version' => 1,
        ];
    }

    public function refund(): static
    {
        return $this->state(fn () => [
            'proposed_disposition' => 'refund',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'disposition_status' => 'disposition_approved',
            'approved_at' => now(),
        ]);
    }

    public function withoutPartner(): static
    {
        return $this->state(fn () => [
            'partner_id' => null,
            'disposition_status' => 'unresolved',
        ]);
    }
}
