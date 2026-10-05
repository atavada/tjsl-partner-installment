<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\Permission;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');

    $this->partner = Partner::factory()->create();

    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::AgreementView);
    $this->operator->grantPermission(Permission::AgreementCreate);

    $this->viewer = User::factory()->viewer()->create();
    $this->viewer->grantPermission(Permission::AgreementView);
});

describe('Agreement Creation View & Authorization', function () {
    it('renders create agreement form for authorized operator', function () {
        $response = $this->actingAs($this->operator)
            ->get('/agreements/create');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agreements/Create')
                ->has('partners')
            );
    });

    it('renders create agreement form scoped to partner', function () {
        $response = $this->actingAs($this->operator)
            ->get("/partners/{$this->partner->id}/agreements/create");

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Agreements/Create')
                ->where('selectedPartnerId', $this->partner->id)
            );
    });

    it('forbids creation view to unauthorized viewer', function () {
        $response = $this->actingAs($this->viewer)
            ->get('/agreements/create');

        $response->assertForbidden();
    });
});

describe('Agreement Creation Storage & Invariants', function () {
    it('creates draft agreement with calculated total amount and normalized agreement number', function () {
        $payload = [
            'partner_id' => $this->partner->id,
            'agreement_number' => '  pumk/2026/001  ',
            'batch_year' => '2026',
            'business_group' => 'Pertanian',
            'tenor_months' => 12,
            'effective_date' => '2026-02-01',
            'first_due_date' => '2026-03-01',
            'maturity_date' => '2027-02-01',
            'principal_amount' => 20_000_000,
            'interest_amount' => 1_200_000,
            'admin_charge_amount' => 100_000,
            'other_charge_amount' => 50_000,
            'interest_rate_percent' => 6.0,
        ];

        $response = $this->actingAs($this->operator)
            ->post('/agreements', $payload);

        $agreement = Agreement::where('agreement_number_normalized', 'PUMK/2026/001')->first();
        expect($agreement)->not->toBeNull();
        expect($agreement->partner_id)->toBe($this->partner->id);
        expect($agreement->total_amount)->toBe(21_350_000); // 20M + 1.2M + 100k + 50k
        expect($agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Draft);
        expect($agreement->signing_status)->toBe(AgreementSigningStatus::NotPrepared);
        expect($agreement->signature_summary)->toBe(SignatureSummary::Unknown);

        $response->assertRedirect("/partners/{$this->partner->id}/agreements/{$agreement->id}");
    });

    it('attaches uploaded initial contract PDF upon creation', function () {
        $file = UploadedFile::fake()->create('contract.pdf', 150, 'application/pdf');

        $payload = [
            'partner_id' => $this->partner->id,
            'agreement_number' => 'PUMK/2026/002',
            'tenor_months' => 24,
            'effective_date' => '2026-03-01',
            'principal_amount' => 30_000_000,
            'interest_amount' => 1_800_000,
            'document' => $file,
        ];

        $response = $this->actingAs($this->operator)
            ->post('/agreements', $payload);

        $agreement = Agreement::where('agreement_number_normalized', 'PUMK/2026/002')->first();
        expect($agreement)->not->toBeNull();

        $doc = AgreementDocument::where('agreement_id', $agreement->id)->first();
        expect($doc)->not->toBeNull();
        expect($doc->file_name)->toBe('contract.pdf');
        expect($doc->document_type)->toBe('contract');
        expect(Storage::disk('local')->exists($doc->file_path))->toBeTrue();
    });

    it('validates required fields with 422', function () {
        $response = $this->actingAs($this->operator)
            ->postJson('/agreements', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'partner_id',
                'agreement_number',
                'tenor_months',
                'effective_date',
                'principal_amount',
            ]);
    });

    it('forbids agreement creation by viewer role', function () {
        $payload = [
            'partner_id' => $this->partner->id,
            'agreement_number' => 'PUMK/2026/003',
            'tenor_months' => 12,
            'effective_date' => '2026-04-01',
            'principal_amount' => 10_000_000,
        ];

        $response = $this->actingAs($this->viewer)
            ->postJson('/agreements', $payload);

        $response->assertForbidden();
    });
});
