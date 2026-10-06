<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MetricDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MetricDefinitionSeeder extends Seeder
{
    /**
     * Seed confirmed and approved metrics per docs/metric-definitions.md.
     */
    public function run(): void
    {
        $approvedAt = Carbon::parse('2026-10-03 00:00:00');
        $approvedBy = 'Process owner';

        $definitions = [
            [
                'code' => 'remaining_principal',
                'name' => 'Remaining Principal',
                'version' => 'v1',
                'formula_expression' => 'remaining_P(as_of) = P_contract + adj_P(as_of) - paid_P(as_of)',
                'description' => 'Sisa pokok pinjaman per perjanjian per tanggal evaluasi.',
                'numerator' => 'Contract principal + signed dated principal adjustments effective <= as_of',
                'denominator' => 'Net posted principal allocations effective <= as_of (original allocations - linked reversals)',
                'date_semantics' => 'As-of date; effective date of allocations and adjustments, not receipt date',
                'included_population' => 'Agreements with lifecycle_status in (active, paid_off, closed_by_rescheduling). Draft/cancelled excluded',
                'excluded_population' => 'Draft and cancelled agreements shown separately. Unknown lifecycle shown in unclassified bucket',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'remaining_charge',
                'name' => 'Remaining Charge (Bunga / Jasa Administrasi)',
                'version' => 'v1',
                'formula_expression' => 'remaining_C(as_of) = C_contract + adj_C(as_of) - paid_C(as_of)',
                'description' => 'Sisa bunga / jasa administrasi pinjaman per perjanjian per tanggal evaluasi.',
                'numerator' => 'Contract charge + signed dated charge adjustments effective <= as_of',
                'denominator' => 'Net posted charge allocations effective <= as_of (original allocations - linked reversals)',
                'date_semantics' => 'As-of date; effective date of allocations and adjustments, not receipt date',
                'included_population' => 'Agreements with lifecycle_status in (active, paid_off, closed_by_rescheduling). Draft/cancelled excluded',
                'excluded_population' => 'Same as Remaining Principal',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'total_remaining_balance',
                'name' => 'Total Remaining Balance',
                'version' => 'v1',
                'formula_expression' => 'remaining(as_of) = remaining_P(as_of) + remaining_C(as_of)',
                'description' => 'Total sisa kewajiban piutang mitra per tanggal evaluasi.',
                'numerator' => 'N/A (sum of two component metrics)',
                'denominator' => 'N/A',
                'date_semantics' => 'Same as components',
                'included_population' => 'Agreements with lifecycle_status in (active, paid_off, closed_by_rescheduling). Draft/cancelled excluded',
                'excluded_population' => 'Same as Remaining Principal. Must never produce negative without explicit exception state (no floor at 0)',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'lunas',
                'name' => 'LUNAS (Zero Balance Override)',
                'version' => 'v1',
                'formula_expression' => 'is_lunas(as_of) = data_verified AND remaining(as_of) == 0 AND remaining_P == 0 AND remaining_C == 0',
                'description' => 'Status lunas keuangan berbasis saldo nol terverifikasi.',
                'numerator' => 'Agreements where all component balances are verified zero',
                'denominator' => 'Total agreements in included population',
                'date_semantics' => 'As-of date, same as balance metrics',
                'included_population' => 'All agreements with data_verified = true. Unknown/unverified data must NEVER become Lunas',
                'excluded_population' => 'Unverified agreements shown as Unknown. Empty collectibility must never become Lunas. LUNAS (money) != Selesai (business lifecycle)',
                'decision_ref' => 'DEC-007, DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'collectibility_label',
                'name' => 'Collectibility Label',
                'version' => 'v1',
                'formula_expression' => 'Five-label function: if not data_verified -> Unknown; if remaining == 0 -> Lunas; else apply month bands: 0-1 Lancar, 2-6 Kurang Lancar, 7-9 Diragukan, >9 Bermasalah',
                'description' => 'Klasifikasi kolektibilitas risiko pinjaman per tanggal evaluasi.',
                'numerator' => 'Agreements matching each band',
                'denominator' => 'Total agreements in included population (category sums must equal total)',
                'date_semantics' => 'As-of date',
                'included_population' => 'All agreements with active or paid_off lifecycle',
                'excluded_population' => 'Unknown collectibility shown in explicit Unknown bucket. Cancelled/draft excluded',
                'decision_ref' => 'DEC-007',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'allocation_order',
                'name' => 'Allocation Order (Admin-First, Oldest-Due-First)',
                'version' => 'v1',
                'formula_expression' => 'Within installment: alloc_C = MIN(A, out_C), then alloc_P = MIN(A - alloc_C, out_P). Across installments: oldest due date first',
                'description' => 'Aturan urutan alokasi pelunasan angsuran pinjaman.',
                'numerator' => 'N/A (process rule, not an aggregate)',
                'denominator' => 'N/A',
                'date_semantics' => 'Effective date of allocation',
                'included_population' => 'All payment allocations against active agreements',
                'excluded_population' => 'Reversed allocations excluded from capacity. ABT lots excluded until identified and allocated',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'months_paid',
                'name' => 'Months Paid (Installments Fully Covered)',
                'version' => 'v1',
                'formula_expression' => 'installments_fully_covered(as_of) = count(installments where outstanding_P == 0 AND outstanding_C == 0)',
                'description' => 'Jumlah angsuran bulanan yang telah lunas seluruhnya.',
                'numerator' => 'Installments with zero remaining on both components',
                'denominator' => 'N/A (count, not ratio)',
                'date_semantics' => 'As-of date, same as balance metrics',
                'included_population' => 'Installment schedule rows for active agreements',
                'excluded_population' => 'Cancelled installments excluded',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'months_remaining',
                'name' => 'Months Remaining',
                'version' => 'v1',
                'formula_expression' => 'tenor_months - installments_fully_covered(as_of)',
                'description' => 'Sisa tenor bulan angsuran pinjaman.',
                'numerator' => 'N/A (derived counter)',
                'denominator' => 'N/A',
                'date_semantics' => 'As-of date',
                'included_population' => 'Same as Months Paid',
                'excluded_population' => 'Same as Months Paid',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'late_fee',
                'name' => 'Late Fee',
                'version' => 'v1',
                'formula_expression' => 'late_fee = 0',
                'description' => 'Denda keterlambatan (selalu 0 rupiah).',
                'numerator' => 'N/A (constant zero)',
                'denominator' => 'N/A',
                'date_semantics' => 'N/A',
                'included_population' => 'All agreements',
                'excluded_population' => 'N/A',
                'decision_ref' => 'DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
            [
                'code' => 'excess_amount',
                'name' => 'Excess Amount (True Excess)',
                'version' => 'v1',
                'formula_expression' => 'excess_amount = payment_amount_left - partner_total_remaining(as_of)',
                'description' => 'Kelebihan pembayaran melampaui total sisa kewajiban seluruh perjanjian mitra.',
                'numerator' => 'Payment amount remaining after covering all partner debt',
                'denominator' => 'N/A',
                'date_semantics' => 'As-of date for partner_total_remaining',
                'included_population' => 'Payments where allocated amount exceeds partner total remaining across all active agreements',
                'excluded_population' => 'ABT lots (unidentified) are NOT excess. Excess lots never leave system (no refund)',
                'decision_ref' => 'DEC-006, DEC-008',
                'is_active' => true,
                'approved_at' => $approvedAt,
                'approved_by' => $approvedBy,
            ],
        ];

        foreach ($definitions as $definition) {
            MetricDefinition::updateOrCreate(
                [
                    'code' => $definition['code'],
                    'version' => $definition['version'],
                ],
                $definition
            );
        }
    }
}
