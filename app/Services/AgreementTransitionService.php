<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\AgreementTransitionType;
use App\Exceptions\CyclicTransitionException;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\AgreementTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AgreementTransitionService
{
    /**
     * Check if proposed transition creates a cycle in transition graph.
     * Uses BFS traversal starting from successor searching for predecessor.
     *
     * @throws CyclicTransitionException
     */
    public function validateNoCycle(string $predecessorId, string $successorId): void
    {
        if ($predecessorId === $successorId) {
            throw CyclicTransitionException::between($predecessorId, $successorId);
        }

        $visited = [];
        $queue = [$successorId];

        while (! empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $predecessorId) {
                throw CyclicTransitionException::between($predecessorId, $successorId);
            }

            if (isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $nextSuccessorIds = AgreementTransition::query()
                ->where('predecessor_id', $currentId)
                ->whereNotNull('successor_id')
                ->pluck('successor_id')
                ->all();

            foreach ($nextSuccessorIds as $nextId) {
                if (! isset($visited[$nextId])) {
                    $queue[] = $nextId;
                }
            }
        }
    }

    /**
     * Create transition between predecessor and successor agreements.
     */
    public function createTransition(
        Agreement $predecessor,
        ?Agreement $successor,
        AgreementTransitionType $type,
        array $attributes = [],
        ?User $authorizer = null
    ): AgreementTransition {
        if ($successor !== null) {
            $this->validateNoCycle($predecessor->id, $successor->id);
        }

        return DB::transaction(function () use ($predecessor, $successor, $type, $attributes, $authorizer) {
            $transition = AgreementTransition::create([
                'predecessor_id' => $predecessor->id,
                'successor_id' => $successor?->id,
                'transition_type' => $type,
                'effective_date' => $attributes['effective_date'] ?? now()->toDateString(),
                'reason' => $attributes['reason'] ?? null,
                'approved_principal_amount' => $attributes['approved_principal_amount'] ?? null,
                'approved_interest_amount' => $attributes['approved_interest_amount'] ?? null,
                'approved_admin_charge_amount' => $attributes['approved_admin_charge_amount'] ?? null,
                'approved_by_id' => $authorizer?->id,
                'approved_at' => $authorizer ? now() : null,
                'version' => 1,
            ]);

            // Rescheduling: predecessor closed as closed_by_rescheduling, NEVER paid_off (PRD §1, §4)
            if ($type === AgreementTransitionType::Rescheduling) {
                $predecessor->update([
                    'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
                ]);
            }

            return $transition;
        });
    }

    /**
     * DEC-002: Operational lifecycle transitions throw NotApprovedException.
     */
    public function transitionLifecycle(Agreement $agreement, AgreementLifecycleStatus $targetStatus): never
    {
        throw NotApprovedException::forLifecycleTransition($targetStatus->value);
    }

    /**
     * DEC-003: Operational signing transitions throw NotApprovedException.
     */
    public function transitionSigning(Agreement $agreement, AgreementSigningStatus $targetStatus): never
    {
        throw NotApprovedException::forSigningTransition($targetStatus->value);
    }
}
