<?php

declare(strict_types=1);

use App\Enums\FundLotType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

describe('HTTP POST /fund-lots/{fundLot}/allocate per TASK-REM-007', function () {
    beforeEach(function () {
        $this->operator = User::factory()->operator()->create();
        $this->operator->grantPermission(Permission::PaymentStage);
        $this->operator->grantPermission(Permission::PaymentPost);
        $this->operator->grantPermission(Permission::PartnerView);
        $this->operator->grantPermission(Permission::AgreementView);

        $this->viewer = User::factory()->create([
            'role' => Role::Auditor,
        ]);

        $this->partner = Partner::factory()->verified()->create();
        $this->agreement = Agreement::factory()->active()->create([
            'partner_id' => $this->partner->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
        ]);

        InstallmentSchedule::factory()->create([
            'agreement_id' => $this->agreement->id,
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => 1_000_000,
            'interest_due' => 0,
            'admin_charge_due' => 0,
            'other_charge_due' => 0,
            'total_due' => 1_000_000,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
        ]);

        $this->transaction = BankTransaction::factory()->create(['amount' => 1_000_000]);
        $this->lot = FundLot::create([
            'bank_transaction_id' => $this->transaction->id,
            'partner_id' => $this->partner->id,
            'lot_type' => FundLotType::IdentifiedUnallocated,
            'amount' => 1_000_000,
            'evidence' => 'slip_bank.pdf',
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);
    });

    it('allows authorized operator to allocate lot to agreement', function () {
        $response = $this->actingAs($this->operator)
            ->post("/fund-lots/{$this->lot->id}/allocate", [
                'agreement_id' => $this->agreement->id,
                'amount' => 500_000,
                'reason' => 'Alokasi ABT via controller',
                'effective_date' => '2026-03-20',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->lot->refresh();
        expect($this->lot->amount)->toBe(500_000);
    });

    it('returns json response with transfer details when requested with json headers', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/fund-lots/{$this->lot->id}/allocate", [
                'agreement_id' => $this->agreement->id,
                'amount' => 400_000,
                'reason' => 'Alokasi API',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'transfer_id',
                'lot_id',
                'remaining_capacity',
            ])
            ->assertJson([
                'lot_id' => $this->lot->id,
                'remaining_capacity' => 600_000,
            ]);
    });

    it('forbids unprivileged viewer from allocating fund lot', function () {
        $response = $this->actingAs($this->viewer)
            ->postJson("/fund-lots/{$this->lot->id}/allocate", [
                'agreement_id' => $this->agreement->id,
                'amount' => 500_000,
            ]);

        $response->assertForbidden();
    });

    it('rejects guest from allocating fund lot with 401', function () {
        $response = $this->postJson("/fund-lots/{$this->lot->id}/allocate", [
            'agreement_id' => $this->agreement->id,
            'amount' => 500_000,
        ]);

        $response->assertUnauthorized();
    });

    it('rejects allocation when amount exceeds remaining capacity with 422', function () {
        $response = $this->actingAs($this->operator)
            ->postJson("/fund-lots/{$this->lot->id}/allocate", [
                'agreement_id' => $this->agreement->id,
                'amount' => 1_500_000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    });

    it('rejects allocation when agreement belongs to different partner with 422', function () {
        $otherPartner = Partner::factory()->verified()->create();
        $otherAgreement = Agreement::factory()->active()->create([
            'partner_id' => $otherPartner->id,
            'principal_amount' => 500_000,
            'total_amount' => 500_000,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/fund-lots/{$this->lot->id}/allocate", [
                'agreement_id' => $otherAgreement->id,
                'amount' => 500_000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['agreement_id']);
    });

    it('renders FundLots/Show page with lot details and active agreements', function () {
        $response = $this->actingAs($this->operator)
            ->get("/fund-lots/{$this->lot->id}");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('FundLots/Show')
                ->has('lot')
                ->where('lot.id', $this->lot->id)
                ->where('lot.remaining_capacity', 1_000_000)
                ->has('lot.partner.active_agreements', 1)
            );
    });
});
