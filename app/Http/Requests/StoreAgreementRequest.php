<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Agreement::class) ?? false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $routePartner = $this->route('partner');
        if ($routePartner && ! $this->has('partner_id')) {
            $this->merge([
                'partner_id' => is_string($routePartner) ? $routePartner : $routePartner->id,
            ]);
        }
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
            'agreement_number' => ['required', 'string', 'max:100'],
            'batch_year' => ['nullable', 'string', 'max:10'],
            'business_group' => ['nullable', 'string', 'max:100'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:360'],
            'application_date' => ['nullable', 'date'],
            'contract_date' => ['nullable', 'date'],
            'effective_date' => ['required', 'date'],
            'loan_start_date' => ['nullable', 'date'],
            'first_due_date' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'principal_amount' => ['required', 'integer', 'min:0'],
            'interest_amount' => ['nullable', 'integer', 'min:0'],
            'admin_charge_amount' => ['nullable', 'integer', 'min:0'],
            'other_charge_amount' => ['nullable', 'integer', 'min:0'],
            'interest_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        ];
    }
}
