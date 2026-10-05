<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::PaymentStage);
    $this->operator->grantPermission(Permission::PaymentPost);
    $this->operator->grantPermission(Permission::PartnerView);
    $this->operator->grantPermission(Permission::AgreementView);

    $this->auditor = User::factory()->auditor()->create();
    $this->auditor->grantPermission(Permission::PaymentStage);
    $this->auditor->grantPermission(Permission::PartnerView);
    $this->auditor->grantPermission(Permission::AgreementView);

    $this->partner = Partner::factory()->create([
        'name' => 'Mitra Uji Masking',
        'partner_no_id' => '000000000012345',
        'verification_state' => 'verified',
    ]);

    $this->agreement = Agreement::factory()->active()->create([
        'partner_id' => $this->partner->id,
        'agreement_number' => 'AGR/2026/10/0001',
    ]);

    $this->payment = BankTransaction::factory()->create([
        'payer_name' => 'Pembayar Uji',
        'payer_va' => '8800123456789012',
        'amount' => 5_000_000,
        'state' => PaymentState::Draft,
    ]);

    $this->allocation = PaymentAllocation::factory()->create([
        'bank_transaction_id' => $this->payment->id,
        'agreement_id' => $this->agreement->id,
        'total_amount' => 5_000_000,
        'principal_amount' => 5_000_000,
        'interest_amount' => 0,
        'admin_charge_amount' => 0,
        'other_charge_amount' => 0,
        'state' => PaymentState::Draft,
    ]);
});

describe('Payment API VA Masking (Finding #1)', function () {
    it('masks payer_va and omits payer_va_raw for unprivileged viewer in JSON endpoint', function () {
        $response = $this->actingAs($this->auditor)
            ->getJson("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}");

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();

        expect($data)
            ->toHaveKey('payer_va')
            ->and($data['payer_va'])->toBe('8800********9012')
            ->and($data)->not->toHaveKey('payer_va_raw');
    });

    it('returns unmasked payer_va and omits payer_va_raw for authorized cashier in JSON endpoint', function () {
        $response = $this->actingAs($this->operator)
            ->getJson("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/payments/{$this->payment->id}");

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();

        expect($data)
            ->toHaveKey('payer_va')
            ->and($data['payer_va'])->toBe('8800123456789012')
            ->and($data)->not->toHaveKey('payer_va_raw');
    });
});

describe('FundLot Search Oracle Elimination (Finding #6 & #22)', function () {
    beforeEach(function () {
        $this->abtTransaction = BankTransaction::factory()->create([
            'payer_name' => 'Wajib Bayar ABT',
            'payer_va' => '9900112233445566',
            'amount' => 2_500_000,
        ]);

        $this->fundLot = FundLot::factory()->create([
            'bank_transaction_id' => $this->abtTransaction->id,
            'lot_type' => FundLotType::Abt,
            'amount' => 2_500_000,
        ]);
    });

    it('yields zero search hits for unprivileged viewer probing by VA (search oracle closed)', function () {
        // Probing with exact VA
        $response = $this->actingAs($this->auditor)
            ->getJson('/abt?search=9900112233445566');

        $response->assertOk();
        $lots = $response->json('data') ?? $response->json();
        expect($lots)->toHaveCount(0);

        // Probing with partial VA substring
        $responsePartial = $this->actingAs($this->auditor)
            ->getJson('/abt?search=11223344');

        $responsePartial->assertOk();
        $lotsPartial = $responsePartial->json('data') ?? $responsePartial->json();
        expect($lotsPartial)->toHaveCount(0);
    });

    it('returns matching lot for authorized cashier searching by VA', function () {
        $response = $this->actingAs($this->operator)
            ->getJson('/abt?search=9900112233445566');

        $response->assertOk();
        $lots = $response->json('data') ?? $response->json();
        expect($lots)->toHaveCount(1)
            ->and($lots[0]['id'])->toBe($this->fundLot->id);
    });

    it('escapes SQL LIKE wildcards in fund lot search', function () {
        $wildcardTransaction = BankTransaction::factory()->create([
            'payer_name' => 'PT 100% Maju',
            'amount' => 1_000_000,
        ]);

        $wildcardLot = FundLot::factory()->create([
            'bank_transaction_id' => $wildcardTransaction->id,
            'lot_type' => FundLotType::Abt,
            'amount' => 1_000_000,
        ]);

        // Search with literal percent
        $response = $this->actingAs($this->operator)
            ->getJson('/abt?search=100%25');

        $response->assertOk();
        $lots = $response->json('data') ?? $response->json();
        expect($lots)->toHaveCount(1)
            ->and($lots[0]['id'])->toBe($wildcardLot->id);
    });
});
