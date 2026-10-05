<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FundLotType;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FundLot>
 */
class FundLotFactory extends Factory
{
    protected $model = FundLot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_transaction_id' => BankTransaction::factory(),
            'partner_id' => Partner::factory(),
            'lot_type' => FundLotType::IdentifiedUnallocated,
            'amount' => 200_000,
            'evidence' => null,
            'identified_by_id' => null,
            'identified_at' => null,
            'identification_evidence' => null,
            'source_agreement_id' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'reason' => 'Synthetic fund lot',
            'version' => 1,
        ];
    }

    public function abt(): static
    {
        return $this->state(fn () => [
            'partner_id' => null,
            'lot_type' => FundLotType::Abt,
            'identified_by_id' => null,
            'identified_at' => null,
            'identification_evidence' => null,
        ]);
    }

    public function identifiedUnallocated(): static
    {
        return $this->state(fn () => [
            'partner_id' => Partner::factory(),
            'lot_type' => FundLotType::IdentifiedUnallocated,
            'identified_by_id' => User::factory(),
            'identified_at' => now(),
            'identification_evidence' => 'Bukti mutasi bank dan konfirmasi WhatsApp mitra',
        ]);
    }

    public function excess(): static
    {
        return $this->state(fn () => [
            'partner_id' => Partner::factory(),
            'lot_type' => FundLotType::Excess,
        ]);
    }
}
