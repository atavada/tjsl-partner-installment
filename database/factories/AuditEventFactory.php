<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correlation_id' => (string) Str::uuid(),
            'actor_id' => User::factory(),
            'actor_type' => 'user',
            'actor_identifier' => 'synthetic-user@example.test',
            'target_type' => null,
            'target_id' => null,
            'action' => 'create',
            'delta' => ['synthetic_key' => 'synthetic_value'],
            'reason' => 'Synthetic test audit entry',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony/BrowserKit',
            'created_at' => now(),
        ];
    }
}
