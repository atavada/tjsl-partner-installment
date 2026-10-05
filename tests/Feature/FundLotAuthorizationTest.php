<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->transaction = BankTransaction::factory()->create(['amount' => 1_000_000]);
    $this->fundLot = FundLot::createAbtLot($this->transaction, 1_000_000);
    $this->partner = Partner::factory()->verified()->create();
});

describe('TASK-REM-002: Deny-by-Default Authorization on Fund Lot & ABT Routes', function () {
    describe('Unauthenticated access (Guests)', function () {
        it('redirects guest from GET /abt to login', function () {
            $this->get('/abt')
                ->assertRedirect('/login');
        });

        it('redirects guest from GET /fund-lots to login', function () {
            $this->get('/fund-lots')
                ->assertRedirect('/login');
        });

        it('rejects guest from GET /fund-lots/{fundLot} with 401', function () {
            $this->getJson("/fund-lots/{$this->fundLot->id}")
                ->assertUnauthorized();
        });

        it('rejects guest from POST /fund-lots/abt with 401', function () {
            $this->postJson('/fund-lots/abt', [
                'idempotency_key' => Str::uuid()->toString(),
                'amount' => 500_000,
                'receipt_date' => '2026-03-20',
            ])->assertUnauthorized();
        });

        it('rejects guest from POST /fund-lots/{fundLot}/identify with 401', function () {
            $this->postJson("/fund-lots/{$this->fundLot->id}/identify", [
                'partner_id' => $this->partner->id,
                'evidence' => 'Slip setoran bank valid',
            ])->assertUnauthorized();
        });
    });

    describe('Unprivileged Viewer / Auditor (No explicit permissions)', function () {
        beforeEach(function () {
            $this->viewer = User::factory()->create([
                'role' => Role::Auditor,
            ]);
        });

        it('forbids viewer from GET /abt', function () {
            $this->actingAs($this->viewer)
                ->get('/abt')
                ->assertForbidden();
        });

        it('forbids viewer from GET /fund-lots', function () {
            $this->actingAs($this->viewer)
                ->get('/fund-lots')
                ->assertForbidden();
        });

        it('forbids viewer from GET /fund-lots/{fundLot}', function () {
            $this->actingAs($this->viewer)
                ->getJson("/fund-lots/{$this->fundLot->id}")
                ->assertForbidden();
        });

        it('forbids viewer from POST /fund-lots/abt', function () {
            $this->actingAs($this->viewer)
                ->postJson('/fund-lots/abt', [
                    'idempotency_key' => Str::uuid()->toString(),
                    'amount' => 500_000,
                    'receipt_date' => '2026-03-20',
                ])->assertForbidden();
        });

        it('forbids viewer from POST /fund-lots/{fundLot}/identify', function () {
            $this->actingAs($this->viewer)
                ->postJson("/fund-lots/{$this->fundLot->id}/identify", [
                    'partner_id' => $this->partner->id,
                    'evidence' => 'Slip setoran bank valid',
                ])->assertForbidden();
        });
    });

    describe('Read-Only Scoped User (partner.view only)', function () {
        beforeEach(function () {
            $this->readOnlyUser = User::factory()->create([
                'role' => Role::Auditor,
            ]);
            $this->readOnlyUser->grantPermission(Permission::PartnerView);
        });

        it('allows reading GET /abt', function () {
            $this->actingAs($this->readOnlyUser)
                ->get('/abt')
                ->assertSuccessful();
        });

        it('allows reading GET /fund-lots', function () {
            $this->actingAs($this->readOnlyUser)
                ->get('/fund-lots')
                ->assertSuccessful();
        });

        it('allows reading GET /fund-lots/{fundLot}', function () {
            $this->actingAs($this->readOnlyUser)
                ->getJson("/fund-lots/{$this->fundLot->id}")
                ->assertSuccessful();
        });

        it('forbids mutation via POST /fund-lots/abt', function () {
            $this->actingAs($this->readOnlyUser)
                ->postJson('/fund-lots/abt', [
                    'idempotency_key' => Str::uuid()->toString(),
                    'amount' => 500_000,
                    'receipt_date' => '2026-03-20',
                ])->assertForbidden();
        });

        it('forbids mutation via POST /fund-lots/{fundLot}/identify', function () {
            $this->actingAs($this->readOnlyUser)
                ->postJson("/fund-lots/{$this->fundLot->id}/identify", [
                    'partner_id' => $this->partner->id,
                    'evidence' => 'Slip setoran bank valid',
                ])->assertForbidden();
        });
    });

    describe('Authorized Operator (Kasir TJSL)', function () {
        beforeEach(function () {
            $this->operator = User::factory()->operator()->create();
            $this->operator->grantPermission(Permission::PaymentStage);
        });

        it('allows operator to view GET /abt', function () {
            $this->actingAs($this->operator)
                ->get('/abt')
                ->assertSuccessful();
        });

        it('allows operator to view GET /fund-lots', function () {
            $this->actingAs($this->operator)
                ->get('/fund-lots')
                ->assertSuccessful();
        });

        it('allows operator to view GET /fund-lots/{fundLot}', function () {
            $this->actingAs($this->operator)
                ->getJson("/fund-lots/{$this->fundLot->id}")
                ->assertSuccessful();
        });

        it('allows operator to capture ABT deposit via POST /fund-lots/abt', function () {
            $this->actingAs($this->operator)
                ->postJson('/fund-lots/abt', [
                    'idempotency_key' => Str::uuid()->toString(),
                    'amount' => 500_000,
                    'receipt_date' => '2026-03-20',
                    'reference' => 'TXN-ABT-AUTH-001',
                    'evidence' => 'rekening_koran.pdf',
                ])->assertStatus(201);
        });

        it('allows operator to identify ABT lot via POST /fund-lots/{fundLot}/identify', function () {
            $this->actingAs($this->operator)
                ->postJson("/fund-lots/{$this->fundLot->id}/identify", [
                    'partner_id' => $this->partner->id,
                    'evidence' => 'Slip setoran bank terverifikasi',
                ])->assertStatus(200);
        });
    });
});
