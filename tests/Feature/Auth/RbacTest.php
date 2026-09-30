<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Partner;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\MaskingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Register temporary test route for middleware verification
    Route::get('/test-financial-action', function () {
        return response()->json(['status' => 'authorized']);
    })->middleware(['role:operator,reconciliation_reviewer']);

    Route::get('/test-owner-action', function () {
        return response()->json(['status' => 'authorized']);
    })->middleware(['role:process_owner']);
});

describe('PRD Roles Enum', function () {
    it('defines exactly the five PRD §3 roles', function () {
        $expectedRoles = [
            'operator',
            'reconciliation_reviewer',
            'process_owner',
            'auditor',
            'system_admin',
        ];

        $actualRoles = array_map(fn (Role $r) => $r->value, Role::cases());

        expect($actualRoles)->toBe($expectedRoles);
    });

    it('provides correct labels for each role', function () {
        expect(Role::Operator->label())->toBe('Operator');
        expect(Role::ReconciliationReviewer->label())->toBe('Reconciliation Reviewer');
        expect(Role::ProcessOwner->label())->toBe('Process Owner');
        expect(Role::Auditor->label())->toBe('Auditor');
        expect(Role::SystemAdmin->label())->toBe('System Admin');
    });

    it('correctly identifies financial vs non-financial roles', function () {
        expect(Role::Operator->isFinancial())->toBeTrue();
        expect(Role::ReconciliationReviewer->isFinancial())->toBeTrue();
        expect(Role::ProcessOwner->isFinancial())->toBeTrue();
        expect(Role::Auditor->isFinancial())->toBeFalse();
        expect(Role::SystemAdmin->isFinancial())->toBeFalse();
    });
});

describe('User Role Assignment and Persistence', function () {
    it('persists and casts user role enum', function () {
        $user = User::factory()->create(['role' => Role::Auditor]);

        $retrieved = User::find($user->id);

        expect($retrieved->role)->toBe(Role::Auditor);
        expect($retrieved->role->value)->toBe('auditor');
        expect($retrieved->isAuditor())->toBeTrue();
        expect($retrieved->isSystemAdmin())->toBeFalse();
    });

    it('defaults user role to operator in factory', function () {
        $user = User::factory()->create();

        expect($user->role)->toBe(Role::Operator);
        expect($user->isOperator())->toBeTrue();
    });

    it('supports factory states for all five PRD roles', function () {
        expect(User::factory()->operator()->make()->role)->toBe(Role::Operator);
        expect(User::factory()->reconciliationReviewer()->make()->role)->toBe(Role::ReconciliationReviewer);
        expect(User::factory()->processOwner()->make()->role)->toBe(Role::ProcessOwner);
        expect(User::factory()->auditor()->make()->role)->toBe(Role::Auditor);
        expect(User::factory()->systemAdmin()->make()->role)->toBe(Role::SystemAdmin);
    });

    it('verifies hasRole with strings and enum instances', function () {
        $user = User::factory()->operator()->create();

        expect($user->hasRole(Role::Operator))->toBeTrue();
        expect($user->hasRole('operator'))->toBeTrue();
        expect($user->hasRole(Role::Auditor, Role::SystemAdmin))->toBeFalse();
        expect($user->hasRole(Role::Auditor, Role::Operator))->toBeTrue();
    });
});

describe('EnsureRoleAuthorized Middleware (Gate test: unauthorized posting denied)', function () {
    it('returns 401 when request is unauthenticated', function () {
        $response = test()->getJson('/test-financial-action');

        $response->assertStatus(401);
    });

    it('returns 403 when user does not have required role', function () {
        $auditor = User::factory()->auditor()->create();

        $response = test()->actingAs($auditor)->getJson('/test-financial-action');

        $response->assertStatus(403);
    });

    it('denies system_admin access to financial actions without explicit role (PRD FR-06)', function () {
        // System admin privilege does NOT bypass financial approval or operations
        $admin = User::factory()->systemAdmin()->create();

        $response = test()->actingAs($admin)->getJson('/test-financial-action');

        $response->assertStatus(403);
    });

    it('allows access when user possesses an authorized role', function () {
        $operator = User::factory()->operator()->create();
        $reviewer = User::factory()->reconciliationReviewer()->create();

        $response1 = test()->actingAs($operator)->getJson('/test-financial-action');
        $response2 = test()->actingAs($reviewer)->getJson('/test-financial-action');

        $response1->assertOk();
        $response2->assertOk();
    });
});

describe('Deny-by-Default Policies (DEC-009)', function () {
    it('denies Partner actions by default for all roles including System Admin', function () {
        $partner = Partner::factory()->create();
        $roles = [
            User::factory()->operator()->create(),
            User::factory()->reconciliationReviewer()->create(),
            User::factory()->processOwner()->create(),
            User::factory()->auditor()->create(),
            User::factory()->systemAdmin()->create(),
        ];

        foreach ($roles as $user) {
            expect(Gate::forUser($user)->allows('viewAny', Partner::class))->toBeFalse();
            expect(Gate::forUser($user)->allows('view', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('create', Partner::class))->toBeFalse();
            expect(Gate::forUser($user)->allows('update', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('delete', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('revealNik', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('revealPhone', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('revealAddress', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('export', Partner::class))->toBeFalse();
        }
    });

    it('denies physical delete of partner under Invariant 4', function () {
        $partner = Partner::factory()->create();
        $user = User::factory()->systemAdmin()->create();

        // Even with grant, delete is strictly barred in code
        $user->grantPermission(Permission::PartnerUpdate);

        expect(Gate::forUser($user)->allows('delete', $partner))->toBeFalse();
    });

    it('allows action when explicit capability grant is present', function () {
        $partner = Partner::factory()->create();
        $user = User::factory()->operator()->create();

        expect(Gate::forUser($user)->allows('view', $partner))->toBeFalse();

        $user->grantPermission(Permission::PartnerView);
        expect(Gate::forUser($user)->allows('view', $partner))->toBeTrue();

        $user->revokePermission(Permission::PartnerView);
        expect(Gate::forUser($user)->allows('view', $partner))->toBeFalse();
    });

    it('denies VirtualAccount actions by default', function () {
        $partner = Partner::factory()->create();
        $va = VirtualAccount::factory()->create(['partner_id' => $partner->id]);
        $user = User::factory()->operator()->create();

        expect(Gate::forUser($user)->allows('view', $va))->toBeFalse();
        expect(Gate::forUser($user)->allows('revealVaNumber', $va))->toBeFalse();
        expect(Gate::forUser($user)->allows('delete', $va))->toBeFalse();

        $user->grantPermission(Permission::VaReveal);
        expect(Gate::forUser($user)->allows('revealVaNumber', $va))->toBeTrue();
    });
});

describe('Sensitive Field Masking (DEC-004)', function () {
    it('masks NIK preserving only first 2 and last 4 characters', function () {
        $masking = new MaskingService;

        expect($masking->maskNik('3512345678900001'))->toBe('35**********0001');
        expect($masking->maskNik(null))->toBeNull();
        expect($masking->maskNik(''))->toBeNull();
    });

    it('masks phone preserving first 3 and last 2 characters', function () {
        $masking = new MaskingService;

        expect($masking->maskPhone('08123456789'))->toBe('081******89');
        expect($masking->maskPhone(null))->toBeNull();
    });

    it('masks address preserving prefix and obscuring detail', function () {
        $masking = new MaskingService;

        $masked = $masking->maskAddress('Jl. Pemuda No. 123 Malang');
        expect($masked)->toStartWith('Jl. ');
        expect($masked)->toContain('*');
        expect($masking->maskAddress(null))->toBeNull();
    });

    it('masks VA number preserving first 4 and last 4 characters', function () {
        $masking = new MaskingService;

        expect($masking->maskVaNumber('988212345678'))->toBe('9882****5678');
        expect($masking->maskVaNumber(null))->toBeNull();
    });

    it('returns masked partner payload by default', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'phone' => '08123456789',
            'address' => 'Jl. Pahlawan No. 45',
        ]);
        $operator = User::factory()->operator()->create();

        $payload = $masking->maskPartner($partner, $operator);

        expect($payload['nik'])->toBe('35**********0001');
        expect($payload['phone'])->toBe('081******89');
        expect($payload['address'])->toStartWith('Jl. ');
        expect($payload['address'])->toContain('*');
        expect($payload['is_masked'])->toBeTrue();
    });

    it('unmasks fields in payload when explicit permissions are granted', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'phone' => '08123456789',
            'address' => 'Jl. Pahlawan No. 45',
        ]);
        $user = User::factory()->reconciliationReviewer()->create();
        $user->grantPermission(Permission::NikReveal)
            ->grantPermission(Permission::PhoneReveal)
            ->grantPermission(Permission::AddressReveal);

        $payload = $masking->maskPartner($partner, $user);

        expect($payload['nik'])->toBe('3512345678900001');
        expect($payload['phone'])->toBe('08123456789');
        expect($payload['address'])->toBe('Jl. Pahlawan No. 45');
        expect($payload['is_masked'])->toBeFalse();
    });
});

describe('Sensitive Field Reveal and Audit Logging (DEC-004 & Gate Test)', function () {
    it('denies unmasking without explicit grant and logs audit attempt without raw PII', function () {
        Log::spy();
        $masking = new MaskingService;
        $partner = Partner::factory()->create(['nik' => '3512345678900001']);
        $operator = User::factory()->operator()->create();

        try {
            $masking->reveal($operator, $partner, 'nik', 'Identity verification for audit');
            $this->fail('Expected AuthorizationException was not thrown');
        } catch (AuthorizationException $e) {
            expect($e->getMessage())->toContain('Access denied for sensitive field reveal: [nik]');
        }

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return $message === 'Sensitive field unmask attempt'
                && $context['event'] === 'sensitive_field_unmask_attempt'
                && $context['field'] === 'nik'
                && $context['permission'] === 'nik.reveal'
                && $context['purpose'] === 'Identity verification for audit'
                && $context['allowed'] === false
                // CRITICAL SECURITY ASSERTION: No raw PII in log context!
                && ! in_array('3512345678900001', $context, true)
                && ! str_contains(json_encode($context), '3512345678900001');
        })->once();
    });

    it('allows unmasking with explicit grant and logs success without raw PII', function () {
        Log::spy();
        $masking = new MaskingService;
        $partner = Partner::factory()->create(['nik' => '3512345678900001']);
        $reviewer = User::factory()->reconciliationReviewer()->create();
        $reviewer->grantPermission(Permission::NikReveal);

        $revealed = $masking->reveal($reviewer, $partner, 'nik', 'Authorized case review');

        expect($revealed)->toBe('3512345678900001');

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return $message === 'Sensitive field unmask attempt'
                && $context['field'] === 'nik'
                && $context['allowed'] === true
                // CRITICAL SECURITY ASSERTION: Raw NIK never recorded in log
                && ! in_array('3512345678900001', $context, true)
                && ! str_contains(json_encode($context), '3512345678900001');
        })->once();
    });
});
