<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Agreement;
use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'role' => Role::Operator,
                'email_verified_at' => now(),
                'password' => 'password',
            ]
        );

        $user->grantPermission(Permission::PartnerView)
            ->grantPermission(Permission::PartnerCreate)
            ->grantPermission(Permission::PartnerUpdate)
            ->save();

        if (Partner::count() === 0) {
            // Seed synthetic partners with aliases and virtual accounts
            $partner1 = Partner::factory()->verified()->create([
                'partner_no_id' => '00010001',
                'partner_no_id_normalized' => '00010001',
                'name' => 'Koperasi Tani Makmur',
                'nik' => '3512345678900001',
                'nik_normalized' => '3512345678900001',
                'region' => 'Malang',
                'business_type' => 'Pertanian',
            ]);
            PartnerAlias::factory()->confirmed()->create([
                'partner_id' => $partner1->id,
                'name_raw' => 'Koptan Makmur',
                'name_normalized' => 'koptan makmur',
            ]);
            VirtualAccount::factory()->create([
                'partner_id' => $partner1->id,
                'va_number' => '0000888812345678',
                'va_number_normalized' => '0000888812345678',
                'provider' => 'BNI',
            ]);
            Agreement::factory()->active()->create([
                'partner_id' => $partner1->id,
                'agreement_number' => '0001/SP-TJSL/2026',
                'agreement_number_normalized' => '0001/SP-TJSL/2026',
            ]);

            $partner2 = Partner::factory()->create([
                'partner_no_id' => '00010002',
                'partner_no_id_normalized' => '00010002',
                'name' => 'Sentra Batik Jaya',
                'nik' => '3512345678900002',
                'nik_normalized' => '3512345678900002',
                'region' => 'Surakarta',
                'business_type' => 'Perdagangan',
                'verification_state' => 'pending',
            ]);
            PartnerAlias::factory()->create([
                'partner_id' => $partner2->id,
                'name_raw' => 'Batik Jaya Solo',
                'name_normalized' => 'batik jaya solo',
            ]);
            VirtualAccount::factory()->create([
                'partner_id' => $partner2->id,
                'va_number' => '0000888887654321',
                'va_number_normalized' => '0000888887654321',
                'provider' => 'BRI',
            ]);

            $partner3 = Partner::factory()->create([
                'partner_no_id' => '007',
                'partner_no_id_normalized' => '007',
                'name' => 'Usaha Bersama Mandiri',
                'nik' => '3512345678900003',
                'nik_normalized' => '3512345678900003',
                'region' => 'Surabaya',
                'business_type' => 'Jasa',
                'verification_state' => 'unverified',
            ]);

            // Additional synthetic partners for pagination testing
            Partner::factory()->count(15)->create();
        }
    }
}
