<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use App\Models\PaymentAllocation;
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
    it('defines the confirmed PRD §3 and DEC-009 roles including formal Viewer', function () {
        $expectedRoles = [
            'operator',
            'reconciliation_reviewer',
            'process_owner',
            'viewer',
            'auditor',
            'system_admin',
        ];

        $actualRoles = array_map(fn (Role $r) => $r->value, Role::cases());

        expect($actualRoles)->toBe($expectedRoles);
    });

    it('provides correct labels for each role', function () {
        expect(Role::Operator->label())->toBe('Kasir TJSL');
        expect(Role::Kasir->label())->toBe('Kasir TJSL');
        expect(Role::ReconciliationReviewer->label())->toBe('Kepala Sub Divisi');
        expect(Role::KepalaSubDivisi->label())->toBe('Kepala Sub Divisi');
        expect(Role::ProcessOwner->label())->toBe('Sekper / Kepala Divisi');
        expect(Role::Sekper->label())->toBe('Sekper / Kepala Divisi');
        expect(Role::Viewer->label())->toBe('Viewer');
        expect(Role::Auditor->label())->toBe('Viewer');
        expect(Role::SystemAdmin->label())->toBe('System Admin');
    });

    it('provides role aliases matching DEC-009 terminology', function () {
        expect(Role::Operator)->toBe(Role::Kasir);
        expect(Role::ReconciliationReviewer)->toBe(Role::KepalaSubDivisi);
        expect(Role::ProcessOwner)->toBe(Role::Sekper);
    });

    it('provides businessName as alias for label per DEC-009', function () {
        foreach (Role::cases() as $role) {
            expect($role->businessName())->toBe($role->label());
        }
    });

    it('asserts confirmed business names differ from legacy English stubs', function () {
        expect(Role::Auditor->label())->toBe('Viewer')
            ->and(Role::Auditor->label())->not->toBe('Auditor')
            ->and(Role::Operator->label())->toBe('Kasir TJSL')
            ->and(Role::Operator->label())->not->toBe('Operator');
    });

    it('correctly identifies financial vs non-financial roles', function () {
        expect(Role::Operator->isFinancial())->toBeTrue();
        expect(Role::Kasir->isFinancial())->toBeTrue();
        expect(Role::ReconciliationReviewer->isFinancial())->toBeTrue();
        expect(Role::KepalaSubDivisi->isFinancial())->toBeTrue();
        expect(Role::ProcessOwner->isFinancial())->toBeTrue();
        expect(Role::Sekper->isFinancial())->toBeTrue();
        expect(Role::Viewer->isFinancial())->toBeFalse();
        expect(Role::Auditor->isFinancial())->toBeFalse();
        expect(Role::SystemAdmin->isFinancial())->toBeFalse();
    });

    it('contains no allocation.approve permission per DEC-005 and TASK-REM-005', function () {
        $allPermissions = array_map(fn (Permission $p) => $p->value, Permission::cases());
        expect($allPermissions)->not->toContain('allocation.approve');
        expect(defined(Permission::class.'::AllocationApprove'))->toBeFalse();
    });
});

describe('User Role Assignment and Persistence', function () {
    it('persists and casts user role enum', function () {
        $user = User::factory()->create(['role' => Role::Auditor]);

        $retrieved = User::find($user->id);

        expect($retrieved->role)->toBe(Role::Auditor);
        expect($retrieved->role->value)->toBe('auditor');
        expect($retrieved->role_label)->toBe('Viewer');
        expect($retrieved->isAuditor())->toBeTrue();
        expect($retrieved->isSystemAdmin())->toBeFalse();
    });

    it('defaults user role to operator in factory', function () {
        $user = User::factory()->create();

        expect($user->role)->toBe(Role::Operator);
        expect($user->role_label)->toBe('Kasir TJSL');
        expect($user->isOperator())->toBeTrue();
    });

    it('supports factory states for all confirmed roles', function () {
        expect(User::factory()->operator()->make()->role)->toBe(Role::Operator);
        expect(User::factory()->reconciliationReviewer()->make()->role)->toBe(Role::ReconciliationReviewer);
        expect(User::factory()->processOwner()->make()->role)->toBe(Role::ProcessOwner);
        expect(User::factory()->viewer()->make()->role)->toBe(Role::Viewer);
        expect(User::factory()->auditor()->make()->role)->toBe(Role::Viewer);
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

    it('allows system_admin access to any action via superadmin bypass', function () {
        $admin = User::factory()->systemAdmin()->create();

        $response = test()->actingAs($admin)->getJson('/test-financial-action');

        $response->assertOk();
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
    it('denies Partner actions by default for non-admin roles while respecting DEC-004 sensitive access', function () {
        $partner = Partner::factory()->create();
        $nonViewers = [
            User::factory()->operator()->create(),
            User::factory()->reconciliationReviewer()->create(),
            User::factory()->processOwner()->create(),
        ];

        // DEC-009: Action-level capabilities denied by default for non-admin
        // DEC-004: Sensitive field access granted by default for non-viewer roles
        foreach ($nonViewers as $user) {
            expect(Gate::forUser($user)->allows('viewAny', Partner::class))->toBeFalse();
            expect(Gate::forUser($user)->allows('view', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('create', Partner::class))->toBeFalse();
            expect(Gate::forUser($user)->allows('update', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('delete', $partner))->toBeFalse();
            expect(Gate::forUser($user)->allows('export', Partner::class))->toBeFalse();

            // DEC-004: Non-viewer roles may see sensitive fields by default
            expect(Gate::forUser($user)->allows('revealNik', $partner))->toBeTrue();
            expect(Gate::forUser($user)->allows('revealPhone', $partner))->toBeTrue();
            expect(Gate::forUser($user)->allows('revealAddress', $partner))->toBeTrue();
        }

        // DEC-004: Viewer role (Auditor) is denied all sensitive field access
        $auditor = User::factory()->auditor()->create();
        expect(Gate::forUser($auditor)->allows('viewAny', Partner::class))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('view', $partner))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('create', Partner::class))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('update', $partner))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('delete', $partner))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('export', Partner::class))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('revealNik', $partner))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('revealPhone', $partner))->toBeFalse();
        expect(Gate::forUser($auditor)->allows('revealAddress', $partner))->toBeFalse();
    });

    it('allows system_admin to perform actions via Gate::before superadmin bypass', function () {
        $partner = Partner::factory()->create();
        $admin = User::factory()->systemAdmin()->create();

        expect(Gate::forUser($admin)->allows('viewAny', Partner::class))->toBeTrue();
        expect(Gate::forUser($admin)->allows('view', $partner))->toBeTrue();
        expect(Gate::forUser($admin)->allows('create', Partner::class))->toBeTrue();
        expect(Gate::forUser($admin)->allows('update', $partner))->toBeTrue();
        expect(Gate::forUser($admin)->allows('revealNik', $partner))->toBeTrue();
        expect(Gate::forUser($admin)->allows('revealPhone', $partner))->toBeTrue();
        expect(Gate::forUser($admin)->allows('revealAddress', $partner))->toBeTrue();
        expect(Gate::forUser($admin)->allows('export', Partner::class))->toBeTrue();
    });

    it('denies physical delete of records for all users including system_admin under Invariant 4', function () {
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);
        $user = User::factory()->operator()->create();
        $admin = User::factory()->systemAdmin()->create();

        // Even with grant, delete is strictly barred in policy and Gate::before
        $user->grantPermission(Permission::PartnerUpdate);

        expect(Gate::forUser($user)->allows('delete', $partner))->toBeFalse();
        expect(Gate::forUser($admin)->allows('delete', $partner))->toBeFalse();
        expect(Gate::forUser($user)->allows('delete', $agreement))->toBeFalse();
        expect(Gate::forUser($admin)->allows('delete', $agreement))->toBeFalse();
    });

    it('forbids system_admin from financial posting or ledger mutation without cashier permissions per DEC-009', function () {
        $admin = User::factory()->systemAdmin()->create();
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->create(['partner_id' => $partner->id]);
        $transaction = BankTransaction::factory()->create();
        $allocation = PaymentAllocation::factory()->create([
            'agreement_id' => $agreement->id,
            'bank_transaction_id' => $transaction->id,
        ]);
        $fundLot = FundLot::factory()->create(['partner_id' => $partner->id]);

        // System Admin cannot post payments without PaymentPost permission
        expect(Gate::forUser($admin)->allows('post', $allocation))->toBeFalse();

        // System Admin cannot reverse payments without PaymentPost permission
        expect(Gate::forUser($admin)->allows('reverse', $transaction))->toBeFalse();

        // System Admin cannot stage payments without PaymentStage permission
        expect(Gate::forUser($admin)->allows('create', BankTransaction::class))->toBeFalse();

        // System Admin cannot create or identify ABT fund lots without cashier role/permission
        expect(Gate::forUser($admin)->allows('createAbt', FundLot::class))->toBeFalse();
        expect(Gate::forUser($admin)->allows('identify', $fundLot))->toBeFalse();

        // When granted explicit cashier permissions, posting is authorized
        $adminWithPost = User::factory()->systemAdmin()->create();
        $adminWithPost->grantPermission(Permission::PaymentPost);
        expect(Gate::forUser($adminWithPost)->allows('post', $allocation))->toBeTrue();
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

    it('denies VirtualAccount actions by default while allowing non-viewer VA reveal per DEC-004', function () {
        $partner = Partner::factory()->create();
        $va = VirtualAccount::factory()->create(['partner_id' => $partner->id]);
        $operator = User::factory()->operator()->create();
        $auditor = User::factory()->auditor()->create();

        // DEC-009: Action capabilities denied by default
        expect(Gate::forUser($operator)->allows('view', $va))->toBeFalse();
        expect(Gate::forUser($operator)->allows('delete', $va))->toBeFalse();

        // DEC-004: Non-viewer allowed VA reveal by default
        expect(Gate::forUser($operator)->allows('revealVaNumber', $va))->toBeTrue();

        // DEC-004: Viewer (Auditor) denied VA reveal
        expect(Gate::forUser($auditor)->allows('revealVaNumber', $va))->toBeFalse();
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

    it('returns masked partner payload for viewer (Auditor) role per DEC-004', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'phone' => '08123456789',
            'address' => 'Jl. Pahlawan No. 45',
        ]);
        $auditor = User::factory()->auditor()->create();

        $payload = $masking->maskPartner($partner, $auditor);

        expect($payload['nik'])->toBe('35**********0001');
        expect($payload['phone'])->toBe('081******89');
        expect($payload['address'])->toStartWith('Jl. ');
        expect($payload['address'])->toContain('*');
        expect($payload['is_masked'])->toBeTrue();
    });

    it('returns masked partner payload for unauthenticated guest viewer', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'phone' => '08123456789',
            'address' => 'Jl. Pahlawan No. 45',
        ]);

        $payload = $masking->maskPartner($partner, null);

        expect($payload['nik'])->toBe('35**********0001');
        expect($payload['phone'])->toBe('081******89');
        expect($payload['address'])->toStartWith('Jl. ');
        expect($payload['address'])->toContain('*');
        expect($payload['is_masked'])->toBeTrue();
    });

    it('returns unmasked partner payload by default for non-viewer roles per DEC-004', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create([
            'nik' => '3512345678900001',
            'phone' => '08123456789',
            'address' => 'Jl. Pahlawan No. 45',
        ]);
        $nonViewers = [
            User::factory()->operator()->create(),
            User::factory()->reconciliationReviewer()->create(),
            User::factory()->processOwner()->create(),
            User::factory()->systemAdmin()->create(),
        ];

        foreach ($nonViewers as $user) {
            $payload = $masking->maskPartner($partner, $user);

            expect($payload['nik'])->toBe('3512345678900001');
            expect($payload['phone'])->toBe('08123456789');
            expect($payload['address'])->toBe('Jl. Pahlawan No. 45');
            expect($payload['is_masked'])->toBeFalse();
        }
    });

    it('masks VirtualAccount payload for Auditor and reveals for non-viewer per DEC-004', function () {
        $masking = new MaskingService;
        $partner = Partner::factory()->create();
        $va = VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => '988212345678',
        ]);
        $auditor = User::factory()->auditor()->create();
        $operator = User::factory()->operator()->create();

        $maskedPayload = $masking->maskVirtualAccount($va, $auditor);
        expect($maskedPayload['va_number'])->toBe('9882****5678');
        expect($maskedPayload['is_masked'])->toBeTrue();

        $unmaskedPayload = $masking->maskVirtualAccount($va, $operator);
        expect($unmaskedPayload['va_number'])->toBe('988212345678');
        expect($unmaskedPayload['is_masked'])->toBeFalse();
    });
});

describe('Sensitive Field Reveal and Audit Logging (DEC-004 & Gate Test)', function () {
    it('denies unmasking for viewer (Auditor) and logs audit attempt without raw PII', function () {
        Log::spy();
        $masking = new MaskingService;
        $partner = Partner::factory()->create(['nik' => '3512345678900001']);
        $auditor = User::factory()->auditor()->create();

        try {
            $masking->reveal($auditor, $partner, 'nik', 'Identity verification for audit');
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

    it('allows unmasking by default for non-viewer roles and logs success without raw PII', function () {
        Log::spy();
        $masking = new MaskingService;
        $partner = Partner::factory()->create(['nik' => '3512345678900001']);
        $operator = User::factory()->operator()->create();

        $revealed = $masking->reveal($operator, $partner, 'nik', 'Authorized case review');

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
