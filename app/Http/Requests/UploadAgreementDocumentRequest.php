<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Agreement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadAgreementDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Agreement|null $agreement */
        $agreement = $this->route('agreement');

        return $agreement ? ($this->user()?->can('uploadDocument', $agreement) ?? false) : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            'document_type' => ['nullable', 'string', 'in:contract,addendum,collateral,id_card,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
