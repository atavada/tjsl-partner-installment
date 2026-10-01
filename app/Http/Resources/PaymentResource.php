<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BankTransaction;
use App\Models\Overpayment;
use App\Models\PaymentAllocation;
use App\Services\MaskingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankTransaction
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MaskingService $maskingService */
        $maskingService = app(MaskingService::class);
        $user = $request->user();

        $maskedPayerVa = $this->payer_va !== null
            ? $maskingService->maskVaNumber($this->payer_va)
            : null;

        $overpaymentsTotal = $this->relationLoaded('overpayments')
            ? (int) $this->overpayments->sum('unapplied_amount')
            : (int) Overpayment::where('bank_transaction_id', $this->id)->sum('unapplied_amount');

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'reference_normalized' => $this->reference_normalized,
            'reference_namespace' => $this->reference_namespace,
            'transaction_datetime' => $this->transaction_datetime?->toIso8601String(),
            'timezone' => $this->timezone,
            'amount' => $this->amount,
            'payer_name' => $this->payer_name,
            'payer_va' => $maskedPayerVa,
            'payer_va_raw' => $this->payer_va,
            'source' => $this->source,
            'source_row_identifier' => $this->source_row_identifier,
            'fingerprint' => $this->fingerprint,
            'idempotency_key' => $this->idempotency_key,
            'state' => $this->state?->value,
            'state_label' => $this->state?->label(),
            'receipt_month' => $this->receipt_month,
            'provenance' => $this->provenance,
            'notes' => $this->notes,
            'recorded_by' => $this->relationLoaded('recordedBy') && $this->recordedBy
                ? [
                    'id' => $this->recordedBy->id,
                    'name' => $this->recordedBy->name,
                ]
                : null,
            'version' => $this->version,
            'allocations' => $this->relationLoaded('allocations')
                ? $this->allocations->map(fn (PaymentAllocation $alloc) => [
                    'id' => $alloc->id,
                    'bank_transaction_id' => $alloc->bank_transaction_id,
                    'agreement_id' => $alloc->agreement_id,
                    'agreement_number' => $alloc->agreement?->agreement_number,
                    'principal_amount' => $alloc->principal_amount,
                    'interest_amount' => $alloc->interest_amount,
                    'admin_charge_amount' => $alloc->admin_charge_amount,
                    'other_charge_amount' => $alloc->other_charge_amount,
                    'total_amount' => $alloc->total_amount,
                    'effective_date' => $alloc->effective_date?->toDateString(),
                    'period' => $alloc->period,
                    'state' => $alloc->state?->value,
                    'state_label' => $alloc->state?->label(),
                    'evidence' => $alloc->evidence,
                    'reversal_of_id' => $alloc->reversal_of_id,
                    'reason' => $alloc->reason,
                    'approved_by' => $alloc->relationLoaded('approvedBy') && $alloc->approvedBy
                        ? [
                            'id' => $alloc->approvedBy->id,
                            'name' => $alloc->approvedBy->name,
                        ]
                        : null,
                    'approved_at' => $alloc->approved_at?->toIso8601String(),
                    'created_at' => $alloc->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'overpayments' => $this->relationLoaded('overpayments')
                ? $this->overpayments->map(fn (Overpayment $op) => [
                    'id' => $op->id,
                    'partner_id' => $op->partner_id,
                    'unapplied_amount' => $op->unapplied_amount,
                    'proposed_disposition' => $op->proposed_disposition,
                    'disposition_status' => $op->disposition_status,
                    'created_at' => $op->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
            'overpayment_amount' => $overpaymentsTotal,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
