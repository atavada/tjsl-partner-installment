<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FundLot;
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
        return [
            'id' => $this->id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'partner_id' => $this->partner_id,
            'partner' => $this->relationLoaded('partner') && $this->partner
                ? [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'partner_no_id' => $this->partner->partner_no_id,
                ]
                : null,
            'lot_type' => $this->lot_type?->value,
            'lot_type_label' => $this->lot_type?->label(),
            'amount' => $this->amount,
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
            'idempotency_key' => $this->idempotency_key,
            'reason' => $this->reason,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
