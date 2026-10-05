<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestructureAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Agreement|null $agreement */
        $agreement = $this->route('agreement');

        return $agreement ? ($this->user()?->can('restructure', $agreement) ?? false) : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'successor_agreement_number' => ['required', 'string', 'max:100'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:360'],
            'approved_principal_amount' => ['required', 'integer', 'min:0'],
            'approved_interest_amount' => ['nullable', 'integer', 'min:0'],
            'approved_admin_charge_amount' => ['nullable', 'integer', 'min:0'],
            'other_charge_amount' => ['nullable', 'integer', 'min:0'],
            'interest_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maturity_date' => ['nullable', 'date'],
            'first_due_date' => ['nullable', 'date'],
            'addendum_document' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}
