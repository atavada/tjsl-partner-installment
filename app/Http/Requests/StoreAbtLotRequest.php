<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BankTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request for capturing an unallocated/unidentified deposit (ABT lot)
 * per DEC-006 and formula-specification.md §8.
 *
 * Explicitly does NOT require partner_id or agreement_id, enabling
 * deposits to be parked without affecting any receivable ledger.
 */
class StoreAbtLotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', BankTransaction::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'bank_transaction_id' => ['nullable', 'uuid', 'exists:bank_transactions,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_va' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'evidence' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
