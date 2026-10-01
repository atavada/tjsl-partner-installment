<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\AuditEvent;
use App\Models\BankTransaction;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuditEventSeeder extends Seeder
{
    /**
     * Seed synthetic audit events covering Phase A operations:
     * - Partner registration and verification audit trail
     * - Agreement creation and document upload audit trail
     * - Payment staging, allocation, and posting audit trail
     * - Payment reversal audit trail with compensating reason
     * - Non-partner ABT deposit recording audit trail
     */
    public function run(): void
    {
        if (AuditEvent::where('action', 'partner.verify')->exists()) {
            return;
        }

        $operator = User::where('email', 'operator@example.test')->first()
            ?? User::first();

        $admin = User::where('email', 'test@example.com')->first()
            ?? User::first();

        $partner1 = Partner::where('partner_no_id', '00010001')->first();
        $agreement1 = Agreement::where('agreement_number', '0001/SP-TJSL/2026')->first();
        $postedTxn = BankTransaction::where('reference', 'TXN-SYN-POSTED-001')->first();
        $reversedAlloc = PaymentAllocation::where('reason', 'like', '%Koreksi salah alokasi%')->first();

        // 1. Partner Verification Audit Event
        if ($partner1 !== null) {
            AuditEvent::create([
                'correlation_id' => (string) Str::uuid(),
                'actor_id' => $operator?->id,
                'actor_type' => 'user',
                'actor_identifier' => $operator?->email ?? 'operator@example.test',
                'target_type' => 'partner',
                'target_id' => $partner1->id,
                'action' => 'partner.verify',
                'delta' => [
                    'verification_state' => ['before' => 'pending', 'after' => 'verified'],
                    'partner_no_id' => '00010001',
                    'name' => 'Koperasi Tani Makmur',
                ],
                'reason' => 'Verifikasi kelengkapan dokumen legalitas identitas mitra binaan',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subDays(30),
            ]);
        }

        // 2. Agreement Creation Audit Event
        if ($agreement1 !== null) {
            AuditEvent::create([
                'correlation_id' => (string) Str::uuid(),
                'actor_id' => $operator?->id,
                'actor_type' => 'user',
                'actor_identifier' => $operator?->email ?? 'operator@example.test',
                'target_type' => 'agreement',
                'target_id' => $agreement1->id,
                'action' => 'agreement.create',
                'delta' => [
                    'agreement_number' => '0001/SP-TJSL/2026',
                    'principal_amount' => 20_000_000,
                    'interest_amount' => 1_200_000,
                    'lifecycle_status' => 'active',
                ],
                'reason' => 'Pencatatan perjanjian pinjaman kemitraan baru tahun 2026',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subDays(25),
            ]);
        }

        // 3. Payment Staging Audit Event
        if ($postedTxn !== null) {
            AuditEvent::create([
                'correlation_id' => (string) Str::uuid(),
                'actor_id' => $operator?->id,
                'actor_type' => 'user',
                'actor_identifier' => $operator?->email ?? 'operator@example.test',
                'target_type' => 'bank_transaction',
                'target_id' => $postedTxn->id,
                'action' => 'payment.stage',
                'delta' => [
                    'reference' => 'TXN-SYN-POSTED-001',
                    'amount' => 1_000_000,
                    'state' => 'posted',
                ],
                'reason' => 'Pencatatan mutasi bank setoran angsuran mitra',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subDays(20),
            ]);
        }

        // 4. Payment Reversal Audit Event (Preserves original and compensating trail)
        if ($reversedAlloc !== null) {
            AuditEvent::create([
                'correlation_id' => (string) Str::uuid(),
                'actor_id' => $operator?->id,
                'actor_type' => 'user',
                'actor_identifier' => $operator?->email ?? 'operator@example.test',
                'target_type' => 'payment_allocation',
                'target_id' => $reversedAlloc->id,
                'action' => 'payment.reverse',
                'delta' => [
                    'state' => ['before' => 'submitted', 'after' => 'reversed'],
                    'total_amount' => 900_000,
                    'reversal_reason' => 'Koreksi salah alokasi synthetic mitra - dibatalkan',
                ],
                'reason' => 'Pembalikan alokasi pembayaran karena kesalahan pemilihan perjanjian kredit',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'created_at' => now()->subDays(10),
            ]);
        }

        // 5. Document Access Audit Event
        AuditEvent::create([
            'correlation_id' => (string) Str::uuid(),
            'actor_id' => $admin?->id,
            'actor_type' => 'user',
            'actor_identifier' => $admin?->email ?? 'test@example.com',
            'target_type' => 'agreement_document',
            'target_id' => (string) Str::uuid(),
            'action' => 'document.download',
            'delta' => [
                'document_type' => 'contract',
                'file_name' => 'surat_perjanjian_0001_SP_TJSL_2026.pdf',
            ],
            'reason' => 'Unduh arsip surat perjanjian untuk audit berkala',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'created_at' => now()->subDays(5),
        ]);
    }
}
