<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\AgreementTransitionType;
use App\Exceptions\CyclicTransitionException;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Services\AgreementTransitionService;

describe('AgreementTransitionService cycle detection (gate test: cyclic addendum rejected)', function () {
    it('rejects self-loop transition where predecessor equals successor', function () {
        $service = new AgreementTransitionService;
        $agreement = Agreement::factory()->create();

        expect(fn () => $service->validateNoCycle($agreement->id, $agreement->id))
            ->toThrow(CyclicTransitionException::class);
    });

    it('rejects direct 2-node cycle (A -> B, B -> A)', function () {
        $service = new AgreementTransitionService;

        $agreementA = Agreement::factory()->create();
        $agreementB = Agreement::factory()->create();

        // Create transition A -> B
        $service->createTransition($agreementA, $agreementB, AgreementTransitionType::Amendment);

        // Attempt B -> A: must throw CyclicTransitionException
        expect(fn () => $service->createTransition($agreementB, $agreementA, AgreementTransitionType::Amendment))
            ->toThrow(CyclicTransitionException::class);
    });

    it('rejects multi-hop cycle (A -> B -> C -> D, attempting D -> A or D -> B)', function () {
        $service = new AgreementTransitionService;

        $agreementA = Agreement::factory()->create();
        $agreementB = Agreement::factory()->create();
        $agreementC = Agreement::factory()->create();
        $agreementD = Agreement::factory()->create();

        // Build chain: A -> B -> C -> D
        $service->createTransition($agreementA, $agreementB, AgreementTransitionType::Amendment);
        $service->createTransition($agreementB, $agreementC, AgreementTransitionType::Amendment);
        $service->createTransition($agreementC, $agreementD, AgreementTransitionType::Amendment);

        // Attempt D -> A (full loop)
        expect(fn () => $service->createTransition($agreementD, $agreementA, AgreementTransitionType::Amendment))
            ->toThrow(CyclicTransitionException::class);

        // Attempt D -> B (sub-loop)
        expect(fn () => $service->createTransition($agreementD, $agreementB, AgreementTransitionType::Amendment))
            ->toThrow(CyclicTransitionException::class);

        // Attempt C -> A (sub-loop)
        expect(fn () => $service->createTransition($agreementC, $agreementA, AgreementTransitionType::Amendment))
            ->toThrow(CyclicTransitionException::class);
    });

    it('allows valid acyclic DAG transitions (branching / diamond structures)', function () {
        $service = new AgreementTransitionService;

        $agreementA = Agreement::factory()->create();
        $agreementB = Agreement::factory()->create();
        $agreementC = Agreement::factory()->create();
        $agreementD = Agreement::factory()->create();

        // DAG: A -> B, A -> C, B -> D, C -> D (diamond)
        $t1 = $service->createTransition($agreementA, $agreementB, AgreementTransitionType::Amendment);
        $t2 = $service->createTransition($agreementA, $agreementC, AgreementTransitionType::Amendment);
        $t3 = $service->createTransition($agreementB, $agreementD, AgreementTransitionType::Amendment);
        $t4 = $service->createTransition($agreementC, $agreementD, AgreementTransitionType::Amendment);

        expect($t1)->not->toBeNull();
        expect($t2)->not->toBeNull();
        expect($t3)->not->toBeNull();
        expect($t4)->not->toBeNull();
    });
});

describe('Agreement restructuring closure', function () {
    it('sets predecessor status to closed_by_rescheduling, never paid_off', function () {
        $service = new AgreementTransitionService;

        $predecessor = Agreement::factory()->active()->create();
        $successor = Agreement::factory()->create();

        $service->createTransition(
            $predecessor,
            $successor,
            AgreementTransitionType::Rescheduling,
            [
                'effective_date' => '2026-03-01',
                'reason' => 'Restrukturisasi kredit macet',
                'approved_principal_amount' => 10_000_000,
            ]
        );

        $predecessor->refresh();

        expect($predecessor->lifecycle_status)->toBe(AgreementLifecycleStatus::ClosedByRescheduling);
        expect($predecessor->isClosedByRescheduling())->toBeTrue();
        expect($predecessor->isPaidOff())->toBeFalse();
    });
});

describe('Operational transition guards (DEC-002 and DEC-003)', function () {
    it('throws NotApprovedException when attempting operational lifecycle transition', function () {
        $service = new AgreementTransitionService;
        $agreement = Agreement::factory()->create(['lifecycle_status' => AgreementLifecycleStatus::Draft]);

        expect(fn () => $service->transitionLifecycle($agreement, AgreementLifecycleStatus::Active))
            ->toThrow(NotApprovedException::class, "Operational lifecycle transition to 'active' is not approved per DEC-002.");
    });

    it('throws NotApprovedException when attempting operational signing transition', function () {
        $service = new AgreementTransitionService;
        $agreement = Agreement::factory()->create(['signing_status' => AgreementSigningStatus::Draft]);

        expect(fn () => $service->transitionSigning($agreement, AgreementSigningStatus::Signed))
            ->toThrow(NotApprovedException::class, "Operational signing transition to 'signed' is not approved per DEC-003.");
    });
});
