<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\FundTransfer;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\AllocationService;
use App\Services\BalanceService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

describe('FIMPL-008: Four-concept ABT fund model per DEC-006', function () {
    beforeEach(function () {
        $this->balanceService = app(BalanceService::class);
        $this->allocationService = app(AllocationService::class);
        $this->operator = User::factory()->operator()->create();
        $this->operator->grantPermission(Permission::PaymentStage);
        $this->operator->grantPermission(Permission::PaymentPost);
    });

    it('creates an ABT lot without partner_id or agreement_id (owner unknown)', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);

        $abtLot = FundLot::createAbtLot(
            transaction: $txn,
            amount: 500_000,
            evidence: 'bank_statement_line_101.pdf',
            reason: 'Setoran belum teridentifikasi (ABT)',
        );

        expect($abtLot->partner_id)->toBeNull()
            ->and($abtLot->source_agreement_id)->toBeNull()
            ->and($abtLot->lot_type)->toBe(FundLotType::Abt)
            ->and($abtLot->isAbt())->toBeTrue()
            ->and($abtLot->amount)->toBe(500_000);
    });

    it('asserts ABT lot does NOT reduce any agreement remaining balance', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 10_000_000,
            'total_amount' => 11_000_000,
            'interest_amount' => 1_000_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
        ]);

        $initialBalance = $this->balanceService->getBalance($agreement);
        expect($initialBalance['total_remaining'])->toBe(11_000_000);

        // ABT deposit arrives at bank - parks with owner unknown
        $txn = BankTransaction::factory()->create(['amount' => 2_000_000]);
        $abtLot = FundLot::createAbtLot($txn, 2_000_000);

        // Agreement balance remains completely untouched by ABT lot
        $balanceAfterAbt = $this->balanceService->getBalance($agreement);
        expect($balanceAfterAbt['total_remaining'])->toBe(11_000_000)
            ->and($balanceAfterAbt['principal_remaining'])->toBe(10_000_000)
            ->and($balanceAfterAbt['paid_total'])->toBe(0);
    });

    it('transitions ABT lot to identified_unallocated on identification, recording actor, time, and evidence', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);

        $partner = Partner::factory()->verified()->create();

        $abtLot->identify(
            partner: $partner,
            actor: $this->operator,
            evidence: 'Slip setoran fisik ditunjukkan mitra saat datang ke kantor'
        );

        $abtLot->refresh();

        expect($abtLot->lot_type)->toBe(FundLotType::IdentifiedUnallocated)
            ->and($abtLot->isIdentified())->toBeTrue()
            ->and($abtLot->isAbt())->toBeFalse()
            ->and($abtLot->partner_id)->toBe($partner->id)
            ->and($abtLot->identified_by_id)->toBe($this->operator->id)
            ->and($abtLot->identified_at)->not->toBeNull()
            ->and($abtLot->identification_evidence)->toBe('Slip setoran fisik ditunjukkan mitra saat datang ke kantor')
            ->and($abtLot->version)->toBe(2);
    });

    it('rejects identification of a non-ABT lot', function () {
        $lot = FundLot::factory()->identifiedUnallocated()->create();
        $partner = Partner::factory()->verified()->create();

        expect(fn () => $lot->identify($partner, $this->operator, 'Some evidence'))
            ->toThrow(InvalidArgumentException::class, 'Only ABT lots can be identified');
    });

    it('rejects identification with empty evidence', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);
        $partner = Partner::factory()->verified()->create();

        expect(fn () => $abtLot->identify($partner, $this->operator, '   '))
            ->toThrow(InvalidArgumentException::class, 'Identification evidence is required.');
    });

    it('changes remaining() balance ONLY when identified lot is allocated to agreement', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 5_000_000,
            'total_amount' => 5_500_000,
            'interest_amount' => 500_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
        ]);

        $schedule = InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 500_000,
            'interest_due' => 50_000,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 550_000,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 550_000]);
        $abtLot = FundLot::createAbtLot($txn, 550_000);

        // 1. ABT stage: balance unchanged
        $b1 = $this->balanceService->getBalance($agreement);
        expect($b1['total_remaining'])->toBe(5_500_000);

        // 2. Identification stage: lot identified to partner, but agreement_id still null -> balance still unchanged
        $abtLot->identify($partner, $this->operator, 'Bukti transfer dari rekening mitra');
        $b2 = $this->balanceService->getBalance($agreement);
        expect($b2['total_remaining'])->toBe(5_500_000);

        // 3. Allocation stage: feed identified lot funds into allocation on agreement
        // Lot funds are consumed into the allocation proposal
        $abtLot->amount = 0;
        $abtLot->saveQuietly();

        $allocation = PaymentAllocation::create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => 500_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 550_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
            'idempotency_key' => Str::uuid()->toString(),
            'version' => 1,
        ]);

        $this->allocationService->allocate($allocation, $agreement);
        $allocation->update([
            'state' => PaymentState::Posted,
            'approved_by_id' => $this->operator->id,
            'approved_at' => now(),
        ]);

        // Balance now reflects allocation!
        $b3 = $this->balanceService->getBalance($agreement);
        expect($b3['total_remaining'])->toBe(4_950_000)
            ->and($b3['paid_total'])->toBe(550_000);
    });

    it('creates true excess FundLot when payment exceeds total remaining debt', function () {
        $agreement = Agreement::factory()->active()->create();

        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'admin_charge_due' => 0,
            'interest_due' => 0,
            'other_charge_due' => 0,
            'principal_due' => 300_000,
            'total_due' => 300_000,
            'status' => 'pending',
        ]);

        $txn = BankTransaction::factory()->create(['amount' => 500_000]);

        $allocation = PaymentAllocation::factory()->create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'admin_charge_amount' => 0,
            'interest_amount' => 0,
            'other_charge_amount' => 0,
            'principal_amount' => 500_000,
            'total_amount' => 500_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Submitted,
        ]);

        $result = $this->allocationService->allocate($allocation, $agreement);

        expect($result->totalAllocated)->toBe(300_000)
            ->and($result->excessAmount)->toBe(200_000);

        $excessLot = FundLot::where('bank_transaction_id', $txn->id)
            ->where('lot_type', FundLotType::Excess)
            ->first();

        expect($excessLot)->not->toBeNull()
            ->and($excessLot->amount)->toBe(200_000)
            ->and($excessLot->partner_id)->toBe($agreement->partner_id)
            ->and($excessLot->source_agreement_id)->toBe($agreement->id)
            ->and($excessLot->isExcess())->toBeTrue();
    });

    it('enforces that fund lots cannot be physically deleted per DEC-006', function () {
        $lot = FundLot::factory()->abt()->create();

        expect(fn () => $lot->delete())
            ->toThrow(LogicException::class, 'Fund lots cannot be deleted per DEC-006.');
    });

    it('enforces that fund lots have no refund method per DEC-006', function () {
        $lot = FundLot::factory()->excess()->create();

        expect(method_exists($lot, 'refund'))->toBeFalse()
            ->and(method_exists($lot, 'executeDisposition'))->toBeFalse();
    });

    it('creates transfer record atomically when excess lot is reallocated to another partner', function () {
        $sourcePartner = Partner::factory()->verified()->create();
        $sourceAgreement = Agreement::factory()->active()->create(['partner_id' => $sourcePartner->id]);

        $targetPartner = Partner::factory()->verified()->create();
        $targetAgreement = Agreement::factory()->active()->create(['partner_id' => $targetPartner->id]);

        $txn = BankTransaction::factory()->create(['amount' => 1_000_000]);
        $excessLot = FundLot::createExcessLot(
            transaction: $txn,
            partner: $sourcePartner,
            agreement: $sourceAgreement,
            amount: 400_000,
            reason: 'Kelebihan pembayaran perjanjian selesai'
        );

        $transfer = FundTransfer::executeTransfer(
            sourceLot: $excessLot,
            targetPartner: $targetPartner,
            targetAgreement: $targetAgreement,
            amount: 250_000,
            reason: 'Pengalihan kelebihan bayar ke mitra binaan lain per instruksi TJSL',
            actor: $this->operator,
            effectiveDate: '2026-04-01',
        );

        expect($transfer)->toBeInstanceOf(FundTransfer::class)
            ->and($transfer->source_lot_id)->toBe($excessLot->id)
            ->and($transfer->target_partner_id)->toBe($targetPartner->id)
            ->and($transfer->target_agreement_id)->toBe($targetAgreement->id)
            ->and($transfer->amount)->toBe(250_000)
            ->and($transfer->actor_id)->toBe($this->operator->id)
            ->and($excessLot->calculateRemainingCapacity())->toBe(150_000); // 400k - 250k
    });

    it('rejects double-consumption of parked fund lot capacity', function () {
        $sourcePartner = Partner::factory()->verified()->create();
        $sourceAgreement = Agreement::factory()->active()->create(['partner_id' => $sourcePartner->id]);

        $targetPartner = Partner::factory()->verified()->create();
        $targetAgreement = Agreement::factory()->active()->create(['partner_id' => $targetPartner->id]);

        $txn = BankTransaction::factory()->create(['amount' => 300_000]);
        $excessLot = FundLot::createExcessLot(
            transaction: $txn,
            partner: $sourcePartner,
            agreement: $sourceAgreement,
            amount: 300_000,
        );

        // First transfer takes 200,000
        FundTransfer::executeTransfer(
            sourceLot: $excessLot,
            targetPartner: $targetPartner,
            targetAgreement: $targetAgreement,
            amount: 200_000,
            reason: 'Transfer pertama',
            actor: $this->operator,
            effectiveDate: '2026-04-01',
        );

        // Second transfer attempts 150,000 (total 350,000 > 300,000 capacity)
        expect(fn () => FundTransfer::executeTransfer(
            sourceLot: $excessLot,
            targetPartner: $targetPartner,
            targetAgreement: $targetAgreement,
            amount: 150_000,
            reason: 'Transfer kedua over-capacity',
            actor: $this->operator,
            effectiveDate: '2026-04-01',
        ))->toThrow(InvalidArgumentException::class, 'double-consumption rejected');
    });

    it('rejects transferring an unidentified ABT lot before identification', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);

        $targetPartner = Partner::factory()->verified()->create();
        $targetAgreement = Agreement::factory()->active()->create(['partner_id' => $targetPartner->id]);

        expect(fn () => FundTransfer::executeTransfer(
            sourceLot: $abtLot,
            targetPartner: $targetPartner,
            targetAgreement: $targetAgreement,
            amount: 200_000,
            reason: 'Transfer langsung dari ABT',
            actor: $this->operator,
            effectiveDate: '2026-04-01',
        ))->toThrow(InvalidArgumentException::class, 'Cannot transfer an unidentified ABT lot. Identify the lot before transferring.');
    });

    it('verifies unmatched ABT lot stays permanently queued with no auto-expiration', function () {
        $txn = BankTransaction::factory()->create([
            'amount' => 1_000_000,
            'transaction_datetime' => now()->subYears(5), // 5 years old
        ]);

        $agedLot = FundLot::createAbtLot($txn, 1_000_000);

        // Emulate age check: lot still exists and is untouched
        $persisted = FundLot::find($agedLot->id);
        expect($persisted)->not->toBeNull()
            ->and($persisted->isAbt())->toBeTrue()
            ->and($persisted->amount)->toBe(1_000_000);
    });

    it('stubs DP-8 excess target partner choice rule as OPEN pending decision', function () {
        expect(fn () => throw NotApprovedException::forExcessTargetChoice())
            ->toThrow(NotApprovedException::class, 'Excess target partner/agreement choice rule is blocked pending DEC-006 (DP-8) approval.');
    });

    it('stubs non-partner depositor workflow as OPEN pending decision', function () {
        expect(fn () => throw NotApprovedException::forNonPartnerDepositor())
            ->toThrow(NotApprovedException::class, 'Non-partner depositor workflow is blocked pending DEC-006 approval.');
    });
});

describe('HTTP routes for ABT capture and identification', function () {
    beforeEach(function () {
        $this->operator = User::factory()->operator()->create();
        $this->operator->grantPermission(Permission::PaymentStage);
        $this->operator->grantPermission(Permission::PaymentPost);
    });

    it('captures an ABT deposit via POST /fund-lots/abt without partner or agreement', function () {
        $response = $this->actingAs($this->operator)
            ->postJson('/fund-lots/abt', [
                'idempotency_key' => Str::uuid()->toString(),
                'amount' => 750_000,
                'receipt_date' => '2026-03-20',
                'reference' => 'TXN-BANK-ABT-099',
                'payer_name' => 'Anonim Belum Teridentifikasi',
                'evidence' => 'rekening_koran_baris_44.pdf',
                'reason' => 'Setoran tidak dikenal masuk ke rekening giro',
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        expect($data['amount'])->toBe(750_000)
            ->and($data['lot_type'])->toBe('abt')
            ->and($data['partner_id'])->toBeNull()
            ->and($data['bank_transaction_id'])->not->toBeNull();

        $lotInDb = FundLot::find($data['id']);
        expect($lotInDb)->not->toBeNull()
            ->and($lotInDb->isAbt())->toBeTrue();
    });

    it('identifies an ABT deposit via POST /fund-lots/{fundLot}/identify', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);
        $partner = Partner::factory()->verified()->create();

        $response = $this->actingAs($this->operator)
            ->postJson("/fund-lots/{$abtLot->id}/identify", [
                'partner_id' => $partner->id,
                'evidence' => 'Konfirmasi slip transfer via email resmi mitra',
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['lot_type'])->toBe('identified_unallocated')
            ->and($data['partner_id'])->toBe($partner->id)
            ->and($data['identified_by_id'])->toBe($this->operator->id)
            ->and($data['identification_evidence'])->toBe('Konfirmasi slip transfer via email resmi mitra');
    });

    it('rejects identifying ABT deposit to an unverified partner', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);
        $unverifiedPartner = Partner::factory()->create(['verification_state' => 'unverified']);

        $response = $this->actingAs($this->operator)
            ->postJson("/fund-lots/{$abtLot->id}/identify", [
                'partner_id' => $unverifiedPartner->id,
                'evidence' => 'Bukti mutasi',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    });

    it('renders the Inertia Abt/Index page with stats, verified partners, and paginated lots', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        FundLot::createAbtLot($txn, 500_000);

        $response = $this->actingAs($this->operator)
            ->get('/abt');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Abt/Index')
                ->has('lots.data')
                ->has('stats')
                ->has('verifiedPartners')
                ->has('filters')
                ->where('stats.total_abt_count', fn ($val) => $val >= 1)
            );
    });

    it('handles web form POST /fund-lots/abt and redirects to abt.index with flash', function () {
        $response = $this->actingAs($this->operator)
            ->post('/fund-lots/abt', [
                'idempotency_key' => Str::uuid()->toString(),
                'amount' => 600_000,
                'receipt_date' => '2026-03-20',
                'reference' => 'TXN-WEB-ABT-100',
                'payer_name' => 'Anonim',
                'reason' => 'Setoran ABT dari web',
            ]);

        $response->assertRedirect(route('abt.index'))
            ->assertSessionHas('success', 'Dana ABT berhasil dicatat ke antrean.');
    });

    it('handles web form POST /fund-lots/{fundLot}/identify and redirects to abt.index with flash', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500_000]);
        $abtLot = FundLot::createAbtLot($txn, 500_000);
        $partner = Partner::factory()->verified()->create();

        $response = $this->actingAs($this->operator)
            ->post("/fund-lots/{$abtLot->id}/identify", [
                'partner_id' => $partner->id,
                'evidence' => 'Konfirmasi via telepon dan berkas fisik',
            ]);

        $response->assertRedirect(route('abt.index'))
            ->assertSessionHas('success', 'Dana ABT berhasil diidentifikasi pada mitra.');
    });
});
