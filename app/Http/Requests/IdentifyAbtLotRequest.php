<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Partner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request for identifying an ABT lot and transitioning it to identified_unallocated
 * per DEC-006 and formula-specification.md §8.
 */
class IdentifyAbtLotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $fundLot = $this->route('fundLot');

        return $this->user()?->can('identify', $fundLot) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'partner_id' => ['required', 'uuid', 'exists:partners,id'],
            'evidence' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $partnerId = (string) $this->input('partner_id');
            if ($partnerId !== '') {
                $partner = Partner::find($partnerId);
                if ($partner && $partner->verification_state !== 'verified') {
                    $v->errors()->add(
                        'partner_id',
                        'Identifikasi ABT hanya dapat dilakukan pada mitra yang telah terverifikasi.'
                    );
                }
            }
        });
    }
}
