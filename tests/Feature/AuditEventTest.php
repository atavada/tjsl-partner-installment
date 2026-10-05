<?php

declare(strict_types=1);

use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AuditEvent;
use App\Models\BankTransaction;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\AgreementDocumentService;
use App\Services\AuditService;
use App\Services\MaskingService;
use App\Services\PaymentReversalService;
use App\Services\PaymentStagingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->operator = User::factory()->operator()->create([
        'email' => 'operator@example.test',
    ]);
    $this->operator->grantPermission(Permission::PartnerView);
    $this->operator->grantPermission(Permission::PaymentStage);

    $this->admin = User::factory()->systemAdmin()->create([
        'email' => 'admin@example.test',
    ]);
});

describe('AuditEvent Immutability Guards', function () {
    it('creates audit event with UUID and append-only timestamp', function () {
        $event = AuditEvent::create([
            'correlation_id' => (string) Str::uuid(),
            'actor_id' => $this->operator->id,
            'actor_type' => 'user',
            'actor_identifier' => $this->operator->email,
            'action' => 'create',
            'delta' => ['name' => 'Synthetic Test Partner'],
            'reason' => 'Initial test creation',
        ]);

        expect($event->id)->toBeString()
            ->and(Str::isUuid($event->id))->toBeTrue()
            ->and($event->created_at)->not->toBeNull()
            ->and($event->action)->toBe('create')
            ->and(AuditEvent::UPDATED_AT)->toBeNull();
    });

    it('rejects updates on AuditEvent model throwing LogicException', function () {
        $event = AuditEvent::create([
            'correlation_id' => (string) Str::uuid(),
            'action' => 'create',
            'delta' => ['key' => 'initial'],
        ]);

        expect(fn () => $event->update(['action' => 'tampered']))
            ->toThrow(LogicException::class, 'AuditEvent is immutable and cannot be updated.');

        $fresh = AuditEvent::find($event->id);
        expect($fresh->action)->toBe('create');
    });

    it('rejects deletes on AuditEvent model throwing LogicException', function () {
        $event = AuditEvent::create([
            'correlation_id' => (string) Str::uuid(),
            'action' => 'create',
            'delta' => ['key' => 'initial'],
        ]);

        expect(fn () => $event->delete())
            ->toThrow(LogicException::class, 'AuditEvent is immutable and cannot be deleted.');

        expect(AuditEvent::where('id', $event->id)->exists())->toBeTrue();
    });
});

describe('Auditable Model Lifecycle Hooks', function () {
    it('emits create audit event with actor, target, action, and masked delta when model is created', function () {
        $this->actingAs($this->operator);

        $partner = Partner::factory()->create([
            'name' => 'Mitra Sintetis A',
            'nik' => '3271012345678901',
            'phone' => '08123456789',
            'address' => 'Jl. Sintetis No. 123, Malang',
        ]);

        $event = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->where('action', 'create')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($this->operator->id)
            ->and($event->actor_type)->toBe('user')
            ->and($event->actor_identifier)->toBe($this->operator->email)
            ->and($event->delta)->toBeArray()
            ->and($event->delta['name'])->toBe('Mitra Sintetis A')
            // Verify masking applied in delta
            ->and($event->delta['nik'])->not->toBe('3271012345678901')
            ->and($event->delta['nik'])->toContain('*')
            ->and($event->delta['phone'])->not->toBe('08123456789')
            ->and($event->delta['phone'])->toContain('*');
    });

    it('emits update audit event with diff of changed fields', function () {
        $this->actingAs($this->operator);

        $partner = Partner::factory()->create([
            'name' => 'Mitra Nama Awal',
            'business_type' => 'Retail',
        ]);

        $partner->setAuditReason('Perubahan nama usaha');
        $partner->update([
            'name' => 'Mitra Nama Baru',
            'business_type' => 'Grosir',
        ]);

        $event = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->where('action', 'update')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->reason)->toBe('Perubahan nama usaha')
            ->and($event->delta)->toHaveKeys(['name', 'business_type'])
            ->and($event->delta['name'])->toEqual([
                'old' => 'Mitra Nama Awal',
                'new' => 'Mitra Nama Baru',
            ])
            ->and($event->delta['business_type'])->toEqual([
                'old' => 'Retail',
                'new' => 'Grosir',
            ]);
    });

    it('does not emit audit event on updated_at touch without content changes', function () {
        $partner = Partner::factory()->create();
        $initialCount = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->count();

        // Touch without dirty content attributes
        $partner->touch();

        $afterCount = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->count();

        expect($afterCount)->toBe($initialCount);
    });

    it('records actor as system when model created outside HTTP auth context', function () {
        $partner = Partner::factory()->create(['name' => 'System Mitra']);

        $event = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->where('action', 'create')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_type)->toBe('system')
            ->and($event->actor_id)->toBeNull();
    });
});

describe('Sensitive Field Masking in Audit Deltas', function () {
    it('masks NIK, phone, address, and virtual account numbers in audit deltas', function () {
        $rawNik = '3271012345678901';
        $rawPhone = '081234567890';
        $rawAddress = 'Jl. Bendungan Wonorejo No. 45';
        $rawVa = '8800123456789012';

        $partner = Partner::factory()->create([
            'nik' => $rawNik,
            'phone' => $rawPhone,
            'address' => $rawAddress,
        ]);

        $va = VirtualAccount::factory()->create([
            'partner_id' => $partner->id,
            'va_number' => $rawVa,
        ]);

        $partnerEvent = AuditEvent::where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->where('action', 'create')
            ->first();

        $vaEvent = AuditEvent::where('target_type', VirtualAccount::class)
            ->where('target_id', $va->id)
            ->where('action', 'create')
            ->first();

        expect($partnerEvent->delta['nik'])->not->toBe($rawNik)
            ->and($partnerEvent->delta['nik'])->toBe('32**********8901')
            ->and($partnerEvent->delta['phone'])->not->toBe($rawPhone)
            ->and($partnerEvent->delta['phone'])->toBe('081*******90')
            ->and($partnerEvent->delta['address'])->not->toBe($rawAddress)
            ->and($partnerEvent->delta['address'])->toContain('*')
            ->and($vaEvent->delta['va_number'])->not->toBe($rawVa)
            ->and($vaEvent->delta['va_number'])->toBe('8800********9012');

        // Confirm zero unmasked PII strings in entire audit_events table
        $allDeltasJson = AuditEvent::pluck('delta')->toJson();
        expect($allDeltasJson)->not->toContain($rawNik)
            ->and($allDeltasJson)->not->toContain($rawPhone)
            ->and($allDeltasJson)->not->toContain($rawAddress)
            ->and($allDeltasJson)->not->toContain($rawVa);
    });

    it('masks payer_va on BankTransaction in audit delta', function () {
        $rawPayerVa = '9900123456781234';

        $txn = BankTransaction::factory()->create([
            'payer_va' => $rawPayerVa,
        ]);

        $event = AuditEvent::where('target_type', BankTransaction::class)
            ->where('target_id', $txn->id)
            ->where('action', 'create')
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->delta['payer_va'])->not->toBe($rawPayerVa)
            ->and($event->delta['payer_va'])->toContain('*');
    });
});

describe('Correlation ID Request Grouping', function () {
    it('assigns correlation ID header and groups request audit events under same ID', function () {
        Route::middleware(['web'])->post('/test-audit-correlation', function () {
            $p = Partner::factory()->create(['name' => 'Correlation Partner']);
            VirtualAccount::factory()->create(['partner_id' => $p->id]);

            return response()->json(['ok' => true]);
        });

        $customCorrelationId = (string) Str::uuid();

        $response = $this->withHeader('X-Correlation-ID', $customCorrelationId)
            ->postJson('/test-audit-correlation');

        $response->assertOk()
            ->assertHeader('X-Correlation-ID', $customCorrelationId);

        $events = AuditEvent::where('correlation_id', $customCorrelationId)->get();
        expect($events->count())->toBeGreaterThanOrEqual(2);

        foreach ($events as $event) {
            expect($event->correlation_id)->toBe($customCorrelationId);
        }
    });

    it('replaces invalid client correlation ID with fresh UUID', function () {
        Route::middleware(['web'])->get('/test-invalid-correlation', fn () => response()->json(['ok' => true]));

        $response = $this->withHeader('X-Correlation-ID', 'not-a-valid-uuid')
            ->getJson('/test-invalid-correlation');

        $response->assertOk();
        $returnedId = $response->headers->get('X-Correlation-ID');

        expect($returnedId)->not->toBe('not-a-valid-uuid')
            ->and(Str::isUuid($returnedId))->toBeTrue();
    });
});

describe('Reversal Preserves Original and Emits Audit Trail', function () {
    it('emits reversal audit event linking compensating allocation to original allocation', function () {
        $service = app(PaymentReversalService::class);

        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);
        $txn = BankTransaction::factory()->create(['amount' => 2_000_000]);

        $originalAllocation = PaymentAllocation::create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 100_000,
            'admin_charge_amount' => 50_000,
            'other_charge_amount' => 0,
            'total_amount' => 1_150_000,
            'effective_date' => '2026-03-15',
            'period' => '2026-03',
            'state' => PaymentState::Draft,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        $reason = 'Salah alokasi komponen angsuran mitra';
        $compensating = $service->reverse($originalAllocation, $reason, $this->operator);

        // Original preserved and marked reversed
        expect($originalAllocation->refresh()->state)->toBe(PaymentState::Reversed)
            ->and($compensating->reversal_of_id)->toBe($originalAllocation->id)
            ->and($compensating->state)->toBe(PaymentState::Reversed);

        // Audit event emitted
        $reversalEvent = AuditEvent::where('action', 'reversal')
            ->where('target_type', PaymentAllocation::class)
            ->where('target_id', $compensating->id)
            ->first();

        expect($reversalEvent)->not->toBeNull()
            ->and($reversalEvent->reason)->toBe($reason)
            ->and($reversalEvent->actor_id)->toBe($this->operator->id)
            ->and($reversalEvent->delta['original_allocation_id'])->toBe($originalAllocation->id)
            ->and($reversalEvent->delta['reversal_allocation_id'])->toBe($compensating->id)
            ->and($reversalEvent->delta['total_amount'])->toBe(1_150_000);
    });
});

describe('Authorization Failures and Unauthorized Access Audit', function () {
    it('emits unauthorized_field_access_denied audit event when unauthorized reveal attempted', function () {
        $masking = app(MaskingService::class);
        $partner = Partner::factory()->create(['nik' => '3271012345678901']);

        // DEC-004: Auditor (viewer) is denied sensitive field reveal
        $unauthorizedUser = User::factory()->auditor()->create();

        try {
            $masking->reveal($unauthorizedUser, $partner, 'nik', 'Audit test reveal');
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            // Expected
        }

        $event = AuditEvent::where('action', 'unauthorized_field_access_denied')
            ->where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($unauthorizedUser->id)
            ->and($event->delta['field'])->toBe('nik')
            ->and($event->delta['purpose'])->toBe('Audit test reveal')
            ->and($event->reason)->toContain('Unauthorized attempt');
    });

    it('emits sensitive_field_revealed audit event when authorized reveal succeeds without raw PII in delta', function () {
        $masking = app(MaskingService::class);
        $partner = Partner::factory()->create(['nik' => '3271012345678901']);

        // Admin has reveal permission
        $result = $masking->reveal($this->admin, $partner, 'nik', 'Verifikasi dokumen mitra');

        expect($result)->toBe('3271012345678901');

        $event = AuditEvent::where('action', 'sensitive_field_revealed')
            ->where('target_type', Partner::class)
            ->where('target_id', $partner->id)
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($this->admin->id)
            ->and($event->delta['field'])->toBe('nik')
            // Verify raw NIK NOT logged in delta
            ->and(json_encode($event->delta))->not->toContain('3271012345678901');
    });

    it('emits unauthorized_posting_attempt audit event when posting attempted', function () {
        $staging = app(PaymentStagingService::class);
        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);
        $txn = BankTransaction::factory()->create(['amount' => 2_000_000]);

        $allocation = PaymentAllocation::create([
            'bank_transaction_id' => $txn->id,
            'agreement_id' => $agreement->id,
            'principal_amount' => 1_000_000,
            'interest_amount' => 0,
            'admin_charge_amount' => 0,
            'other_charge_amount' => 0,
            'total_amount' => 1_000_000,
            'effective_date' => '2026-03-15',
            'period' => '2026-03',
            'state' => PaymentState::Draft,
            'idempotency_key' => (string) Str::uuid(),
            'version' => 1,
        ]);

        try {
            $staging->post($allocation, $this->operator);
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            // Expected
        }

        $event = AuditEvent::where('action', 'unauthorized_posting_attempt')
            ->where('target_type', PaymentAllocation::class)
            ->where('target_id', $allocation->id)
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($this->operator->id)
            ->and($event->delta['total_amount'])->toBe(1_000_000);
    });

    it('emits authorization_failure audit event when EnsureRoleAuthorized rejects forbidden role', function () {
        Route::middleware(['web', 'role:financial_reviewer'])->get('/test-role-gate', fn () => response()->json(['ok' => true]));

        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->getJson('/test-role-gate');
        $response->assertStatus(403);

        $event = AuditEvent::where('action', 'authorization_failure')->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($operator->id)
            ->and($event->delta['user_role'])->toBe(Role::Operator->value)
            ->and($event->delta['required_roles'])->toContain('financial_reviewer');
    });
});

describe('Document Access & Export Audit', function () {
    it('emits document_access audit event on document download', function () {
        Storage::fake('local');
        $docService = app(AgreementDocumentService::class);

        $partner = Partner::factory()->create();
        $agreement = Agreement::factory()->active()->create(['partner_id' => $partner->id]);

        $document = $docService->store(
            agreement: $agreement,
            file: 'Synthetic PDF content',
            originalFileName: 'surat_perjanjian.pdf',
            mimeType: 'application/pdf',
            documentType: 'contract',
            notes: 'Test document',
            uploader: $this->operator,
        );

        $content = $docService->download($document, $this->operator);
        expect($content)->toBe('Synthetic PDF content');

        $event = AuditEvent::where('action', 'download')
            ->where('target_type', AgreementDocument::class)
            ->where('target_id', $document->id)
            ->first();

        expect($event)->not->toBeNull()
            ->and($event->actor_id)->toBe($this->operator->id)
            ->and($event->delta['file_name'])->toBe('surat_perjanjian.pdf');
    });

    it('emits export audit event with explicit scope and count', function () {
        $auditService = app(AuditService::class);

        $event = $auditService->logExport(
            scope: 'partners_reconciliation_2026',
            count: 42,
            actor: $this->operator,
            reason: 'Laporan rekonsiliasi triwulanan',
        );

        expect($event)->not->toBeNull()
            ->and($event->action)->toBe('export')
            ->and($event->actor_id)->toBe($this->operator->id)
            ->and($event->delta['scope'])->toBe('partners_reconciliation_2026')
            ->and($event->delta['record_count'])->toBe(42)
            ->and($event->reason)->toBe('Laporan rekonsiliasi triwulanan');
    });
});
