<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $partnerNoId = str_pad((string) $this->faker->unique()->randomNumber(8), 10, '0', STR_PAD_LEFT);
        $nik = str_pad((string) $this->faker->unique()->randomNumber(9), 16, '0', STR_PAD_LEFT);

        return [
            'partner_no_id' => $partnerNoId,
            'partner_no_id_normalized' => Partner::normalizePartnerNoId($partnerNoId),
            'nik' => $nik,
            'nik_normalized' => Partner::normalizeNik($nik),
            'name' => $this->faker->name(),
            'phone' => $this->faker->numerify('08##########'),
            'address' => $this->faker->address(),
            'business_type' => $this->faker->randomElement(['Perdagangan', 'Pertanian', 'Perikanan', 'Jasa']),
            'region' => $this->faker->city(),
            'verification_state' => 'unverified',
            'provenance' => 'factory',
            'version' => 1,
        ];
    }

    /**
     * Partner in staging — no partner_no_id assigned yet.
     */
    public function staging(): static
    {
        return $this->state(fn () => [
            'partner_no_id' => null,
            'partner_no_id_normalized' => null,
        ]);
    }

    /**
     * Verified partner.
     */
    public function verified(): static
    {
        return $this->state(fn () => [
            'verification_state' => 'verified',
        ]);
    }
}
