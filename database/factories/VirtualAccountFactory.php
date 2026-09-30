<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Partner;
use App\Models\VirtualAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VirtualAccount>
 */
class VirtualAccountFactory extends Factory
{
    protected $model = VirtualAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vaNumber = str_pad((string) $this->faker->unique()->randomNumber(8), 16, '0', STR_PAD_LEFT);

        return [
            'partner_id' => Partner::factory(),
            'va_number' => $vaNumber,
            'va_number_normalized' => VirtualAccount::normalizeVaNumber($vaNumber),
            'provider' => $this->faker->randomElement(['BNI', 'BRI', 'Mandiri', 'BCA']),
            'valid_from' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'valid_until' => $this->faker->optional(0.7)->dateTimeBetween('now', '+2 years'),
            'evidence' => null,
            'version' => 1,
        ];
    }
}
