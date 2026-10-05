<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Models\Partner;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPartnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('viewAny', Partner::class)) {
            return false;
        }

        if ($this->input('type') === 'va' && ! $user->can(Permission::VaReveal->value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:100'],
            'type' => [
                'nullable',
                'string',
                Rule::in(['no_id', 'name', 'agreement', 'va']),
                'required_with:query',
            ],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
