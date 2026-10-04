<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\AgreementTransitionType;
use App\Enums\CollectibilityStatus;
use App\Enums\Permission;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AgreementTransition;
use App\Models\Partner;
use App\Models\User;
use App\Services\BalanceService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->authorizedUser = User::factory()->operator()->create();
    $this->authorizedUser->grantPermission(Permission::PartnerView);
    $this->authorizedUser->grantPermission(Permission::AgreementView);

    $this->noAgreementPermissionUser = User::factory()->operator()->create();
    $this->noAgreementPermissionUser->grantPermission(Permission::PartnerView);

    $this->unauthorizedUser = User::factory()->operator()->create();
});

describe('Agreement Timeline Authentication & Authorization', function () {
    it('redirects unauthenticated guests to login', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $this->get("/partners/{$partner->id}/agreements")->assertRedirect('/login');
        $this->get("/partners/{$partner->id}/agreements/{$agreement->id}")->assertRedirect('/login');
    });

    it('forbids users without agreement.view permission', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($this->noAgreementPermissionUser)
            ->get("/partners/{$partner->id}/agreements")
            ->assertForbidden();

        $this->actingAs($this->noAgreementPermissionUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}")
            ->assertForbidden();
    });

    it('forbids completely unauthorized users', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $this->actingAs($this->unauthorizedUser)
            ->get("/partners/{$partner->id}/agreements")
            ->assertForbidden();

        $this->actingAs($this->unauthorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}")
            ->assertForbidden();
    });

    it('allows authorized users to view timeline index and show pages', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $indexResponse = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements");

        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Index')
            ->has('partner')
            ->has('agreements')
            ->where('partner.id', $partner->id)
            ->where('agreements.0.id', $agreement->id)
        );

        $showResponse = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}");

        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->has('partner')
            ->has('agreement')
            ->where('partner.id', $partner->id)
            ->where('agreement.id', $agreement->id)
        );
    });

    it('prevents cross-partner agreement access returning 404', function () {
        $partnerA = Partner::factory()->create();
        $partnerB = Partner::factory()->create();
        $agreementB = Agreement::factory()->create(['partner_id' => $partnerB->id]);

        // Trying to access partner B's agreement through partner A route
        $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partnerA->id}/agreements/{$agreementB->id}")
            ->assertNotFound();
    });
});

describe('DEC-008 Balance Stub — Unverified and Never Zero (Gate Test)', function () {
    it('returns explicit unverified balance status and components, never zero', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 20_000_000,
            'total_amount' => 21_200_000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->where('agreement.balance.status', 'unverified')
            ->where('agreement.balance.label', 'Belum Terverifikasi')
            ->where('agreement.balance.principal_remaining', 'unverified')
            ->where('agreement.balance.interest_remaining', 'unverified')
            ->where('agreement.balance.admin_charge_remaining', 'unverified')
            ->where('agreement.balance.other_charge_remaining', 'unverified')
            ->where('agreement.balance.total_remaining', 'unverified')
            ->where('agreement.balance.creates_debt', true)
        );

        // Direct BalanceService unit verification
        $balanceService = app(BalanceService::class);
        $balance = $balanceService->getBalance($agreement);

        expect($balance['status'])->toBe('unverified');
        expect($balance['principal_remaining'])->toBe('unverified');
        expect($balance['total_remaining'])->toBe('unverified');
        expect($balance['principal_remaining'])->not->toBe(0);
        expect($balance['total_remaining'])->not->toBe(0);
        expect($balance['principal_remaining'])->not->toBe('0');
        expect($balance['total_remaining'])->not->toBe('0');
    });

    it('identifies draft agreements as creating no debt (PRD FR-02)', function () {
        $partner = Partner::factory()->create();
        $draftAgreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$draftAgreement->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->where('agreement.is_draft', true)
            ->where('agreement.balance.is_draft', true)
            ->where('agreement.balance.creates_debt', false)
            ->where('agreement.balance.status', 'draft')
        );

        // Direct BalanceService check
        $balanceService = app(BalanceService::class);
        $balance = $balanceService->getBalance($draftAgreement);

        expect($balance['is_draft'])->toBeTrue();
        expect($balance['creates_debt'])->toBeFalse();
    });
});

describe('PRD §4 Invariant 8 — Three Independent Status Dimensions (Gate Test)', function () {
    it('provides three status dimensions independently in resource', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'lifecycle_status' => AgreementLifecycleStatus::Active,
            'collectibility_status' => CollectibilityStatus::Lancar,
            'signing_status' => AgreementSigningStatus::Signed,
            'signature_summary' => SignatureSummary::Signed,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->where('agreement.status_dimensions.lifecycle.status', 'active')
            ->where('agreement.status_dimensions.lifecycle.label', 'Aktif')
            ->where('agreement.status_dimensions.collectibility.status', 'lancar')
            ->where('agreement.status_dimensions.collectibility.label', 'Lancar')
            ->where('agreement.status_dimensions.signing.status', 'signed')
            ->where('agreement.status_dimensions.signing.label', 'Sudah Ditandatangani')
            ->where('agreement.status_dimensions.signing.summary', 'signed')
            ->where('agreement.status_dimensions.signing.summary_label', 'Sudah TTD')
        );
    });

    it('status changes in signing or lifecycle do not change balances', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'principal_amount' => 15_000_000,
            'interest_amount' => 900_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 0,
            'total_amount' => 16_000_000,
            'lifecycle_status' => AgreementLifecycleStatus::Active,
            'signing_status' => AgreementSigningStatus::Draft,
        ]);

        $balanceService = app(BalanceService::class);
        $initialBalance = $balanceService->getBalance($agreement);

        // Update signing status
        $agreement->update(['signing_status' => AgreementSigningStatus::Signed]);
        $agreement->refresh();
        $afterSigningBalance = $balanceService->getBalance($agreement);

        // Update collectibility status
        $agreement->update(['collectibility_status' => CollectibilityStatus::KurangLancar]);
        $agreement->refresh();
        $afterCollectibilityBalance = $balanceService->getBalance($agreement);

        // Amounts and unverified status are strictly invariant
        expect($agreement->principal_amount)->toBe(15_000_000);
        expect($agreement->total_amount)->toBe(16_000_000);
        expect($initialBalance['status'])->toBe($afterSigningBalance['status']);
        expect($afterSigningBalance['status'])->toBe($afterCollectibilityBalance['status']);
        expect($afterCollectibilityBalance['status'])->toBe('unverified');
    });
});

describe('Agreement Timeline Predecessor and Successor Links', function () {
    it('displays predecessor and successor relationships in agreement detail', function () {
        $partner = Partner::factory()->create();

        $originalAgreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'agreement_number' => '0001/PUMK/2024',
            'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
        ]);

        $rescheduledAgreement = Agreement::factory()->create([
            'partner_id' => $partner->id,
            'agreement_number' => '0002/PUMK/2026',
            'lifecycle_status' => AgreementLifecycleStatus::Active,
        ]);

        AgreementTransition::factory()->create([
            'predecessor_id' => $originalAgreement->id,
            'successor_id' => $rescheduledAgreement->id,
            'transition_type' => AgreementTransitionType::Rescheduling,
            'reason' => 'Restrukturisasi angsuran mitra',
        ]);

        // When viewing the rescheduled agreement, predecessor is loaded
        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$rescheduledAgreement->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->has('agreement.predecessors', 1)
            ->where('agreement.predecessors.0.id', $originalAgreement->id)
            ->where('agreement.predecessors.0.agreement_number', '0001/PUMK/2024')
        );

        // When viewing the original agreement, successor is loaded
        $origResponse = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$originalAgreement->id}");

        $origResponse->assertOk();
        $origResponse->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->has('agreement.successors', 1)
            ->where('agreement.successors.0.id', $rescheduledAgreement->id)
            ->where('agreement.successors.0.agreement_number', '0002/PUMK/2026')
        );
    });

    it('loads documents for an agreement', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        AgreementDocument::factory()->create([
            'agreement_id' => $agreement->id,
            'file_name' => 'surat_perjanjian_mitra.pdf',
            'document_type' => 'contract',
            'document_version' => 1,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get("/partners/{$partner->id}/agreements/{$agreement->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Agreements/Show')
            ->has('agreement.documents', 1)
            ->where('agreement.documents.0.file_name', 'surat_perjanjian_mitra.pdf')
            ->where('agreement.documents.0.document_type', 'contract')
        );
    });
});

describe('JSON API response for Agreements', function () {
    it('returns JSON resource collection when requested', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson("/partners/{$partner->id}/agreements");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'agreement_number',
                    'principal_amount',
                    'total_amount',
                    'status_dimensions',
                    'balance',
                ],
            ],
        ]);
        expect($response->json('data.0.id'))->toBe($agreement->id);
    });

    it('returns single JSON resource when requested', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);

        $response = $this->actingAs($this->authorizedUser)
            ->getJson("/partners/{$partner->id}/agreements/{$agreement->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'agreement_number',
                'status_dimensions',
                'balance',
            ],
        ]);
        expect($response->json('data.id'))->toBe($agreement->id);
    });
});
