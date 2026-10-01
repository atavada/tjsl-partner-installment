<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AgreementTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AgreementTransition
 */
class AgreementTransitionResource extends JsonResource
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
            'predecessor_id' => $this->predecessor_id,
            'successor_id' => $this->successor_id,
            'transition_type' => $this->transition_type?->value,
            'transition_type_label' => $this->transition_type?->label(),
            'effective_date' => $this->effective_date?->toDateString(),
            'reason' => $this->reason,
            'approved_principal_amount' => $this->approved_principal_amount,
            'approved_interest_amount' => $this->approved_interest_amount,
            'approved_admin_charge_amount' => $this->approved_admin_charge_amount,
            'approved_by' => $this->relationLoaded('approvedBy') && $this->approvedBy
                ? [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ]
                : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'version' => $this->version,
            'predecessor' => $this->relationLoaded('predecessor') && $this->predecessor
                ? [
                    'id' => $this->predecessor->id,
                    'agreement_number' => $this->predecessor->agreement_number,
                    'partner_id' => $this->predecessor->partner_id,
                ]
                : null,
            'successor' => $this->relationLoaded('successor') && $this->successor
                ? [
                    'id' => $this->successor->id,
                    'agreement_number' => $this->successor->agreement_number,
                    'partner_id' => $this->successor->partner_id,
                ]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
