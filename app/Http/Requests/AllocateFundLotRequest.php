<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AgreementLifecycleStatus;
use App\Models\Agreement;
use App\Models\FundLot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form request for allocating an identified ABT lot to an agreement
 * per DEC-006, DP-7, DP-8, and TASK-REM-007.
 */
class AllocateFundLotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var FundLot|null $fundLot */
        $fundLot = $this->route('fundLot');

        return $fundLot !== null && ($this->user()?->can('allocate', $fundLot) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agreement_id' => ['required', 'uuid', 'exists:agreements,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'effective_date' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Configure the validator instance with business invariant checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var FundLot|null $fundLot */
            $fundLot = $this->route('fundLot');
            if ($fundLot === null) {
                return;
            }

            $agreementId = (string) $this->input('agreement_id');
            if ($agreementId !== '') {
                $agreement = Agreement::find($agreementId);
                if ($agreement !== null) {
                    if ($agreement->partner_id !== $fundLot->partner_id) {
                        $v->errors()->add(
                            'agreement_id',
                            'Perjanjian yang dipilih bukan milik mitra pemilik dana parkir.'
                        );
                    }

                    if ($agreement->lifecycle_status !== AgreementLifecycleStatus::Active) {
                        $v->errors()->add(
                            'agreement_id',
                            'Hanya perjanjian dengan status Aktif yang dapat menerima alokasi dana.'
                        );
                    }
                }
            }

            $amount = (int) $this->input('amount');
            if ($amount > 0) {
                $capacity = $fundLot->calculateRemainingCapacity();
                if ($amount > $capacity) {
                    $v->errors()->add(
                        'amount',
                        'Jumlah alokasi melebihi sisa kapasitas dana parkir (Rp '.number_format($capacity, 0, ',', '.').').'
                    );
                }
            }
        });
    }
}
