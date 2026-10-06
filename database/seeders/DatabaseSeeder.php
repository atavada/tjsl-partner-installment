<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with synthetic data for Phase A.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PartnerSeeder::class,
            AgreementSeeder::class,
            PaymentSeeder::class,
            AuditEventSeeder::class,
            MetricDefinitionSeeder::class,
        ]);
    }
}
