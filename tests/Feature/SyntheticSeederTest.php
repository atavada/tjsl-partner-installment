<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementTransitionType;
use App\Enums\PaymentState;
use App\Enums\Role;
use App\Exceptions\CyclicTransitionException;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AgreementTransition;
use App\Models\AuditEvent;
use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Overpayment;
use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\AgreementTransitionService;
use App\Services\BalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

describe('Synthetic Database Seeder (TASK-009 / PRD §9 Gate)', function () {
    it('seeds clean database without error and produces valid Phase A entity hierarchy', function () {
        // Users for all 5 PRD §3 roles
        expect(User::count())->toBeGreaterThanOrEqual(5);

        $admin = User::where('email', 'test@example.com')->first();
        expect($admin)->not->toBeNull()
            ->and($admin->role)->toBe(Role::SystemAdmin)
            ->and($admin->isSystemAdmin())->toBeTrue();

        $operator = User::where('email', 'operator@example.test')->first();
        expect($operator)->not->toBeNull()
            ->and($operator->role)->toBe(Role::Operator)
            ->and($operator->hasPermission('partner.view'))->toBeTrue()
            ->and($operator->hasPermission('payment.stage'))->toBeTrue()
            ->and($operator->hasPermission('payment.post'))->toBeTrue();

        $reviewer = User::where('email', 'reviewer@example.test')->first();
        expect($reviewer)->not->toBeNull()
            ->and($reviewer->role)->toBe(Role::ReconciliationReviewer);

        $auditor = User::where('email', 'auditor@example.test')->first();
        expect($auditor)->not->toBeNull()
            ->and($auditor->role)->toBe(Role::Auditor);

        $processOwner = User::where('email', 'process_owner@example.test')->first();
        expect($processOwner)->not->toBeNull()
            ->and($processOwner->role)->toBe(Role::ProcessOwner);

        // Partners, aliases, and virtual accounts exist
        expect(Partner::count())->toBeGreaterThanOrEqual(15);
        expect(PartnerAlias::count())->toBeGreaterThanOrEqual(4);
        expect(VirtualAccount::count())->toBeGreaterThanOrEqual(4);

        // Agreements, transitions, documents, schedules exist
        expect(Agreement::count())->toBeGreaterThanOrEqual(6);
        expect(AgreementTransition::count())->toBeGreaterThanOrEqual(3);
        expect(AgreementDocument::count())->toBeGreaterThanOrEqual(2);
        expect(InstallmentSchedule::count())->toBeGreaterThanOrEqual(12);

        // Bank transactions, allocations, overpayments (ABT) exist
        expect(BankTransaction::count())->toBeGreaterThanOrEqual(6);
        expect(PaymentAllocation::count())->toBeGreaterThanOrEqual(6);
        expect(Overpayment::count())->toBeGreaterThanOrEqual(2);

        // Audit events exist with append-only immutability
        expect(AuditEvent::count())->toBeGreaterThanOrEqual(5);
        $event = AuditEvent::where('action', 'partner.verify')->first();
        expect($event)->not->toBeNull()
            ->and(Str::isUuid($event->correlation_id))->toBeTrue()
            ->and($event->actor_identifier)->not->toBeNull()
            ->and($event->ip_address)->toBe('127.0.0.1')
            ->and($event->delta)->toBeArray()
            ->and($event->reason)->not->toBeNull();
    });

    it('exercises all Phase A gate test edge cases on seeded data', function () {
        // 1. Leading-zero NO IDs preserved
        $p1 = Partner::where('partner_no_id', '00010001')->first();
        expect($p1)->not->toBeNull()
            ->and($p1->partner_no_id)->toBe('00010001')
            ->and($p1->partner_no_id_normalized)->toBe('00010001')
            ->and(str_starts_with($p1->partner_no_id, '000'))->toBeTrue();

        $pShort = Partner::where('partner_no_id', '007')->first();
        expect($pShort)->not->toBeNull()
            ->and($pShort->partner_no_id)->toBe('007')
            ->and($pShort->partner_no_id)->not->toBe('7');

        $pLong = Partner::where('partner_no_id', '000000000012345')->first();
        expect($pLong)->not->toBeNull()
            ->and($pLong->partner_no_id)->toBe('000000000012345')
            ->and(strlen($pLong->partner_no_id))->toBe(15);

        // 2. Same-name different-person partners
        $budiPartners = Partner::where('name', 'Budi Santoso')->get();
        expect($budiPartners->count())->toBeGreaterThanOrEqual(2);
        $pA = $budiPartners->firstWhere('partner_no_id', '00010002');
        $pB = $budiPartners->firstWhere('partner_no_id', '00010003');
        expect($pA)->not->toBeNull()
            ->and($pB)->not->toBeNull()
            ->and($pA->id)->not->toBe($pB->id)
            ->and($pA->nik)->not->toBe($pB->nik)
            ->and($pA->region)->not->toBe($pB->region);

        // Identical alias names on different partners
        $aliases = PartnerAlias::where('name_normalized', 'koptan makmur')->get();
        expect($aliases->count())->toBeGreaterThanOrEqual(2);
        $distinctPartnersWithSameAlias = $aliases->pluck('partner_id')->unique();
        expect($distinctPartnersWithSameAlias->count())->toBeGreaterThanOrEqual(2);

        // Partners without NO ID (staging state)
        $stagingPartners = Partner::whereNull('partner_no_id')->get();
        expect($stagingPartners->count())->toBeGreaterThanOrEqual(2);
        $sitiStaging = $stagingPartners->firstWhere('name', 'Siti Aminah');
        expect($sitiStaging)->not->toBeNull()
            ->and($sitiStaging->partner_no_id)->toBeNull()
            ->and($sitiStaging->verification_state)->toBe('unverified');

        // Virtual accounts with leading zeros preserved
        $vaBNI = VirtualAccount::where('va_number', '0000888812345678')->first();
        expect($vaBNI)->not->toBeNull()
            ->and($vaBNI->va_number)->toBe('0000888812345678')
            ->and(str_starts_with($vaBNI->va_number, '0000'))->toBeTrue();

        // 3. Draft agreements create NO DEBT per PRD FR-02
        $draft = Agreement::where('lifecycle_status', AgreementLifecycleStatus::Draft)->first();
        expect($draft)->not->toBeNull()
            ->and($draft->agreement_number)->toBe('0003/SP-TJSL/2026');
        $balance = app(BalanceService::class)->getBalance($draft);
        expect($balance['is_draft'])->toBeTrue()
            ->and($balance['creates_debt'])->toBeFalse()
            ->and($balance['status'])->toBe('draft');

        // Active agreement with schedules and documents
        $active = Agreement::where('agreement_number', '0001/SP-TJSL/2026')->first();
        expect($active)->not->toBeNull()
            ->and($active->lifecycle_status)->toBe(AgreementLifecycleStatus::Active)
            ->and($active->collectibility_status->value)->toBe('current');
        expect(InstallmentSchedule::where('agreement_id', $active->id)->count())->toBe(12);
        expect(AgreementDocument::where('agreement_id', $active->id)->count())->toBeGreaterThanOrEqual(1);

        // Closed by rescheduling != paid off
        $predecessor = Agreement::where('agreement_number', '0001/PUMK/2024')->first();
        $successor = Agreement::where('agreement_number', '0002/PUMK/2026')->first();
        expect($predecessor)->not->toBeNull()
            ->and($successor)->not->toBeNull()
            ->and($predecessor->lifecycle_status)->toBe(AgreementLifecycleStatus::ClosedByRescheduling)
            ->and($predecessor->isClosedByRescheduling())->toBeTrue()
            ->and($predecessor->isPaidOff())->toBeFalse()
            ->and($successor->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);

        $rescheduleTrans = AgreementTransition::where('predecessor_id', $predecessor->id)
            ->where('successor_id', $successor->id)
            ->first();
        expect($rescheduleTrans)->not->toBeNull()
            ->and($rescheduleTrans->transition_type)->toBe(AgreementTransitionType::Rescheduling);

        $paidOff = Agreement::where('agreement_number', '0006/SP-TJSL/2023')->first();
        expect($paidOff)->not->toBeNull()
            ->and($paidOff->lifecycle_status)->toBe(AgreementLifecycleStatus::PaidOff)
            ->and($paidOff->isPaidOff())->toBeTrue()
            ->and($paidOff->isClosedByRescheduling())->toBeFalse();

        // Multi-hop transition chain and cyclic transition rejection
        $a1 = Agreement::where('agreement_number', '0010/SP-TJSL/2024')->first();
        $a3 = Agreement::where('agreement_number', '0030/SP-TJSL/2026')->first();
        expect($a1)->not->toBeNull()->and($a3)->not->toBeNull();
        expect(fn () => app(AgreementTransitionService::class)->validateNoCycle($a3->id, $a1->id))
            ->toThrow(CyclicTransitionException::class);

        // 4. Payment in each state (draft, submitted, posted, reversed)
        expect(BankTransaction::where('state', PaymentState::Draft)->exists())->toBeTrue();
        expect(PaymentAllocation::where('state', PaymentState::Draft)->exists())->toBeTrue();

        expect(BankTransaction::where('state', PaymentState::Submitted)->exists())->toBeTrue();
        expect(PaymentAllocation::where('state', PaymentState::Submitted)->exists())->toBeTrue();

        expect(BankTransaction::where('state', PaymentState::Posted)->exists())->toBeTrue();
        expect(PaymentAllocation::where('state', PaymentState::Posted)->exists())->toBeTrue();

        expect(BankTransaction::where('state', PaymentState::Reversed)->exists())->toBeTrue();
        $reversedAllocs = PaymentAllocation::where('state', PaymentState::Reversed)->get();
        expect($reversedAllocs->count())->toBeGreaterThanOrEqual(2);
        $compensating = $reversedAllocs->firstWhere('reversal_of_id', '!=', null);
        expect($compensating)->not->toBeNull()
            ->and($compensating->total_amount)->toBe(900_000)
            ->and($compensating->reason)->toBe('Entri kompensasi pembalikan alokasi salah');
        $original = PaymentAllocation::find($compensating->reversal_of_id);
        expect($original)->not->toBeNull()
            ->and($original->state)->toBe(PaymentState::Reversed);

        // 5. Overpayments (ABT): partner-linked and non-partner unapplied deposit
        $partnerOverpayment = Overpayment::whereNotNull('partner_id')->first();
        expect($partnerOverpayment)->not->toBeNull()
            ->and($partnerOverpayment->unapplied_amount)->toBe(200_000)
            ->and($partnerOverpayment->proposed_disposition)->toBe('offset');

        $nonPartnerOverpayment = Overpayment::whereNull('partner_id')->first();
        expect($nonPartnerOverpayment)->not->toBeNull()
            ->and($nonPartnerOverpayment->unapplied_amount)->toBe(500_000)
            ->and($nonPartnerOverpayment->proposed_disposition)->toBe('refund');

        // 6. No real PII
        foreach (User::all() as $user) {
            expect($user->email)->toMatch('/@(example\.test|example\.com)$/');
        }
        foreach (BankTransaction::all() as $txn) {
            expect($txn->reference)->toMatch('/^TXN-SYN-/');
        }
    });
});
