<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cashier = User::factory()->operator()->create();
    $this->cashier->grantPermission(Permission::PaymentPost);
    $this->cashier->grantPermission(Permission::PaymentStage);
    $this->cashier->grantPermission(Permission::PartnerView);
    $this->cashier->grantPermission(Permission::AgreementView);

    $this->viewer = User::factory()->auditor()->create();
    $this->viewer->grantPermission(Permission::PaymentStage);
    $this->viewer->grantPermission(Permission::PartnerView);
    $this->viewer->grantPermission(Permission::AgreementView);

    $this->partner = Partner::factory()->create([
        'name' => 'Mitra Sintetis Posting',
        'partner_no_id' => '000000000054321',
        'verification_state' => 'verified',
    ]);

    $this->agreement = Agreement::factory()->active()->create([
        'partner_id' => $this->partner->id,
        'agreement_number' => 'AGR/2026/03/POST-001',
        'total_amount' => 10_000_000,
    ]);

    $this->schedule1 = InstallmentSchedule::factory()->create([
        'agreement_id' => $this->agreement->id,
        'installment_number' => 1,
        'due_date' => '2026-04-01',
        'principal_due' => 1_000_000,
        'interest_due' => 100_000,
        'admin_charge_due' => 50_000,
        'principal_paid' => 0,
        'interest_paid' => 0,
        'admin_charge_paid' => 0,
        'status' => 'pending',
    ]);

    $this->schedule2 = InstallmentSchedule::factory()->create([
        'agreement_id' => $this->agreement->id,
        'installment_number' => 2,
        'due_date' => '2026-05-01',
        'principal_due' => 1_000_000,
        'interest_due' => 100_000,
        'admin_charge_due' => 50_000,
        'principal_paid' => 0,
        'interest_paid' => 0,
        'admin_charge_paid' => 0,
        'status' => 'pending',
    ]);

    $this->payment = BankTransaction::factory()->create([
        'amount' => 1_150_000,
        'source' => 'SYNTHETIC_INGEST',
        'payer_name' => 'Mitra Sintetis Posting',
        'payer_va' => '988000000054321',
    ]);

    $this->allocation = PaymentAllocation::create([
        'bank_transaction_id' => $this->payment->id,
        'agreement_id' => $this->agreement->id,
        'principal_amount' => 1_000_000,
        'interest_amount' => 100_000,
        'admin_charge_amount' => 50_000,
        'other_charge_amount' => 0,
        'total_amount' => 1_150_000,
        'effective_date' => '2026-04-02',
        'period' => '2026-04',
        'state' => PaymentState::Draft,
        'idempotency_key' => (string) Str::uuid(),
        'version' => 1,
    ]);
});

describe('PaymentController::post route and lifecycle (TASK-REM-003)', function () {
    it('redirects unauthenticated users to login', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";

        $this->post($url)
            ->assertRedirect('/login');
    });

    it('rejects posting with 403 Forbidden when user lacks PaymentPost permission', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";

        $this->actingAs($this->viewer)
            ->post($url)
            ->assertForbidden();

        $this->allocation->refresh();
        expect($this->allocation->state)->toBe(PaymentState::Draft);
    });

    it('allows authorized cashier to post allocation and updates ledger balances', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";

        $response = $this->actingAs($this->cashier)
            ->post($url);

        $response->assertRedirect("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}")
            ->assertSessionHas('success');

        $this->allocation->refresh();
        expect($this->allocation->state)->toBe(PaymentState::Posted)
            ->and($this->allocation->approved_by_id)->toBe($this->cashier->id)
            ->and($this->allocation->approved_at)->not->toBeNull()
            ->and($this->allocation->version)->toBe(2);

        $this->schedule1->refresh();
        expect($this->schedule1->status)->toBe('paid')
            ->and($this->schedule1->principal_paid)->toBe(1_000_000)
            ->and($this->schedule1->interest_paid)->toBe(100_000)
            ->and($this->schedule1->admin_charge_paid)->toBe(50_000);

        // Verify BalanceService reflects the posted allocation
        $balanceService = app(BalanceService::class);
        $balance = $balanceService->getBalance($this->agreement);
        expect($balance['paid_principal'])->toBe(1_000_000)
            ->and($balance['paid_total'])->toBe(1_150_000);
    });

    it('returns JSON response for API/XHR posting requests', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";

        $response = $this->actingAs($this->cashier)
            ->postJson($url);

        $response->assertOk()
            ->assertJson([
                'allocation_id' => $this->allocation->id,
                'state' => 'posted',
            ]);
    });

    it('handles idempotent re-posting without double-crediting installment balances', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";

        // First post
        $this->actingAs($this->cashier)
            ->postJson($url)
            ->assertOk();

        $this->schedule1->refresh();
        $firstPrincipalPaid = $this->schedule1->principal_paid;
        expect($firstPrincipalPaid)->toBe(1_000_000);

        // Second post (idempotent re-posting)
        $this->actingAs($this->cashier)
            ->postJson($url)
            ->assertOk()
            ->assertJson([
                'allocation_id' => $this->allocation->id,
                'state' => 'posted',
            ]);

        // Verify schedule balances were NOT double-credited
        $this->schedule1->refresh();
        expect($this->schedule1->principal_paid)->toBe(1_000_000)
            ->and($this->schedule1->total_paid)->toBe(1_150_000);
    });

    it('rejects posting with 404 when route parameters mismatch', function () {
        $otherPartner = Partner::factory()->create();
        $otherAgreement = Agreement::factory()->active()->create(['partner_id' => $otherPartner->id]);

        // Wrong partner in route
        $urlWrongPartner = "/partners/{$otherPartner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";
        $this->actingAs($this->cashier)
            ->postJson($urlWrongPartner)
            ->assertNotFound();

        // Wrong agreement in route
        $urlWrongAgreement = "/partners/{$this->partner->id}/agreements/{$otherAgreement->id}/payments/{$this->payment->id}/allocations/{$this->allocation->id}/post";
        $this->actingAs($this->cashier)
            ->postJson($urlWrongAgreement)
            ->assertNotFound();
    });
});

describe('StorePaymentRequest route parameter resolution (TASK-REM-003 Finding #21)', function () {
    it('successfully stores payment when partner_id and agreement_id are omitted from body', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments";

        $response = $this->actingAs($this->cashier)
            ->post($url, [
                'idempotency_key' => (string) Str::uuid(),
                'receipt_date' => '2026-03-20',
                'amount' => 1_150_000,
                'principal_amount' => 1_000_000,
                'interest_amount' => 100_000,
                'admin_charge_amount' => 50_000,
                'other_charge_amount' => 0,
                'payer_name' => 'Mitra Omitted Body',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bank_transactions', [
            'payer_name' => 'Mitra Omitted Body',
            'amount' => 1_150_000,
        ]);
    });

    it('rejects with 422 when body partner_id conflicts with route partner', function () {
        $otherPartner = Partner::factory()->create();
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments";

        $response = $this->actingAs($this->cashier)
            ->postJson($url, [
                'idempotency_key' => (string) Str::uuid(),
                'partner_id' => $otherPartner->id,
                'agreement_id' => $this->agreement->id,
                'receipt_date' => '2026-03-20',
                'amount' => 1_150_000,
                'principal_amount' => 1_000_000,
                'interest_amount' => 100_000,
                'admin_charge_amount' => 50_000,
                'other_charge_amount' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    });
});

describe('PaymentController::show Inertia props (TASK-REM-003 UI)', function () {
    it('passes can_post true to Inertia for authorized cashier', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}";

        $this->actingAs($this->cashier)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Show')
                ->where('can_post', true)
                ->has('partner')
                ->has('agreement')
                ->has('payment')
            );
    });

    it('passes can_post false to Inertia for read-only viewer', function () {
        $url = "/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}";

        $this->actingAs($this->viewer)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Payments/Show')
                ->where('can_post', false)
            );
    });
});
