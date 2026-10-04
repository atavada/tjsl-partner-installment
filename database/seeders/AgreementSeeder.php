<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\AgreementTransitionType;
use App\Enums\CollectibilityStatus;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AgreementTransition;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AgreementSeeder extends Seeder
{
    /**
     * Seed synthetic agreements with transitions, documents, and schedules:
     * - Draft agreements (create no debt per PRD FR-02)
     * - Active agreements (verified, current collectibility)
     * - Closed by rescheduling agreements (predecessors linked to successors)
     * - Paid off agreements (verifies closed_by_rescheduling != paid_off)
     * - Multi-hop transition chains (exercises acyclic DAGs, used for cyclic rejection test)
     * - Leading zeros preserved in agreement numbers
     * - Versioned private synthetic documents and installment schedules
     */
    public function run(): void
    {
        $partner1 = Partner::where('partner_no_id', '00010001')->first()
            ?? Partner::factory()->create(['partner_no_id' => '00010001', 'partner_no_id_normalized' => '00010001']);

        $partner2 = Partner::where('partner_no_id', '00010002')->first()
            ?? Partner::factory()->create(['partner_no_id' => '00010002', 'partner_no_id_normalized' => '00010002']);

        $partner4 = Partner::where('partner_no_id', '007')->first()
            ?? Partner::factory()->create(['partner_no_id' => '007', 'partner_no_id_normalized' => '007']);

        $partner5 = Partner::where('partner_no_id', '000000000012345')->first()
            ?? Partner::factory()->create(['partner_no_id' => '000000000012345', 'partner_no_id_normalized' => '000000000012345']);

        // -------------------------------------------------------------
        // 1. Partner 1: Active agreement (with contract document & schedules)
        // -------------------------------------------------------------
        $agreement1 = Agreement::firstOrCreate(
            ['agreement_number' => '0001/SP-TJSL/2026'],
            [
                'partner_id' => $partner1->id,
                'agreement_number_normalized' => '0001/SP-TJSL/2026',
                'batch_year' => '2026',
                'business_group' => 'Kelompok Tani Makmur',
                'source_row_number' => 101,
                'application_date' => '2026-01-10',
                'contract_date' => '2026-01-15',
                'effective_date' => '2026-02-01',
                'maturity_date' => '2028-01-31',
                'principal_amount' => 20_000_000,
                'interest_amount' => 1_200_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 21_300_000,
                'lifecycle_status' => AgreementLifecycleStatus::Active,
                'legacy_lifecycle_status' => 'Aktif',
                'collectibility_status' => CollectibilityStatus::Lancar,
                'legacy_collectibility_status' => 'Lancar',
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'legacy_signing_status' => 'Sudah TTD',
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        // Contract document
        AgreementDocument::firstOrCreate(
            ['file_name' => 'surat_perjanjian_0001_SP_TJSL_2026.pdf'],
            [
                'agreement_id' => $agreement1->id,
                'file_path' => 'agreements/synthetic/contract_0001_SP_TJSL_2026.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 1048576,
                'checksum_sha256' => hash('sha256', 'synthetic_contract_0001_SP_TJSL_2026'),
                'document_type' => 'contract',
                'document_version' => 1,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'notes' => 'Dokumen kontrak asli bertandatangan basah (sintetis)',
                'version' => 1,
            ]
        );

        // 12 monthly installment schedules
        for ($month = 1; $month <= 12; $month++) {
            $dueDate = Carbon::parse('2026-02-01')->addMonths($month - 1)->toDateString();
            InstallmentSchedule::firstOrCreate(
                ['agreement_id' => $agreement1->id, 'installment_number' => $month],
                [
                    'due_date' => $dueDate,
                    'principal_due' => 1_666_667,
                    'interest_due' => 100_000,
                    'admin_charge_due' => 8_333,
                    'other_charge_due' => 0,
                    'total_due' => 1_775_000,
                    'principal_paid' => $month === 1 ? 800_000 : 0,
                    'interest_paid' => $month === 1 ? 150_000 : 0,
                    'admin_charge_paid' => $month === 1 ? 50_000 : 0,
                    'other_charge_paid' => 0,
                    'total_paid' => $month === 1 ? 1_000_000 : 0,
                    'status' => $month === 1 ? 'partially_paid' : 'pending',
                    'is_calculated' => false,
                    'version' => 1,
                ]
            );
        }

        // -------------------------------------------------------------
        // 2. Partner 1: Draft agreement (creates NO DEBT per PRD FR-02)
        // -------------------------------------------------------------
        Agreement::firstOrCreate(
            ['agreement_number' => '0003/SP-TJSL/2026'],
            [
                'partner_id' => $partner1->id,
                'agreement_number_normalized' => '0003/SP-TJSL/2026',
                'batch_year' => '2026',
                'business_group' => 'Kelompok Tani Makmur',
                'source_row_number' => 102,
                'application_date' => '2026-03-01',
                'contract_date' => null,
                'effective_date' => null,
                'maturity_date' => null,
                'principal_amount' => 25_000_000,
                'interest_amount' => 1_500_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 26_600_000,
                'lifecycle_status' => AgreementLifecycleStatus::Draft,
                'legacy_lifecycle_status' => 'Draft',
                'collectibility_status' => CollectibilityStatus::Unknown,
                'signing_status' => AgreementSigningStatus::Draft,
                'signature_summary' => SignatureSummary::Unknown,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        // -------------------------------------------------------------
        // 3. Partner 2: Rescheduled pair (predecessor closed_by_rescheduling -> successor active)
        // -------------------------------------------------------------
        $predecessorAgreement = Agreement::firstOrCreate(
            ['agreement_number' => '0001/PUMK/2024'],
            [
                'partner_id' => $partner2->id,
                'agreement_number_normalized' => '0001/PUMK/2024',
                'batch_year' => '2024',
                'business_group' => 'Sentra Dagang Surabaya',
                'source_row_number' => 201,
                'application_date' => '2024-01-05',
                'contract_date' => '2024-01-10',
                'effective_date' => '2024-02-01',
                'maturity_date' => '2026-01-31',
                'principal_amount' => 15_000_000,
                'interest_amount' => 900_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 16_000_000,
                'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
                'legacy_lifecycle_status' => 'Ditutup Rescheduling',
                'collectibility_status' => CollectibilityStatus::KurangLancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        $successorAgreement = Agreement::firstOrCreate(
            ['agreement_number' => '0002/PUMK/2026'],
            [
                'partner_id' => $partner2->id,
                'agreement_number_normalized' => '0002/PUMK/2026',
                'batch_year' => '2026',
                'business_group' => 'Sentra Dagang Surabaya',
                'source_row_number' => 202,
                'application_date' => '2026-01-15',
                'contract_date' => '2026-01-20',
                'effective_date' => '2026-02-01',
                'maturity_date' => '2028-01-31',
                'principal_amount' => 10_000_000,
                'interest_amount' => 600_000,
                'admin_charge_amount' => 50_000,
                'other_charge_amount' => 0,
                'total_amount' => 10_650_000,
                'lifecycle_status' => AgreementLifecycleStatus::Active,
                'legacy_lifecycle_status' => 'Aktif',
                'collectibility_status' => CollectibilityStatus::Lancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        $rescheduleTransition = AgreementTransition::firstOrCreate(
            [
                'predecessor_id' => $predecessorAgreement->id,
                'successor_id' => $successorAgreement->id,
            ],
            [
                'transition_type' => AgreementTransitionType::Rescheduling,
                'effective_date' => '2026-02-01',
                'reason' => 'Restrukturisasi penjadwalan ulang angsuran kredit macet mitra binaan',
                'approved_principal_amount' => 10_000_000,
                'approved_interest_amount' => 600_000,
                'approved_admin_charge_amount' => 50_000,
                'approved_at' => '2026-01-25 10:00:00',
                'version' => 1,
            ]
        );

        // Addendum document attached to transition
        AgreementDocument::firstOrCreate(
            ['file_name' => 'addendum_restrukturisasi_0002.pdf'],
            [
                'agreement_id' => $successorAgreement->id,
                'transition_id' => $rescheduleTransition->id,
                'file_path' => 'agreements/synthetic/addendum_restrukturisasi_0002.pdf',
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 524288,
                'checksum_sha256' => hash('sha256', 'synthetic_addendum_restrukturisasi_0002'),
                'document_type' => 'addendum',
                'document_version' => 1,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'notes' => 'Addendum restrukturisasi dan penjadwalan ulang kredit (sintetis)',
                'version' => 1,
            ]
        );

        // -------------------------------------------------------------
        // 4. Partner 4: Active and Paid Off agreements
        // -------------------------------------------------------------
        Agreement::firstOrCreate(
            ['agreement_number' => '0007/SP-TJSL/2025'],
            [
                'partner_id' => $partner4->id,
                'agreement_number_normalized' => '0007/SP-TJSL/2025',
                'batch_year' => '2025',
                'business_group' => 'UBM Mandiri Group',
                'source_row_number' => 301,
                'application_date' => '2025-05-10',
                'contract_date' => '2025-05-15',
                'effective_date' => '2025-06-01',
                'maturity_date' => '2027-05-31',
                'principal_amount' => 18_000_000,
                'interest_amount' => 1_080_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 19_180_000,
                'lifecycle_status' => AgreementLifecycleStatus::Active,
                'legacy_lifecycle_status' => 'Aktif',
                'collectibility_status' => CollectibilityStatus::Lancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        // Paid off agreement (PRD §1: closed_by_rescheduling is never paid_off)
        Agreement::firstOrCreate(
            ['agreement_number' => '0006/SP-TJSL/2023'],
            [
                'partner_id' => $partner4->id,
                'agreement_number_normalized' => '0006/SP-TJSL/2023',
                'batch_year' => '2023',
                'business_group' => 'UBM Mandiri Group',
                'source_row_number' => 302,
                'application_date' => '2023-01-10',
                'contract_date' => '2023-01-15',
                'effective_date' => '2023-02-01',
                'maturity_date' => '2025-01-31',
                'principal_amount' => 10_000_000,
                'interest_amount' => 600_000,
                'admin_charge_amount' => 50_000,
                'other_charge_amount' => 0,
                'total_amount' => 10_650_000,
                'lifecycle_status' => AgreementLifecycleStatus::PaidOff,
                'legacy_lifecycle_status' => 'Lunas',
                'collectibility_status' => CollectibilityStatus::Lancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        // -------------------------------------------------------------
        // 5. Partner 5: Multi-hop transition chain (A1 -> A2 -> A3)
        // Used for exercising DAG transitions and testing cyclic rejection (A3 -> A1)
        // -------------------------------------------------------------
        $chainA1 = Agreement::firstOrCreate(
            ['agreement_number' => '0010/SP-TJSL/2024'],
            [
                'partner_id' => $partner5->id,
                'agreement_number_normalized' => '0010/SP-TJSL/2024',
                'batch_year' => '2024',
                'business_group' => 'Sentra Batik Wijaya',
                'source_row_number' => 401,
                'application_date' => '2024-01-01',
                'contract_date' => '2024-01-10',
                'effective_date' => '2024-02-01',
                'maturity_date' => '2025-01-31',
                'principal_amount' => 30_000_000,
                'interest_amount' => 1_800_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 31_900_000,
                'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
                'legacy_lifecycle_status' => 'Ditutup Rescheduling',
                'collectibility_status' => CollectibilityStatus::Bermasalah,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        $chainA2 = Agreement::firstOrCreate(
            ['agreement_number' => '0020/SP-TJSL/2025'],
            [
                'partner_id' => $partner5->id,
                'agreement_number_normalized' => '0020/SP-TJSL/2025',
                'batch_year' => '2025',
                'business_group' => 'Sentra Batik Wijaya',
                'source_row_number' => 402,
                'application_date' => '2025-01-15',
                'contract_date' => '2025-01-20',
                'effective_date' => '2025-02-01',
                'maturity_date' => '2026-01-31',
                'principal_amount' => 20_000_000,
                'interest_amount' => 1_200_000,
                'admin_charge_amount' => 100_000,
                'other_charge_amount' => 0,
                'total_amount' => 21_300_000,
                'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
                'legacy_lifecycle_status' => 'Ditutup Rescheduling',
                'collectibility_status' => CollectibilityStatus::KurangLancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        $chainA3 = Agreement::firstOrCreate(
            ['agreement_number' => '0030/SP-TJSL/2026'],
            [
                'partner_id' => $partner5->id,
                'agreement_number_normalized' => '0030/SP-TJSL/2026',
                'batch_year' => '2026',
                'business_group' => 'Sentra Batik Wijaya',
                'source_row_number' => 403,
                'application_date' => '2026-01-10',
                'contract_date' => '2026-01-15',
                'effective_date' => '2026-02-01',
                'maturity_date' => '2028-01-31',
                'principal_amount' => 15_000_000,
                'interest_amount' => 900_000,
                'admin_charge_amount' => 50_000,
                'other_charge_amount' => 0,
                'total_amount' => 15_950_000,
                'lifecycle_status' => AgreementLifecycleStatus::Active,
                'legacy_lifecycle_status' => 'Aktif',
                'collectibility_status' => CollectibilityStatus::Lancar,
                'signing_status' => AgreementSigningStatus::Signed,
                'signature_summary' => SignatureSummary::Signed,
                'provenance' => 'synthetic_seed',
                'version' => 1,
            ]
        );

        // Hop 1: A1 -> A2
        AgreementTransition::firstOrCreate(
            [
                'predecessor_id' => $chainA1->id,
                'successor_id' => $chainA2->id,
            ],
            [
                'transition_type' => AgreementTransitionType::Rescheduling,
                'effective_date' => '2025-02-01',
                'reason' => 'Restrukturisasi tahap 1 penjadwalan ulang angsuran',
                'approved_principal_amount' => 20_000_000,
                'version' => 1,
            ]
        );

        // Hop 2: A2 -> A3
        AgreementTransition::firstOrCreate(
            [
                'predecessor_id' => $chainA2->id,
                'successor_id' => $chainA3->id,
            ],
            [
                'transition_type' => AgreementTransitionType::Rescheduling,
                'effective_date' => '2026-02-01',
                'reason' => 'Restrukturisasi tahap 2 penjadwalan ulang angsuran lanjutan',
                'approved_principal_amount' => 15_000_000,
                'version' => 1,
            ]
        );
    }
}
