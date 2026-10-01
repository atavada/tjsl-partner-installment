<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\PaymentReversalService;
use App\Services\PaymentStagingService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::PaymentStage);
    $this->operator->grantPermission(Permission::PaymentPost);
    $this->operator->grantPermission(Permission::PartnerView);
    $this->operator->grantPermission(Permission::AgreementView);

    $this->unauthorizedUser = User::factory()->operator()->create();

    $this->verifiedPartner = Partner::factory()->create([
        'name' => 'Mitra Sintetis Terverifikasi',
        'partner_no_id' => '000000000012345',
        'verification_state' => 'verified',
    ]);

    $this->unverifiedPartner = Partner::factory()->create([
        'name' => 'Mitra Sintetis Belum Terverifikasi',
        'partner_no_id' => '000000000099999',
        'verification_state' => 'unverified',
    ]);

    $this->agreement = Agreement::factory()->active()->create([
        'partner_id' => $this->verifiedPartner->id,
        'agreement_number' => 'AGR/2026/03/0001',
        'total_amount' => 10_000_000,
    ]);
});

describe('Payment Staging Authorization and Navigation', function () {
    it('redirects unauthenticated guests to login', function () {
        $this->get("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments")
            ->assertRedirect('/login');

        $this->get("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments/create")
            ->assertRedirect('/login');

        $this->post("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [])
            ->assertRedirect('/login');
    });

    it('forbids users without payment.stage permission from create and store', function () {
        $this->actingAs($this->unauthorizedUser)
            ->get("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments/create")
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->post("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'principal_amount' => 500_000,
                'interest_amount' => 50_000,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ])
            ->assertForbidden();
    });

    it('renders payments index and create page with balance and component props', function () {
        $this->actingAs($this->operator)
            ->get("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Index')
                ->has('partner')
                ->has('agreement')
                ->has('payments')
            );

        $this->actingAs($this->operator)
            ->get("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments/create")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Create')
                ->has('partner')
                ->has('agreement')
                ->where('agreement.balance.status', 'unverified')
                ->has('default_idempotency_key')
            );
    });
});

describe('Zero and negative payment rejection (PRD §4 invariant 1, FR-03)', function () {
    it('rejects all-zero component amounts with 422 validation error', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'principal_amount' => 0,
                'interest_amount' => 0,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['principal_amount']);
    });

    it('rejects negative component amounts with 422 validation error', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'principal_amount' => -100_000,
                'interest_amount' => 50_000,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['principal_amount']);
    });
});

describe('Duplicate payment rejection and idempotent ingestion (FR-03, PRD §2, §4)', function () {
    it('returns existing record for identical idempotency key (idempotent re-submission)', function () {
        $idempotencyKey = Str::uuid()->toString();

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'partner_id' => $this->verifiedPartner->id,
            'agreement_id' => $this->agreement->id,
            'receipt_date' => '2026-03-15',
            'reference' => 'TXN-IDEMP-001',
            'amount' => 1_000_000,
            'principal_amount' => 800_000,
            'interest_amount' => 150_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
        ];

        // First attempt
        $res1 = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", $payload);
        $res1->assertStatus(201);
        $txnId1 = $res1->json('data.id');

        expect(BankTransaction::count())->toBe(1);

        // Second attempt with exact same idempotency_key returns existing record
        $res2 = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", $payload);
        $res2->assertStatus(201);
        $txnId2 = $res2->json('data.id');

        expect($txnId2)->toBe($txnId1);
        expect(BankTransaction::count())->toBe(1);
    });

    it('rejects duplicate transaction fingerprint with DuplicatePaymentException', function () {
        $payload1 = [
            'idempotency_key' => Str::uuid()->toString(),
            'partner_id' => $this->verifiedPartner->id,
            'agreement_id' => $this->agreement->id,
            'receipt_date' => '2026-03-15',
            'reference' => 'TXN-DUP-001',
            'payer_va' => '880011223344',
            'source' => 'SYNTHETIC_TEST',
            'amount' => 500_000,
            'principal_amount' => 450_000,
            'interest_amount' => 50_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
        ];

        $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", $payload1)
            ->assertStatus(201);

        // Different idempotency key, but identical financial parameters (triggers duplicate fingerprint block)
        $payload2 = array_merge($payload1, [
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $stagingService = app(PaymentStagingService::class);

        expect(fn () => $stagingService->stage($payload2, $this->operator))
            ->toThrow(DuplicatePaymentException::class);
    });
});

describe('Over-allocation rejected by DB and service checks (PRD §4 invariant 2)', function () {
    it('rejects allocation when component sum exceeds explicit transaction amount with 422', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'amount' => 500_000, // Bank received only 500,000
                'principal_amount' => 600_000, // Attempting to allocate 600,000 poko + 50,000 interest
                'interest_amount' => 50_000,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    });

    it('stages overage as unapplied/ABT when transaction amount exceeds allocated components', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'amount' => 1_000_000, // Mutasi bank Rp 1.000.000
                'principal_amount' => 700_000,
                'interest_amount' => 100_000,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0, // Alokasi total Rp 800.000 -> Overage Rp 200.000
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');

        expect($data['amount'])->toBe(1_000_000);
        expect($data['overpayment_amount'])->toBe(200_000);
        expect($data['overpayments'])->toHaveCount(1);
        expect($data['overpayments'][0]['unapplied_amount'])->toBe(200_000);
    });
});

describe('Name-only payment stays unmatched (PRD §9 gate)', function () {
    it('rejects staging payment to an unverified partner', function () {
        $agreementOnUnverified = Agreement::factory()->create([
            'partner_id' => $this->unverifiedPartner->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->unverifiedPartner->id}/agreements/{$agreementOnUnverified->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->unverifiedPartner->id,
                'agreement_id' => $agreementOnUnverified->id,
                'receipt_date' => '2026-03-15',
                'principal_amount' => 500_000,
                'interest_amount' => 0,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['partner_id']);
    });

    it('rejects agreement that does not belong to the route partner', function () {
        $otherPartner = Partner::factory()->create([
            'verification_state' => 'verified',
        ]);
        $otherAgreement = Agreement::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        // Route model validation returns 404 on mismatched agreement
        $this->actingAs($this->operator)
            ->get("/partners/{$this->verifiedPartner->id}/agreements/{$otherAgreement->id}/payments")
            ->assertNotFound();

        // Form request validation returns 422 on mismatched partner and agreement
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$otherAgreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $otherAgreement->id,
                'receipt_date' => '2026-03-15',
                'principal_amount' => 500_000,
                'interest_amount' => 0,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['agreement_id']);
    });
});

describe('Server recomputes totals regardless of client values (PRD §2)', function () {
    it('server derives total_amount strictly from sum of components', function () {
        $stagingService = app(PaymentStagingService::class);

        $transaction = $stagingService->stage([
            'idempotency_key' => Str::uuid()->toString(),
            'partner_id' => $this->verifiedPartner->id,
            'agreement_id' => $this->agreement->id,
            'receipt_date' => '2026-03-15',
            'principal_amount' => 600_000,
            'interest_amount' => 120_000,
            'admin_charge_amount' => 30_000,
            'other_charge_amount' => 10_000,
            // Client attempts to pass fake total
            'total_amount' => 999_999_999,
        ], $this->operator);

        $alloc = $transaction->allocations->first();
        expect($alloc->total_amount)->toBe(760_000);
        expect($transaction->amount)->toBe(760_000);
    });
});

describe('Reversal preserves original and audit trail (PRD §4 invariant 4)', function () {
    it('reverses an allocation and creates compensating entry while keeping original visible', function () {
        $stagingService = app(PaymentStagingService::class);
        $reversalService = app(PaymentReversalService::class);

        $transaction = $stagingService->stage([
            'idempotency_key' => Str::uuid()->toString(),
            'partner_id' => $this->verifiedPartner->id,
            'agreement_id' => $this->agreement->id,
            'receipt_date' => '2026-03-15',
            'principal_amount' => 800_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
        ], $this->operator);

        $originalAlloc = $transaction->allocations->first();
        expect($originalAlloc->state)->toBe(PaymentState::Draft);

        // Submit allocation
        $submittedAlloc = $stagingService->submit($originalAlloc, $this->operator);
        expect($submittedAlloc->state)->toBe(PaymentState::Submitted);

        // Reverse allocation via HTTP endpoint
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments/{$transaction->id}/allocations/{$submittedAlloc->id}/reverse", [
                'reason' => 'Koreksi salah alokasi synthetic mitra',
            ]);

        $response->assertOk();

        // Verify original allocation remains in database with state = reversed
        $originalReloaded = PaymentAllocation::find($originalAlloc->id);
        expect($originalReloaded)->not->toBeNull();
        expect($originalReloaded->state)->toBe(PaymentState::Reversed);
        expect($originalReloaded->reason)->toBe('Koreksi salah alokasi synthetic mitra');

        // Verify compensating allocation exists with reversal_of_id pointing to original
        $compensating = PaymentAllocation::where('reversal_of_id', $originalAlloc->id)->first();
        expect($compensating)->not->toBeNull();
        expect($compensating->total_amount)->toBe(900_000);
        expect($compensating->state)->toBe(PaymentState::Reversed);
        expect($compensating->reason)->toBe('Koreksi salah alokasi synthetic mitra');
        expect($compensating->approved_by_id)->toBe($this->operator->id);

        // Transaction is immutable and still exists
        $txnReloaded = BankTransaction::find($transaction->id);
        expect($txnReloaded)->not->toBeNull();
        expect($txnReloaded->amount)->toBe(900_000);
    });

    it('rejects reversal without reason', function () {
        $allocation = PaymentAllocation::factory()->create([
            'agreement_id' => $this->agreement->id,
        ]);

        $reversalService = app(PaymentReversalService::class);

        expect(fn () => $reversalService->reverse($allocation, '  ', $this->operator))
            ->toThrow(InvalidArgumentException::class, 'Reason is mandatory for payment reversal.');
    });
});

describe('Gated Operations Throws NotApprovedException (DEC-005, DEC-008, DEC-010)', function () {
    it('blocks posting to ledger per DEC-008', function () {
        $allocation = PaymentAllocation::factory()->create([
            'agreement_id' => $this->agreement->id,
        ]);

        $stagingService = app(PaymentStagingService::class);

        expect(fn () => $stagingService->post($allocation, $this->operator))
            ->toThrow(NotApprovedException::class, 'Payment posting to receivable ledger is blocked pending DEC-008 approval.');
    });

    it('blocks second review enforcement per DEC-005', function () {
        $allocation = PaymentAllocation::factory()->create([
            'agreement_id' => $this->agreement->id,
        ]);

        $stagingService = app(PaymentStagingService::class);

        expect(fn () => $stagingService->enforceSecondReview($allocation, $this->operator))
            ->toThrow(NotApprovedException::class, 'Second review requirement configuration is blocked pending DEC-005 approval.');
    });

    it('blocks period override per DEC-010 via form request', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->verifiedPartner->id}/agreements/{$this->agreement->id}/payments", [
                'idempotency_key' => Str::uuid()->toString(),
                'partner_id' => $this->verifiedPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-15',
                'period_override' => '2025-12', // Overriding 2026-03 to 2025-12
                'period_override_reason' => 'Backdated synthetic adjustment',
                'principal_amount' => 500_000,
                'interest_amount' => 0,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
            ]);

        // Throws NotApprovedException which translates to 500 / unhandled exception in test
        $response->assertStatus(500);
    });
});
