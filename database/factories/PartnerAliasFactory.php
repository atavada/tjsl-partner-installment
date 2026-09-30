<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerAlias>
 */
class PartnerAliasFactory extends Factory
{
    protected $model = PartnerAlias::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameRaw = $this->faker->name();

        return [
            'partner_id' => Partner::factory(),
            'name_raw' => $nameRaw,
            'name_normalized' => PartnerAlias::normalizeName($nameRaw),
            'source' => $this->faker->randomElement(['workbook', 'manual_entry', 'import']),
            'reviewer_id' => null,
            'state' => 'unreviewed',
            'version' => 1,
        ];
    }

    /**
     * Confirmed alias.
     */
    public function confirmed(): static
    {
        return $this->state(fn () => [
            'state' => 'confirmed',
        ]);
    }
}
