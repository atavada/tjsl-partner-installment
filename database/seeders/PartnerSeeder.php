<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\VirtualAccount;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Seed synthetic partners with edge cases from Phase A gate tests:
     * - Leading-zero NO IDs ('00010001', '00010002', '00010003', '007', '000000000012345')
     * - Same-name different-person partners (distinct IDs, NIKs, regions)
     * - Partners with identical alias names ('Koptan Makmur')
     * - Partners with and without NO ID (staging state)
     * - Preserved leading zeros on NIK and VA numbers
     */
    public function run(): void
    {
        // 1. Partner 1: 'Koperasi Tani Makmur' with leading-zero NO ID '00010001'
        $partner1 = Partner::firstOrCreate(
            ['partner_no_id' => '00010001'],
            [
                'partner_no_id_normalized' => '00010001',
                'name' => 'Koperasi Tani Makmur',
                'nik' => '3512345678900001',
                'nik_normalized' => '3512345678900001',
                'phone' => '081234560001',
                'address' => 'Jl. Raya Tani Makmur No. 12, Kepanjen',
                'region' => 'Malang',
                'business_type' => 'Pertanian',
                'verification_state' => 'verified',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner1->id, 'name_normalized' => 'koptan makmur'],
            [
                'name_raw' => 'Koptan Makmur',
                'source' => 'workbook',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner1->id, 'va_number_normalized' => '0000888812345678'],
            [
                'va_number' => '0000888812345678',
                'provider' => 'BNI',
                'valid_from' => '2024-01-01',
                'valid_until' => '2028-12-31',
                'version' => 1,
            ]
        );

        // 2. Same-name different-person partner A: 'Budi Santoso' (Surabaya, Perdagangan)
        // Also shares identical alias 'Koptan Makmur' with Partner 1 (same alias != same person)
        $partner2 = Partner::firstOrCreate(
            ['partner_no_id' => '00010002'],
            [
                'partner_no_id_normalized' => '00010002',
                'name' => 'Budi Santoso',
                'nik' => '3578345678900002',
                'nik_normalized' => '3578345678900002',
                'phone' => '081234560002',
                'address' => 'Jl. Pasar Besar No. 45, Wonokromo',
                'region' => 'Kota Surabaya',
                'business_type' => 'Perdagangan',
                'verification_state' => 'verified',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner2->id, 'name_normalized' => 'koptan makmur'],
            [
                'name_raw' => 'Koptan Makmur',
                'source' => 'workbook',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner2->id, 'name_normalized' => 'toko budi jaya'],
            [
                'name_raw' => 'Toko Budi Jaya',
                'source' => 'import',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner2->id, 'va_number_normalized' => '0000888887654321'],
            [
                'va_number' => '0000888887654321',
                'provider' => 'BRI',
                'valid_from' => '2024-06-01',
                'valid_until' => '2027-12-31',
                'version' => 1,
            ]
        );

        // 3. Same-name different-person partner B: 'Budi Santoso' (Malang, Pertanian)
        $partner3 = Partner::firstOrCreate(
            ['partner_no_id' => '00010003'],
            [
                'partner_no_id_normalized' => '00010003',
                'name' => 'Budi Santoso',
                'nik' => '3512345678900010',
                'nik_normalized' => '3512345678900010',
                'phone' => '081234560010',
                'address' => 'Desa Sumber Makmur RT 02/RW 04',
                'region' => 'Kab. Malang',
                'business_type' => 'Pertanian',
                'verification_state' => 'verified',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner3->id, 'name_normalized' => 'pak budi malang'],
            [
                'name_raw' => 'Pak Budi Malang',
                'source' => 'manual_entry',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner3->id, 'va_number_normalized' => '0000888833334444'],
            [
                'va_number' => '0000888833334444',
                'provider' => 'Mandiri',
                'valid_from' => '2025-01-01',
                'valid_until' => '2029-12-31',
                'version' => 1,
            ]
        );

        // 4. Partner with short leading zero NO ID ('007')
        $partner4 = Partner::firstOrCreate(
            ['partner_no_id' => '007'],
            [
                'partner_no_id_normalized' => '007',
                'name' => 'Usaha Bersama Mandiri',
                'nik' => '3512345678900003',
                'nik_normalized' => '3512345678900003',
                'phone' => '081234560003',
                'address' => 'Jl. Veteran No. 7, Klojen',
                'region' => 'Kota Malang',
                'business_type' => 'Jasa',
                'verification_state' => 'verified',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner4->id, 'name_normalized' => 'ubm services'],
            [
                'name_raw' => 'UBM Services',
                'source' => 'manual_entry',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner4->id, 'va_number_normalized' => '0000777712345678'],
            [
                'va_number' => '0000777712345678',
                'provider' => 'Mandiri',
                'valid_from' => '2025-01-01',
                'valid_until' => '2029-12-31',
                'version' => 1,
            ]
        );

        // 5. Partner with long leading zero NO ID ('000000000012345')
        $partner5 = Partner::firstOrCreate(
            ['partner_no_id' => '000000000012345'],
            [
                'partner_no_id_normalized' => '000000000012345',
                'name' => 'Sentra Batik Wijaya',
                'nik' => '3512345678900004',
                'nik_normalized' => '3512345678900004',
                'phone' => '081234560004',
                'address' => 'Jl. Laweyan No. 88',
                'region' => 'Kota Surakarta',
                'business_type' => 'Industri Kecil',
                'verification_state' => 'verified',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner5->id, 'name_normalized' => 'batik wijaya solo'],
            [
                'name_raw' => 'Batik Wijaya Solo',
                'source' => 'workbook',
                'state' => 'confirmed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner5->id, 'va_number_normalized' => '0000999900001234'],
            [
                'va_number' => '0000999900001234',
                'provider' => 'BCA',
                'valid_from' => '2023-01-01',
                'valid_until' => '2027-12-31',
                'version' => 1,
            ]
        );

        // 6. Partner with pending verification state
        $partner6 = Partner::firstOrCreate(
            ['partner_no_id' => '00020001'],
            [
                'partner_no_id_normalized' => '00020001',
                'name' => 'Siti Aminah',
                'nik' => '3512345678900005',
                'nik_normalized' => '3512345678900005',
                'phone' => '081234560005',
                'address' => 'Jl. Brantas No. 21, Pare',
                'region' => 'Kab. Kediri',
                'business_type' => 'Perikanan',
                'verification_state' => 'pending',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        PartnerAlias::firstOrCreate(
            ['partner_id' => $partner6->id, 'name_normalized' => 'mina sejahtera'],
            [
                'name_raw' => 'Mina Sejahtera',
                'source' => 'workbook',
                'state' => 'unreviewed',
                'version' => 1,
            ]
        );

        VirtualAccount::firstOrCreate(
            ['partner_id' => $partner6->id, 'va_number_normalized' => '0000555512345678'],
            [
                'va_number' => '0000555512345678',
                'provider' => 'BRI',
                'valid_from' => '2025-06-01',
                'valid_until' => null,
                'version' => 1,
            ]
        );

        // 7. Staging partner without NO ID (unverified, same name 'Siti Aminah' as Partner 6)
        Partner::firstOrCreate(
            ['nik_normalized' => '3512345678900006'],
            [
                'partner_no_id' => null,
                'partner_no_id_normalized' => null,
                'name' => 'Siti Aminah',
                'nik' => '3512345678900006',
                'phone' => '081234560006',
                'address' => 'Jl. Merdeka No. 10',
                'region' => 'Kab. Blitar',
                'business_type' => 'Peternakan',
                'verification_state' => 'unverified',
                'provenance' => 'synthetic_staging',
                'version' => 1,
            ]
        );

        // 8. Another staging partner without NO ID
        Partner::firstOrCreate(
            ['nik_normalized' => '3512345678900007'],
            [
                'partner_no_id' => null,
                'partner_no_id_normalized' => null,
                'name' => 'Kelompok Tani Berkah',
                'nik' => '3512345678900007',
                'phone' => '081234560007',
                'address' => 'Desa Tani Maju RT 03/01',
                'region' => 'Kab. Mojokerto',
                'business_type' => 'Pertanian',
                'verification_state' => 'unverified',
                'provenance' => 'synthetic_staging',
                'version' => 1,
            ]
        );

        // 9. Additional synthetic partners if fewer than 15 exist total
        $currentCount = Partner::count();
        if ($currentCount < 15) {
            Partner::factory()->count(15 - $currentCount)->create();
        }
    }
}
