<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AgreementLifecycleStatus;
use App\Models\Agreement;
use App\Models\FundLot;
use App\Models\FundTransfer;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FundLot
 */
class FundLotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partnerData = null;
        if ($this->relationLoaded('partner') && $this->partner) {
            $balanceService = app(BalanceService::class);
            $activeAgreements = $this->partner->agreements()
                ->where('lifecycle_status', AgreementLifecycleStatus::Active->value)
                ->orderBy('created_at')
                ->get()
                ->map(function (Agreement $agr) use ($balanceService): array {
                    $bal = $balanceService->getBalance($agr);

                    return [
                        'id' => $agr->id,
                        'agreement_number' => $agr->agreement_number,
                        'remaining_balance' => is_int($bal['total_remaining']) ? $bal['total_remaining'] : 0,
                        'principal_remaining' => is_int($bal['principal_remaining']) ? $bal['principal_remaining'] : 0,
                    ];
                });

            $partnerData = [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
                'partner_no_id' => $this->partner->partner_no_id,
                'total_remaining_debt' => $balanceService->getPartnerTotalRemaining($this->partner),
                'active_agreements' => $activeAgreements,
            ];
        }

        return [
            'id' => $this->id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'bank_transaction' => $this->relationLoaded('bankTransaction') && $this->bankTransaction
                ? [
                    'id' => $this->bankTransaction->id,
                    'reference' => $this->bankTransaction->reference,
                    'amount' => $this->bankTransaction->amount,
                    'payer_name' => $this->bankTransaction->payer_name,
                    'transaction_datetime' => $this->bankTransaction->transaction_datetime,
                    'source' => $this->bankTransaction->source,
                ]
                : null,
            'partner_id' => $this->partner_id,
            'partner' => $partnerData,
            'lot_type' => $this->lot_type?->value,
            'lot_type_label' => $this->lot_type?->label(),
            'amount' => $this->amount,
            'remaining_capacity' => $this->calculateRemainingCapacity(),
            'is_allocated' => $this->isAllocated(),
            'evidence' => $this->evidence,
            'identified_by_id' => $this->identified_by_id,
            'identified_by' => $this->relationLoaded('identifiedBy') && $this->identifiedBy
                ? [
                    'id' => $this->identifiedBy->id,
                    'name' => $this->identifiedBy->name,
                ]
                : null,
            'identified_at' => $this->identified_at?->toIso8601String(),
            'identification_evidence' => $this->identification_evidence,
            'source_agreement_id' => $this->source_agreement_id,
            'source_agreement' => $this->relationLoaded('sourceAgreement') && $this->sourceAgreement
                ? [
                    'id' => $this->sourceAgreement->id,
                    'agreement_number' => $this->sourceAgreement->agreement_number,
                ]
                : null,
            'transfers' => $this->relationLoaded('transfers') && $this->transfers
                ? $this->transfers->map(fn (FundTransfer $t): array => [
                    'id' => $t->id,
                    'amount' => $t->amount,
                    'effective_date' => $t->effective_date?->toDateString(),
                    'reason' => $t->reason,
                    'target_agreement' => $t->targetAgreement ? [
                        'id' => $t->targetAgreement->id,
                        'agreement_number' => $t->targetAgreement->agreement_number,
                    ] : null,
                    'actor' => $t->actor ? [
                        'id' => $t->actor->id,
                        'name' => $t->actor->name,
                    ] : null,
                    'linked_allocation_id' => $t->linked_allocation_id,
                    'created_at' => $t->created_at?->toIso8601String(),
                ])
                : [],
            'idempotency_key' => $this->idempotency_key,
            'reason' => $this->reason,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
