<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgreementTransitionType;
use App\Models\Agreement;
use App\Models\AgreementTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementTransition>
 */
class AgreementTransitionFactory extends Factory
{
    protected $model = AgreementTransition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'predecessor_id' => Agreement::factory(),
            'successor_id' => null,
            'transition_type' => AgreementTransitionType::Amendment,
            'effective_date' => '2026-02-01',
            'reason' => 'Perubahan klausul perjanjian pinjaman mitra',
            'approved_principal_amount' => null,
            'approved_interest_amount' => null,
            'approved_admin_charge_amount' => null,
            'approved_by_id' => null,
            'approved_at' => null,
            'version' => 1,
        ];
    }

    public function rescheduling(): static
    {
        return $this->state(fn () => [
            'transition_type' => AgreementTransitionType::Rescheduling,
            'successor_id' => Agreement::factory(),
            'reason' => 'Restrukturisasi penjadwalan ulang angsuran',
        ]);
    }
}
