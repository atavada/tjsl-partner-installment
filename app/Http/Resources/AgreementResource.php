<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AgreementLifecycleStatus;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agreement
 */
class AgreementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BalanceService $balanceService */
        $balanceService = app(BalanceService::class);
        $balance = $balanceService->getBalance($this->resource);

        $isDraft = $this->lifecycle_status === AgreementLifecycleStatus::Draft;

        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'agreement_number' => $this->agreement_number,
            'agreement_number_normalized' => $this->agreement_number_normalized,
            'batch_year' => $this->batch_year,
            'business_group' => $this->business_group,
            'source_row_number' => $this->source_row_number,
            'application_date' => $this->application_date?->toDateString(),
            'contract_date' => $this->contract_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'maturity_date' => $this->maturity_date?->toDateString(),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'admin_charge_amount' => $this->admin_charge_amount,
            'other_charge_amount' => $this->other_charge_amount,
            'total_amount' => $this->total_amount,
            // Three independent status dimensions per PRD §4 invariant 8
            'status_dimensions' => [
                'lifecycle' => [
                    'status' => $this->lifecycle_status?->value,
                    'label' => $this->lifecycle_status?->label(),
                    'legacy' => $this->legacy_lifecycle_status,
                ],
                'collectibility' => [
                    'status' => $this->collectibility_status?->value,
                    'label' => $this->collectibility_status?->label(),
                    'legacy' => $this->legacy_collectibility_status,
                ],
                'signing' => [
                    'status' => $this->signing_status?->value,
                    'label' => $this->signing_status?->label(),
                    'legacy' => $this->legacy_signing_status,
                    'summary' => $this->signature_summary?->value,
                    'summary_label' => $this->signature_summary?->label(),
                ],
            ],
            'lifecycle_status' => $this->lifecycle_status?->value,
            'lifecycle_status_label' => $this->lifecycle_status?->label(),
            'collectibility_status' => $this->collectibility_status?->value,
            'collectibility_status_label' => $this->collectibility_status?->label(),
            'signing_status' => $this->signing_status?->value,
            'signing_status_label' => $this->signing_status?->label(),
            'signature_summary' => $this->signature_summary?->value,
            'signature_summary_label' => $this->signature_summary?->label(),
            'is_draft' => $isDraft,
            'is_paid_off' => $this->isPaidOff(),
            'is_completed' => $this->isCompleted(),
            'is_closed_by_rescheduling' => $this->isClosedByRescheduling(),
            'provenance' => $this->provenance,
            'approved_source' => $this->approved_source,
            'approved_by' => $this->relationLoaded('approvedBy') && $this->approvedBy
                ? [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ]
                : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'version' => $this->version,
            // Balance stub per DEC-008
            'balance' => $balance,
            'partner' => $this->relationLoaded('partner') && $this->partner
                ? [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'partner_no_id' => $this->partner->partner_no_id,
                ]
                : null,
            'documents' => $this->relationLoaded('documents')
                ? $this->documents->map(fn (AgreementDocument $doc) => [
                    'id' => $doc->id,
                    'agreement_id' => $doc->agreement_id,
                    'transition_id' => $doc->transition_id,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                    'file_size_bytes' => $doc->file_size_bytes,
                    'document_type' => $doc->document_type,
                    'document_version' => $doc->document_version,
                    'signing_status' => $doc->signing_status?->value,
                    'signing_status_label' => $doc->signing_status?->label(),
                    'signature_summary' => $doc->signature_summary?->value,
                    'signature_summary_label' => $doc->signature_summary?->label(),
                    'notes' => $doc->notes,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'predecessors' => $this->relationLoaded('predecessors')
                ? $this->predecessors->map(fn (Agreement $pred) => [
                    'id' => $pred->id,
                    'agreement_number' => $pred->agreement_number,
                    'effective_date' => $pred->effective_date?->toDateString(),
                    'lifecycle_status' => $pred->lifecycle_status?->value,
                    'lifecycle_status_label' => $pred->lifecycle_status?->label(),
                    'total_amount' => $pred->total_amount,
                    'transition_type' => $pred->pivot?->transition_type,
                ])->values()->all()
                : [],
            'successors' => $this->relationLoaded('successors')
                ? $this->successors->map(fn (Agreement $succ) => [
                    'id' => $succ->id,
                    'agreement_number' => $succ->agreement_number,
                    'effective_date' => $succ->effective_date?->toDateString(),
                    'lifecycle_status' => $succ->lifecycle_status?->value,
                    'lifecycle_status_label' => $succ->lifecycle_status?->label(),
                    'total_amount' => $succ->total_amount,
                    'transition_type' => $succ->pivot?->transition_type,
                ])->values()->all()
                : [],
            'predecessor_transitions' => $this->relationLoaded('predecessorTransitions')
                ? AgreementTransitionResource::collection($this->predecessorTransitions)->resolve($request)
                : [],
            'successor_transitions' => $this->relationLoaded('successorTransitions')
                ? AgreementTransitionResource::collection($this->successorTransitions)->resolve($request)
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
