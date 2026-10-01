<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentState;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\NotApprovedException;
use App\Models\BankTransaction;
use App\Models\Overpayment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentStagingService
{
    /**
     * Stage a payment transaction and allocation proposal.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DuplicatePaymentException
     * @throws InvalidArgumentException
     */
    public function stage(array $data, User $actor): BankTransaction
    {
        // 1. Idempotency check: if transaction with exact idempotency key exists, return it
        $idempotencyKey = (string) $data['idempotency_key'];
        $existing = BankTransaction::with(['allocations.agreement', 'overpayments', 'recordedBy'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // 2. Server recomputes component sum regardless of client input (PRD §2)
        $principal = (int) ($data['principal_amount'] ?? 0);
        $interest = (int) ($data['interest_amount'] ?? 0);
        $admin = (int) ($data['admin_charge_amount'] ?? 0);
        $other = (int) ($data['other_charge_amount'] ?? 0);
        $allocationTotal = $principal + $interest + $admin + $other;

        if ($allocationTotal <= 0) {
            throw new InvalidArgumentException('Total payment allocation must be strictly positive (zero and negative rejection).');
        }

        $txnAmount = isset($data['amount']) && (int) $data['amount'] > 0
            ? (int) $data['amount']
            : $allocationTotal;

        if ($allocationTotal > $txnAmount) {
            throw new InvalidArgumentException(
                "Allocation total ({$allocationTotal}) exceeds transaction amount ({$txnAmount}) (over-allocation rejected)."
            );
        }

        // 3. Format receipt datetime and derive period YYYY-MM (DEC-010 stub)
        $receiptDate = (string) $data['receipt_date'];
        $receiptCarbon = Carbon::parse($receiptDate);
        $transactionDatetime = $receiptCarbon->setTime(12, 0, 0)->format('Y-m-d H:i:s');
        $derivedPeriod = $receiptCarbon->format('Y-m');

        $source = (string) ($data['source'] ?? 'MANUAL_CAPTURE');
        $reference = isset($data['reference']) && trim((string) $data['reference']) !== ''
            ? trim((string) $data['reference'])
            : null;
        $payerVa = isset($data['payer_va']) && trim((string) $data['payer_va']) !== ''
            ? trim((string) $data['payer_va'])
            : null;

        // 4. Duplicate detection via fingerprint (PRD §4, §5 FR-03)
        $fingerprint = BankTransaction::computeFingerprint(
            source: $source,
            datetime: $transactionDatetime,
            amount: $txnAmount,
            reference: $reference,
            payerVa: $payerVa,
        );

        $duplicate = BankTransaction::where('fingerprint', $fingerprint)->exists();
        if ($duplicate) {
            throw DuplicatePaymentException::withFingerprint($fingerprint);
        }

        // 5. Atomic multi-table write inside DB transaction (PRD §2)
        return DB::transaction(function () use (
            $data,
            $actor,
            $idempotencyKey,
            $txnAmount,
            $principal,
            $interest,
            $admin,
            $other,
            $allocationTotal,
            $transactionDatetime,
            $derivedPeriod,
            $source,
            $reference,
            $payerVa,
            $fingerprint,
        ): BankTransaction {
            $transaction = BankTransaction::create([
                'reference' => $reference,
                'reference_namespace' => (string) ($data['reference_namespace'] ?? 'MANUAL'),
                'transaction_datetime' => $transactionDatetime,
                'timezone' => 'Asia/Jakarta',
                'amount' => $txnAmount,
                'payer_name' => $data['payer_name'] ?? null,
                'payer_va' => $payerVa,
                'source' => $source,
                'source_row_identifier' => $data['source_row_identifier'] ?? null,
                'fingerprint' => $fingerprint,
                'idempotency_key' => $idempotencyKey,
                'state' => PaymentState::Draft,
                'receipt_month' => $derivedPeriod,
                'provenance' => 'manual_staging',
                'notes' => $data['notes'] ?? null,
                'recorded_by_id' => $actor->id,
                'version' => 1,
            ]);

            // Staged allocation proposal
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,
                'agreement_id' => $data['agreement_id'],
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'admin_charge_amount' => $admin,
                'other_charge_amount' => $other,
                'total_amount' => $allocationTotal,
                'effective_date' => $data['receipt_date'],
                'period' => $derivedPeriod,
                'state' => PaymentState::Draft,
                'evidence' => $data['evidence'] ?? null,
                'idempotency_key' => (string) Str::uuid(),
                'version' => 1,
            ]);

            // If transaction amount exceeds allocated components, overage becomes unapplied ABT (FR-03)
            $overage = $txnAmount - $allocationTotal;
            if ($overage > 0) {
                Overpayment::create([
                    'bank_transaction_id' => $transaction->id,
                    'partner_id' => $data['partner_id'],
                    'unapplied_amount' => $overage,
                    'proposed_disposition' => 'unapplied_deposit',
                    'disposition_status' => 'unresolved',
                    'evidence' => $data['evidence'] ?? null,
                    'idempotency_key' => (string) Str::uuid(),
                    'version' => 1,
                ]);
            }

            return $transaction->load(['allocations.agreement', 'overpayments', 'recordedBy']);
        });
    }

    /**
     * Submit a draft allocation for review.
     */
    public function submit(PaymentAllocation $allocation, User $actor): PaymentAllocation
    {
        if ($allocation->state !== PaymentState::Draft) {
            throw new InvalidArgumentException("Allocation must be in 'draft' state to be submitted. Current state: '{$allocation->state->value}'.");
        }

        $allocation->update([
            'state' => PaymentState::Submitted,
        ]);

        return $allocation->refresh();
    }

    /**
     * Post allocation to receivable ledger.
     * Blocked pending DEC-008 balance calculation approval.
     */
    public function post(PaymentAllocation $allocation, User $actor): never
    {
        if (app()->bound(AuditService::class)) {
            app(AuditService::class)->logAuthFailure(
                action: 'unauthorized_posting_attempt',
                target: $allocation,
                delta: [
                    'allocation_id' => $allocation->id,
                    'agreement_id' => $allocation->agreement_id,
                    'total_amount' => $allocation->total_amount,
                ],
                reason: 'Posting blocked pending DEC-008 balance calculation approval.',
                actor: $actor,
            );
        }

        throw NotApprovedException::forPaymentPosting();
    }

    /**
     * Enforce second reviewer requirement (DEC-005).
     * Configurable review step, default mandatory, but enforcement throws NotApprovedException.
     */
    public function enforceSecondReview(PaymentAllocation $allocation, User $reviewer): never
    {
        throw NotApprovedException::forSecondReview();
    }
}
