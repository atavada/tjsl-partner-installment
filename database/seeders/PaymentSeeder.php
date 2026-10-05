<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    /**
     * Seed synthetic payments exercising all Phase A states and edge cases:
     * - Payment in each state: draft, submitted, posted, reversed
     * - Reversal with compensating entry and reason preserved
     * - Overpayment / ABT records (unapplied excess deposit, and unmatched non-partner deposit)
     * - Unmatched bank transaction in draft state
     * - Payer VA numbers preserving leading zeros
     * - Invariants verified: positive amounts, component sum == total, allocated + unapplied <= capacity
     */
    public function run(): void
    {
        if (BankTransaction::where('reference', 'TXN-SYN-POSTED-001')->exists()) {
            return;
        }

        $operator = User::where('email', 'operator@example.test')->first()
            ?? User::first();

        $activeAgreement1 = Agreement::where('agreement_number', '0001/SP-TJSL/2026')->first()
            ?? Agreement::factory()->active()->create(['agreement_number' => '0001/SP-TJSL/2026']);

        $rescheduledAgreement2 = Agreement::where('agreement_number', '0002/PUMK/2026')->first()
            ?? Agreement::factory()->active()->create(['agreement_number' => '0002/PUMK/2026']);

        $activeAgreement3 = Agreement::where('agreement_number', '0007/SP-TJSL/2025')->first()
            ?? Agreement::factory()->active()->create(['agreement_number' => '0007/SP-TJSL/2025']);

        $chainAgreement = Agreement::where('agreement_number', '0030/SP-TJSL/2026')->first()
            ?? Agreement::factory()->active()->create(['agreement_number' => '0030/SP-TJSL/2026']);

        $partner1 = Partner::where('partner_no_id', '00010001')->first()
            ?? $activeAgreement1->partner;

        // ---------------------------------------------------------------------
        // 1. POSTED payment: BankTransaction + PaymentAllocation in Posted state
        // ---------------------------------------------------------------------
        $ref1 = 'TXN-SYN-POSTED-001';
        $datetime1 = '2026-03-01 09:15:00';
        $amount1 = 1_000_000;
        $source1 = 'SYNTHETIC_GIRO_BNI';
        $va1 = '0000888812345678';

        $postedTxn = BankTransaction::create([
            'reference' => $ref1,
            'reference_normalized' => mb_strtoupper($ref1),
            'reference_namespace' => 'REK_GIRO_BNI_01',
            'transaction_datetime' => $datetime1,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount1,
            'payer_name' => 'Budi Santoso (Koptan Makmur)',
            'payer_va' => $va1,
            'source' => $source1,
            'source_row_identifier' => 'rekening_koran_mar2026_row_12',
            'fingerprint' => BankTransaction::computeFingerprint($source1, $datetime1, $amount1, $ref1, $va1),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Posted,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_bank_statement',
            'notes' => 'Pembayaran angsuran ke-1 lunas terposting',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $postedTxn->id,
            'agreement_id' => $activeAgreement1->id,
            'principal_amount' => 800_000,
            'interest_amount' => 150_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-01',
            'period' => '2026-03',
            'state' => PaymentState::Posted,
            'evidence' => 'evidence_bni_0001.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'approved_by_id' => $operator?->id,
            'approved_at' => '2026-03-01 10:00:00',
            'approved_source' => 'SYNTHETIC_REK_KORAN_BNI:ROW_12',
            'version' => 1,
        ]);

        // ---------------------------------------------------------------------
        // 2. SUBMITTED payment: BankTransaction + PaymentAllocation in Submitted state
        // ---------------------------------------------------------------------
        $ref2 = 'TXN-SYN-SUBMITTED-002';
        $datetime2 = '2026-03-10 11:20:00';
        $amount2 = 1_500_000;
        $source2 = 'SYNTHETIC_GIRO_BCA';
        $va2 = '0000999900001234';

        $submittedTxn = BankTransaction::create([
            'reference' => $ref2,
            'reference_normalized' => mb_strtoupper($ref2),
            'reference_namespace' => 'REK_GIRO_BCA_01',
            'transaction_datetime' => $datetime2,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount2,
            'payer_name' => 'Sentra Batik Wijaya',
            'payer_va' => $va2,
            'source' => $source2,
            'source_row_identifier' => 'rekening_koran_mar2026_row_45',
            'fingerprint' => BankTransaction::computeFingerprint($source2, $datetime2, $amount2, $ref2, $va2),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Submitted,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_bank_statement',
            'notes' => 'Menunggu verifikasi reviewer rekonsiliasi',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $submittedTxn->id,
            'agreement_id' => $chainAgreement->id,
            'principal_amount' => 1_200_000,
            'interest_amount' => 250_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_500_000,
            'effective_date' => '2026-03-10',
            'period' => '2026-03',
            'state' => PaymentState::Submitted,
            'evidence' => 'evidence_bca_0002.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        // ---------------------------------------------------------------------
        // 3. DRAFT payment: BankTransaction + PaymentAllocation in Draft state
        // ---------------------------------------------------------------------
        $ref3 = 'TXN-SYN-DRAFT-003';
        $datetime3 = '2026-03-15 14:00:00';
        $amount3 = 750_000;
        $source3 = 'SYNTHETIC_GIRO_MANDIRI';
        $va3 = '0000777712345678';

        $draftTxn = BankTransaction::create([
            'reference' => $ref3,
            'reference_normalized' => mb_strtoupper($ref3),
            'reference_namespace' => 'REK_GIRO_MANDIRI_01',
            'transaction_datetime' => $datetime3,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount3,
            'payer_name' => 'Usaha Bersama Mandiri',
            'payer_va' => $va3,
            'source' => $source3,
            'source_row_identifier' => 'rekening_koran_mar2026_row_88',
            'fingerprint' => BankTransaction::computeFingerprint($source3, $datetime3, $amount3, $ref3, $va3),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Draft,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_staging',
            'notes' => 'Draft usulan alokasi belum diajukan',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $draftTxn->id,
            'agreement_id' => $activeAgreement3->id,
            'principal_amount' => 600_000,
            'interest_amount' => 120_000,
            'admin_charge_amount' => 30_000,
            'other_charge_amount' => 0,
            'total_amount' => 750_000,
            'effective_date' => '2026-03-15',
            'period' => '2026-03',
            'state' => PaymentState::Draft,
            'evidence' => 'evidence_mandiri_0003.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        // ---------------------------------------------------------------------
        // 4. REVERSED payment: Allocation reversed with compensating entry
        // ---------------------------------------------------------------------
        $ref4 = 'TXN-SYN-REVERSED-004';
        $datetime4 = '2026-02-20 13:30:00';
        $amount4 = 900_000;
        $source4 = 'SYNTHETIC_GIRO_BRI';
        $va4 = '0000888887654321';

        $reversedTxn = BankTransaction::create([
            'reference' => $ref4,
            'reference_normalized' => mb_strtoupper($ref4),
            'reference_namespace' => 'REK_GIRO_BRI_01',
            'transaction_datetime' => $datetime4,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount4,
            'payer_name' => 'Budi Santoso (Surabaya)',
            'payer_va' => $va4,
            'source' => $source4,
            'source_row_identifier' => 'rekening_koran_feb2026_row_09',
            'fingerprint' => BankTransaction::computeFingerprint($source4, $datetime4, $amount4, $ref4, $va4),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Reversed,
            'receipt_month' => '2026-02',
            'provenance' => 'synthetic_bank_statement',
            'notes' => 'Transaksi telah dibatalkan via entri pembalikan kompensasi',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        // Original allocation (state = Reversed)
        $originalAlloc = PaymentAllocation::create([
            'bank_transaction_id' => $reversedTxn->id,
            'agreement_id' => $rescheduledAgreement2->id,
            'principal_amount' => 750_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 900_000,
            'effective_date' => '2026-02-20',
            'period' => '2026-02',
            'state' => PaymentState::Reversed,
            'evidence' => 'evidence_bri_0004_original.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Koreksi salah alokasi synthetic mitra - dibatalkan',
            'version' => 1,
        ]);

        // Compensating reversal entry linked to original allocation (PRD §4 invariant 4)
        PaymentAllocation::create([
            'bank_transaction_id' => $reversedTxn->id,
            'agreement_id' => $rescheduledAgreement2->id,
            'principal_amount' => 750_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 900_000,
            'effective_date' => '2026-02-20',
            'period' => '2026-02',
            'state' => PaymentState::Reversed,
            'evidence' => 'evidence_bri_0004_reversal.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'reversal_of_id' => $originalAlloc->id,
            'reason' => 'Entri kompensasi pembalikan alokasi salah',
            'approved_by_id' => $operator?->id,
            'version' => 1,
        ]);

        // ---------------------------------------------------------------------
        // 5. OVERPAYMENT (ABT) record: Transaction with partial allocation & unapplied excess
        // ---------------------------------------------------------------------
        $ref5 = 'TXN-SYN-OVERPAY-005';
        $datetime5 = '2026-03-22 10:00:00';
        $amount5 = 1_200_000;
        $source5 = 'SYNTHETIC_GIRO_BNI';
        $va5 = '0000888812345678';

        $overpayTxn = BankTransaction::create([
            'reference' => $ref5,
            'reference_normalized' => mb_strtoupper($ref5),
            'reference_namespace' => 'REK_GIRO_BNI_01',
            'transaction_datetime' => $datetime5,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount5,
            'payer_name' => 'Budi Santoso (Koptan Makmur)',
            'payer_va' => $va5,
            'source' => $source5,
            'source_row_identifier' => 'rekening_koran_mar2026_row_99',
            'fingerprint' => BankTransaction::computeFingerprint($source5, $datetime5, $amount5, $ref5, $va5),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Submitted,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_bank_statement',
            'notes' => 'Terdapat kelebihan setoran angsuran Rp 200.000 menjadi ABT',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $overpayTxn->id,
            'agreement_id' => $activeAgreement1->id,
            'principal_amount' => 800_000,
            'interest_amount' => 150_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-22',
            'period' => '2026-03',
            'state' => PaymentState::Submitted,
            'evidence' => 'evidence_overpayment_0005.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        FundLot::create([
            'bank_transaction_id' => $overpayTxn->id,
            'partner_id' => $partner1->id,
            'source_agreement_id' => $activeAgreement1->id,
            'lot_type' => FundLotType::IdentifiedUnallocated,
            'amount' => 200_000, // 1_200_000 - 1_000_000
            'evidence' => 'evidence_abt_notice_0005.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Kelebihan setoran angsuran periode Maret 2026',
            'version' => 1,
        ]);

        // ---------------------------------------------------------------------
        // 6. UNMATCHED / NON-PARTNER deposit (ABT without partner link)
        // ---------------------------------------------------------------------
        $ref6 = 'TXN-SYN-UNMATCHED-006';
        $datetime6 = '2026-03-25 08:45:00';
        $amount6 = 500_000;
        $source6 = 'SYNTHETIC_GIRO_BRI';
        $va6 = '9999000099990000';

        $unmatchedTxn = BankTransaction::create([
            'reference' => $ref6,
            'reference_normalized' => mb_strtoupper($ref6),
            'reference_namespace' => 'REK_GIRO_BRI_01',
            'transaction_datetime' => $datetime6,
            'timezone' => 'Asia/Jakarta',
            'amount' => $amount6,
            'payer_name' => 'Pengirim Anonim Tidak Dikenal',
            'payer_va' => $va6,
            'source' => $source6,
            'source_row_identifier' => 'rekening_koran_mar2026_row_120',
            'fingerprint' => BankTransaction::computeFingerprint($source6, $datetime6, $amount6, $ref6, $va6),
            'idempotency_key' => (string) Str::uuid(),
            'state' => PaymentState::Draft,
            'receipt_month' => '2026-03',
            'provenance' => 'synthetic_bank_statement',
            'notes' => 'Setoran masuk tanpa VA atau referensi mitra yang cocok (antrean rekonsiliasi)',
            'recorded_by_id' => $operator?->id,
            'version' => 1,
        ]);

        FundLot::create([
            'bank_transaction_id' => $unmatchedTxn->id,
            'partner_id' => null, // Non-partner unapplied deposit per DEC-006 / PRD §4 / FR-13
            'lot_type' => FundLotType::Abt,
            'amount' => 500_000,
            'evidence' => null,
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Setoran bank tanpa identifikasi mitra binaan (ABT)',
            'version' => 1,
        ]);
    }
}
