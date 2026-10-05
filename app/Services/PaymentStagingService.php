<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Exceptions\DuplicatePaymentException;
use App\Exceptions\NotApprovedException;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\InstallmentSchedule;
use App\Models\PaymentAllocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentStagingService
{
    public function __construct(
        protected AllocationService $allocationService = new AllocationService,
        protected ?AuditService $auditService = null,
    ) {
        $this->auditService ??= app(AuditService::class);
    }

    /**
     * Stage a payment transaction and allocation proposal.
     * Cashier inputs directly without secondary approval per DEC-005.
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
        $existing = BankTransaction::with(['allocations.agreement', 'fundLots', 'recordedBy'])
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

        // 3. Format receipt datetime and derive period YYYY-MM per DEC-010
        // Period derivation: DEC-010 RESOLVED. Override authority: still OPEN.
        $receiptDate = (string) $data['receipt_date'];
        $receiptCarbon = Carbon::parse($receiptDate);
        $transactionDatetime = $receiptCarbon->setTime(12, 0, 0)->format('Y-m-d H:i:s');
        $derivedPeriod = $receiptCarbon->format('Y-m');

        if (isset($data['period_override']) && trim((string) $data['period_override']) !== '') {
            $override = trim((string) $data['period_override']);
            if ($override !== $derivedPeriod) {
                throw NotApprovedException::forPeriodOverride();
            }
        }

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

            // If transaction amount exceeds allocated components, overage becomes identified-unallocated lot per DEC-006
            $overage = $txnAmount - $allocationTotal;
            if ($overage > 0) {
                FundLot::create([
                    'bank_transaction_id' => $transaction->id,
                    'partner_id' => $data['partner_id'],
                    'lot_type' => FundLotType::IdentifiedUnallocated,
                    'amount' => $overage,
                    'evidence' => $data['evidence'] ?? null,
                    'idempotency_key' => (string) Str::uuid(),
                    'version' => 1,
                ]);
            }

            return $transaction->load(['allocations.agreement', 'fundLots', 'recordedBy']);
        });
    }

    /**
     * Submit a draft allocation for processing.
     * Per DEC-005, cashier has direct authority without requiring a second reviewer.
     * All actions remain audited via Auditable lifecycle hooks and reversible via PaymentReversalService.
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
     * Post allocation to receivable ledger per DEC-008 §6.
     * Cashier posts directly without secondary approval per DEC-005.
     *
     * @throws InvalidArgumentException
     */
    public function post(PaymentAllocation $allocation, User $actor): PaymentAllocation
    {
        if (! $actor->hasPermission(Permission::PaymentPost)) {
            $this->auditService?->log(
                action: 'unauthorized_posting_attempt',
                target: $allocation,
                delta: [
                    'total_amount' => (int) $allocation->total_amount,
                    'agreement_id' => (string) $allocation->agreement_id,
                    'bank_transaction_id' => (string) $allocation->bank_transaction_id,
                ],
                reason: 'Unauthorized attempt to post payment allocation without payment.post permission',
                actor: $actor,
            );

            throw new AuthorizationException('User does not have permission to post payments.');
        }

        if ($allocation->state !== PaymentState::Submitted && $allocation->state !== PaymentState::Draft) {
            throw new InvalidArgumentException("Allocation must be in 'draft' or 'submitted' state to be posted. Current state: '{$allocation->state->value}'.");
        }

        return DB::transaction(function () use ($allocation, $actor): PaymentAllocation {
            /** @var PaymentAllocation $lockedAllocation */
            $lockedAllocation = PaymentAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAllocation->state === PaymentState::Posted) {
                return $lockedAllocation;
            }

            if ($lockedAllocation->state !== PaymentState::Submitted && $lockedAllocation->state !== PaymentState::Draft) {
                throw new InvalidArgumentException("Allocation must be in 'draft' or 'submitted' state to be posted. Current state: '{$lockedAllocation->state->value}'.");
            }

            /** @var Agreement|null $agreement */
            $agreement = Agreement::where('id', $lockedAllocation->agreement_id)
                ->lockForUpdate()
                ->first();

            if ($agreement === null) {
                throw new InvalidArgumentException('Allocation must be linked to a valid agreement to post.');
            }

            // Lock installment schedules for this agreement before calculation per DEC-008 / TiDB safety
            InstallmentSchedule::where('agreement_id', $agreement->id)
                ->lockForUpdate()
                ->get();

            // Execute allocation across installment schedules per DEC-008 §6
            $this->allocationService->allocate(
                allocation: $lockedAllocation,
                agreement: $agreement,
                asOf: Carbon::parse($lockedAllocation->effective_date ?? now()),
            );

            // Transition allocation state to Posted
            $lockedAllocation->update([
                'state' => PaymentState::Posted,
                'approved_by_id' => $actor->id,
                'approved_at' => Carbon::now(),
                'version' => (int) $lockedAllocation->version + 1,
            ]);

            return $lockedAllocation->refresh();
        });
    }
}
