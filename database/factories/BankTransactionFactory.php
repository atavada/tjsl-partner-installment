<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentState;
use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 1_000_000;
        $ref = 'TXN-'.(string) $this->faker->numberBetween(100000, 999999);
        $datetime = '2026-03-15 10:30:00';
        $source = 'SYNTHETIC_BANK_STATEMENT';
        $payerVa = '8800'.$this->faker->numerify('########');

        return [
            'reference' => $ref,
            'reference_normalized' => mb_strtoupper($ref),
            'reference_namespace' => 'SYNTHETIC_GIRO_01',
            'transaction_datetime' => $datetime,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount,
            'payer_name' => 'Mitra Sintetis #'.$this->faker->numberBetween(1, 999),
            'payer_va' => $payerVa,
            'source' => $source,
            'source_row_identifier' => 'row-'.$this->faker->numberBetween(1, 500),
            'fingerprint' => BankTransaction::computeFingerprint($source, $datetime, $amount, $ref, $payerVa),
            'idempotency_key' => Str::uuid()->toString(),
            'state' => PaymentState::Draft,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_factory',
            'notes' => null,
            'recorded_by_id' => null,
            'version' => 1,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn () => [
            'state' => PaymentState::Posted,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'state' => PaymentState::Submitted,
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'state' => PaymentState::Reversed,
        ]);
    }
}
