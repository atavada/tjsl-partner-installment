<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\ReceivableAdjustment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::PartnerView);
    $this->operator->grantPermission(Permission::VaReveal);

    $this->viewer = User::factory()->viewer()->create();
    $this->viewer->grantPermission(Permission::PartnerView);
});

describe('Partner Receivable Detail Drill-Down (TASK-REM-009 / FR-05 / Finding #23)', function () {
    it('returns partner show page with portfolio receivable rollup and itemized agreement breakdown', function () {
        $partner = Partner::first();
        expect($partner)->not->toBeNull();

        $response = $this->actingAs($this->operator)->get("/partners/{$partner->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Partners/Show')
            ->has('partner')
            ->has('receivable_rollup')
            ->has('receivable_rollup.contract_total')
            ->has('receivable_rollup.paid_total')
            ->has('receivable_rollup.remaining_total')
            ->has('agreements_detail')
            ->has('as_of')
        );
    });

    it('exposes bank transaction source coordinates within payment allocations', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'agreement_number' => 'AGR-COORD-001',
            'principal_amount' => 10_000_000,
            'interest_amount' => 1_000_000,
            'admin_charge_amount' => 0,
            'total_amount' => 11_000_000,
        ]);

        $bankTxn = BankTransaction::factory()->create([
            'reference' => 'TRX-COORD-12345',
            'payer_name' => 'Budi Santoso',
            'payer_va' => '9880012345678901',
            'source' => 'BANK_MUTASI_CSV',
            'source_row_identifier' => 'MUTASI_ROW_#42',
            'amount' => 2_000_000,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $bankTxn->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => 1_800_000,
            'interest_amount' => 200_000,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 2_000_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->operator)->get("/partners/{$partner->id}");
        $response->assertOk();

        $agreementsDetail = $response->viewData('page')['props']['agreements_detail'];
        expect($agreementsDetail)->toHaveCount(1);

        $allocations = $agreementsDetail[0]['payment_allocations'];
        expect($allocations)->toHaveCount(1);

        $firstAlloc = $allocations[0];
        expect($firstAlloc['bank_transaction'])->not->toBeNull()
            ->and($firstAlloc['bank_transaction']['reference'])->toBe('TRX-COORD-12345')
            ->and($firstAlloc['bank_transaction']['payer_name'])->toBe('Budi Santoso')
            ->and($firstAlloc['bank_transaction']['payer_va'])->toBe('9880012345678901')
            ->and($firstAlloc['bank_transaction']['source'])->toBe('BANK_MUTASI_CSV')
            ->and($firstAlloc['bank_transaction']['source_row_identifier'])->toBe('MUTASI_ROW_#42');
    });

    it('masks payer_va in source coordinates for viewers without va.reveal permission', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 5_000_000,
            'total_amount' => 5_000_000,
        ]);

        $bankTxn = BankTransaction::factory()->create([
            'reference' => 'TRX-MASK-789',
            'payer_va' => '9880099988877766',
            'amount' => 1_000_000,
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $bankTxn->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-15',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->viewer)->get("/partners/{$partner->id}");
        $response->assertOk();

        $agreementsDetail = $response->viewData('page')['props']['agreements_detail'];
        $va = $agreementsDetail[0]['payment_allocations'][0]['bank_transaction']['payer_va'];

        expect($va)->toContain('***')
            ->and($va)->not->toBe('9880099988877766');
    });

    it('surfaces receivable adjustments in the agreement drilldown', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 5_000_000,
            'total_amount' => 5_000_000,
        ]);

        ReceivableAdjustment::create([
            'agreement_id' => $agreement->id,
            'adjustment_type' => 'correction',
            'principal_amount' => -500_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => -500_000,
            'effective_date' => '2026-04-01',
            'reason' => 'Koreksi kelebihan pencatatan',
            'evidence' => 'MEMO-DIR-2026-01',
            'state' => PaymentState::Posted,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        $response = $this->actingAs($this->operator)->get("/partners/{$partner->id}");
        $response->assertOk();

        $agreementsDetail = $response->viewData('page')['props']['agreements_detail'];
        $adjustments = $agreementsDetail[0]['receivable_adjustments'];

        expect($adjustments)->toHaveCount(1)
            ->and($adjustments[0]['adjustment_type'])->toBe('correction')
            ->and($adjustments[0]['principal_amount'])->toBe(-500_000)
            ->and($adjustments[0]['reason'])->toBe('Koreksi kelebihan pencatatan')
            ->and($adjustments[0]['evidence'])->toBe('MEMO-DIR-2026-01');
    });

    it('surfaces integrity warnings when an agreement has corrupted overpaid schedules', function () {
        $partner = Partner::factory()->verified()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 5_000_000,
            'total_amount' => 5_000_000,
        ]);

        // Corrupted schedule where paid > due
        InstallmentSchedule::factory()->create([
            'agreement_id' => $agreement->id,
            'installment_number' => 1,
            'principal_due' => 1_000_000,
            'principal_paid' => 1_500_000, // Overpaid integrity error!
            'interest_due' => 0,
            'interest_paid' => 0,
            'admin_charge_due' => 0,
            'admin_charge_paid' => 0,
            'other_charge_due' => 0,
            'other_charge_paid' => 0,
            'total_due' => 1_000_000,
            'total_paid' => 1_500_000,
        ]);

        $response = $this->actingAs($this->operator)->get("/partners/{$partner->id}");
        $response->assertOk();

        $rollup = $response->viewData('page')['props']['receivable_rollup'];
        expect($rollup['warnings'])->not->toBeEmpty();
    });
});
