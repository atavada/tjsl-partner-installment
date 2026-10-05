<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementTransitionType;
use App\Enums\Permission;
use App\Exceptions\CyclicTransitionException;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AgreementTransition;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\User;
use App\Services\AgreementTransitionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');

    $this->partner = Partner::factory()->create();

    $this->agreement = Agreement::factory()->create([
        'partner_id' => $this->partner->id,
        'agreement_number' => 'PUMK/2024/050',
        'agreement_number_normalized' => 'PUMK/2024/050',
        'principal_amount' => 50_000_000,
        'total_amount' => 53_000_000,
        'lifecycle_status' => AgreementLifecycleStatus::Active,
        'effective_date' => '2024-01-01',
        'tenor_months' => 24,
    ]);

    // Create schedule for DEC-008 balance verification
    InstallmentSchedule::create([
        'agreement_id' => $this->agreement->id,
        'installment_number' => 1,
        'due_date' => '2024-02-01',
        'principal_due' => 2_083_333,
        'principal_paid' => 2_083_333,
        'interest_due' => 125_000,
        'interest_paid' => 125_000,
        'admin_charge_due' => 0,
        'admin_charge_paid' => 0,
        'other_charge_due' => 0,
        'other_charge_paid' => 0,
        'total_due' => 2_208_333,
        'total_paid' => 2_208_333,
        'version' => 1,
    ]);

    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::AgreementView);
    $this->operator->grantPermission(Permission::AgreementRestructure);

    $this->viewer = User::factory()->viewer()->create();
    $this->viewer->grantPermission(Permission::AgreementView);
});

describe('Agreement Restructure View & Authorization', function () {
    it('renders restructure interface for active agreement with balance comparison', function () {
        $response = $this->actingAs($this->operator)
            ->get("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/restructure");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agreements/Restructure')
                ->where('agreement.id', $this->agreement->id)
                ->has('balance')
            );
    });

    it('forbids restructure interface for closed or paid off agreements', function () {
        $closedAgreement = Agreement::factory()->create([
            'partner_id' => $this->partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
        ]);

        $response = $this->actingAs($this->operator)
            ->get("/partners/{$this->partner->id}/agreements/{$closedAgreement->id}/restructure");

        $response->assertForbidden();
    });

    it('forbids restructure interface for viewer role', function () {
        $response = $this->actingAs($this->viewer)
            ->get("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/restructure");

        $response->assertForbidden();
    });
});

describe('Agreement Restructure Execution Lifecycle', function () {
    it('restructures active agreement: creates successor and marks predecessor closed_by_rescheduling without mutating predecessor balance', function () {
        $file = UploadedFile::fake()->create('addendum_restrukturisasi.pdf', 300, 'application/pdf');

        $initialPredecessorTotal = $this->agreement->total_amount;
        $initialPredecessorPrincipal = $this->agreement->principal_amount;

        $payload = [
            'successor_agreement_number' => 'PUMK/2026/050-R1',
            'effective_date' => '2026-03-01',
            'reason' => 'Restrukturisasi perpanjangan tenor akibat kendala arus kas usaha mitra binaan.',
            'tenor_months' => 36,
            'approved_principal_amount' => 45_000_000,
            'approved_interest_amount' => 2_700_000,
            'approved_admin_charge_amount' => 150_000,
            'other_charge_amount' => 0,
            'interest_rate_percent' => 5.0,
            'addendum_document' => $file,
        ];

        $response = $this->actingAs($this->operator)
            ->post("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/restructure", $payload);

        // Predecessor checks (DEC-002, DEC-008)
        $this->agreement->refresh();
        expect($this->agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::ClosedByRescheduling);
        expect($this->agreement->lifecycle_status)->not->toBe(AgreementLifecycleStatus::PaidOff);
        expect($this->agreement->total_amount)->toBe($initialPredecessorTotal);
        expect($this->agreement->principal_amount)->toBe($initialPredecessorPrincipal);

        // Successor checks
        $successor = Agreement::where('agreement_number_normalized', 'PUMK/2026/050-R1')->first();
        expect($successor)->not->toBeNull();
        expect($successor->partner_id)->toBe($this->partner->id);
        expect($successor->principal_amount)->toBe(45_000_000);
        expect($successor->total_amount)->toBe(47_850_000);
        expect($successor->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);

        // Transition link check
        $transition = AgreementTransition::where('predecessor_id', $this->agreement->id)
            ->where('successor_id', $successor->id)
            ->first();
        expect($transition)->not->toBeNull();
        expect($transition->transition_type)->toBe(AgreementTransitionType::Rescheduling);
        expect($transition->reason)->toContain('Restrukturisasi perpanjangan tenor');
        expect($transition->approved_principal_amount)->toBe(45_000_000);

        // Document attachment check
        $doc = AgreementDocument::where('agreement_id', $successor->id)->first();
        expect($doc)->not->toBeNull();
        expect($doc->document_type)->toBe('addendum');
        expect($doc->transition_id)->toBe($transition->id);
        expect(Storage::disk('local')->exists($doc->file_path))->toBeTrue();

        $response->assertRedirect("/partners/{$this->partner->id}/agreements/{$successor->id}");
    });

    it('requires approval reason with minimum 5 characters', function () {
        $payload = [
            'successor_agreement_number' => 'PUMK/2026/050-R2',
            'effective_date' => '2026-03-01',
            'reason' => 'no', // too short
            'tenor_months' => 12,
            'approved_principal_amount' => 10_000_000,
        ];

        $response = $this->actingAs($this->operator)
            ->postJson("/partners/{$this->partner->id}/agreements/{$this->agreement->id}/restructure", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    });

    it('rejects cyclic restructuring attempt where successor is an ancestor', function () {
        $transitionService = app(AgreementTransitionService::class);

        // Predecessor is A ($this->agreement)
        // Create B
        $agreementB = Agreement::factory()->create(['partner_id' => $this->partner->id]);
        $transitionService->createTransition($this->agreement, $agreementB, AgreementTransitionType::Rescheduling);

        // Create C from B
        $agreementC = Agreement::factory()->create(['partner_id' => $this->partner->id]);
        $transitionService->createTransition($agreementB, $agreementC, AgreementTransitionType::Rescheduling);

        // Now attempt to restructure C -> A (cycle: A -> B -> C -> A)
        expect(fn () => $transitionService->validateNoCycle($agreementC->id, $this->agreement->id))
            ->toThrow(CyclicTransitionException::class);
    });
});
