<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePaymentRequest extends FormRequest
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
            'partner_id' => ['required', 'uuid', 'exists:partners,id'],
            'agreement_id' => ['required', 'uuid', 'exists:agreements,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_va' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:255'],
            'evidence' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'principal_amount' => ['required', 'integer', 'min:0'],
            'interest_amount' => ['required', 'integer', 'min:0'],
            'admin_charge_amount' => ['required', 'integer', 'min:0'],
            'other_charge_amount' => ['required', 'integer', 'min:0'],
            'period_override' => ['nullable', 'string', 'max:7'],
            'period_override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $principal = (int) $this->input('principal_amount', 0);
            $interest = (int) $this->input('interest_amount', 0);
            $admin = (int) $this->input('admin_charge_amount', 0);
            $other = (int) $this->input('other_charge_amount', 0);
            $componentSum = $principal + $interest + $admin + $other;

            // Zero and negative payment rejected (PRD §4 invariant 1, FR-03)
            if ($componentSum <= 0) {
                $v->errors()->add(
                    'principal_amount',
                    'Total alokasi pembayaran harus lebih besar dari 0 (zero and negative payment rejected).'
                );
            }

            // Over-allocation check against explicit transaction amount
            if ($this->filled('amount')) {
                $transactionAmount = (int) $this->input('amount');
                if ($componentSum > $transactionAmount) {
                    $v->errors()->add(
                        'amount',
                        "Jumlah alokasi komponen ({$componentSum}) melebihi nilai transaksi bank ({$transactionAmount}) (over-allocation rejected)."
                    );
                }
            }

            // Check agreement belongs to specified partner
            $partnerId = (string) $this->input('partner_id');
            $agreementId = (string) $this->input('agreement_id');

            if ($partnerId !== '' && $agreementId !== '') {
                $agreement = Agreement::find($agreementId);
                if ($agreement && $agreement->partner_id !== $partnerId) {
                    $v->errors()->add(
                        'agreement_id',
                        'Perjanjian yang dipilih tidak sesuai dengan mitra yang dituju.'
                    );
                }
            }

            // Name-only payment stays unmatched: partner must be verified (PRD §9 gate)
            if ($partnerId !== '') {
                $partner = Partner::find($partnerId);
                if ($partner && $partner->verification_state !== 'verified') {
                    $v->errors()->add(
                        'partner_id',
                        'Pembayaran hanya dapat dialokasikan pada mitra yang telah terverifikasi. Pembayaran tanpa mitra terverifikasi harus tetap berstatus belum cocok (unmatched).'
                    );
                }
            }

            // Period derivation: DEC-010 RESOLVED. Override authority: still OPEN.
            // receipt_month is derived server-side from receipt_date as YYYY-MM.
            // Matching period override (override == derived month) is accepted silently.
            // Differing period override throws NotApprovedException::forPeriodOverride() pending approval.
            if ($this->filled('period_override')) {
                $receiptDate = $this->input('receipt_date');
                $derivedMonth = $receiptDate ? Carbon::parse($receiptDate)->format('Y-m') : null;
                $override = trim((string) $this->input('period_override'));

                if ($derivedMonth !== null && $override !== '' && $override !== $derivedMonth) {
                    throw NotApprovedException::forPeriodOverride();
                }
            }
        });
    }
}
