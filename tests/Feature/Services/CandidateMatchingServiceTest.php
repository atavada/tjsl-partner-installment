<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\VirtualAccount;
use App\Services\CandidateMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CandidateMatchingService (PRD §5 FR-04, Finding #7)', function () {
    beforeEach(function () {
        $this->service = new CandidateMatchingService;
    });

    it('matches exact Virtual Account with confidence 1.00', function () {
        $partner = Partner::factory()->create(['name' => 'Budi Santoso']);
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);
        VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => '8800123456789012',
            'va_number_normalized' => '8800123456789012',
        ]);

        $tx = BankTransaction::factory()->create([
            'payer_va' => '8800123456789012',
            'amount' => 1_000_000,
            'state' => PaymentState::Draft,
        ]);

        $candidates = $this->service->findCandidatesForTransaction($tx);

        expect($candidates)->not->toBeEmpty()
            ->and($candidates[0]['partner_id'])->toBe($partner->id)
            ->and($candidates[0]['confidence'])->toBe(1.00)
            ->and($candidates[0]['match_type'])->toBe('va_exact');
    });

    it('matches exact Partner NO ID with confidence 0.95', function () {
        $partner = Partner::factory()->create([
            'name' => 'Siti Aminah',
            'partner_no_id' => '000000000098765',
            'partner_no_id_normalized' => '000000000098765',
        ]);
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);

        $tx = BankTransaction::factory()->create([
            'reference' => 'BAYAR MITRA 000000000098765 AGUSTUS',
            'payer_va' => null,
            'payer_name' => 'Unknown',
            'amount' => 500_000,
            'state' => PaymentState::Draft,
        ]);

        $candidates = $this->service->findCandidatesForTransaction($tx);

        expect($candidates)->not->toBeEmpty()
            ->and($candidates[0]['partner_id'])->toBe($partner->id)
            ->and($candidates[0]['confidence'])->toBe(0.95)
            ->and($candidates[0]['match_type'])->toBe('partner_id_exact');
    });

    it('matches exact installment due amount within 3 days with confidence 0.80', function () {
        $partner = Partner::factory()->create(['name' => 'Ahmad Dahlan']);
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'due_date' => '2026-10-15',
            'principal_due' => 1_500_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 1_500_000,
        ]);

        $tx = BankTransaction::factory()->create([
            'transaction_datetime' => '2026-10-14 10:00:00',
            'amount' => 1_500_000,
            'payer_va' => null,
            'payer_name' => 'Random Depositor',
            'state' => PaymentState::Draft,
        ]);

        $candidates = $this->service->findCandidatesForTransaction($tx);

        expect($candidates)->not->toBeEmpty()
            ->and($candidates[0]['partner_id'])->toBe($partner->id)
            ->and($candidates[0]['confidence'])->toBe(0.80)
            ->and($candidates[0]['match_type'])->toBe('amount_date_proximity');
    });

    it('matches fuzzy partner name when similarity exceeds 80% with confidence 0.70', function () {
        $partner = Partner::factory()->create(['name' => 'Muhammad Rifqi Pratama']);
        Agreement::factory()->active()->create(['partner_id' => $partner->id]);

        $tx = BankTransaction::factory()->create([
            'payer_name' => 'Muhamad Rifki Pratama',
            'payer_va' => null,
            'amount' => 750_000,
            'state' => PaymentState::Draft,
        ]);

        $candidates = $this->service->findCandidatesForTransaction($tx);

        expect($candidates)->not->toBeEmpty()
            ->and($candidates[0]['partner_id'])->toBe($partner->id)
            ->and($candidates[0]['confidence'])->toBe(0.70)
            ->and($candidates[0]['match_type'])->toBe('name_fuzzy');
    });

    it('correctly reports competing candidate count when multiple candidates match', function () {
        $partner1 = Partner::factory()->create(['name' => 'Agus Setiawan']);
        Agreement::factory()->active()->create(['partner_id' => $partner1->id]);

        $partner2 = Partner::factory()->create(['name' => 'Agus Setiawanto']);
        Agreement::factory()->active()->create(['partner_id' => $partner2->id]);

        $tx = BankTransaction::factory()->create([
            'payer_name' => 'Agus Setiawan',
            'payer_va' => null,
            'amount' => 1_000_000,
            'state' => PaymentState::Draft,
        ]);

        $candidates = $this->service->findCandidatesForTransaction($tx);

        expect(count($candidates))->toBeGreaterThanOrEqual(2)
            ->and($candidates[0]['competing_candidate_count'])->toBeGreaterThanOrEqual(1);
    });
});
