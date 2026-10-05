<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\FundLot;
use App\Models\FundTransfer;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FundTransfer>
 */
class FundTransferFactory extends Factory
{
    protected $model = FundTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $partner = Partner::factory();
        $agreement = Agreement::factory()->for($partner);

        return [
            'source_lot_id' => FundLot::factory()->excess(),
            'target_partner_id' => $partner,
            'target_agreement_id' => $agreement,
            'amount' => 50_000,
            'reason' => 'Pengalihan kelebihan bayar antar mitra per keputusan kasir',
            'actor_id' => User::factory(),
            'effective_date' => now()->toDateString(),
            'linked_allocation_id' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'version' => 1,
        ];
    }
}
