<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PaymentState;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'bank_transaction_id',
        'agreement_id',
        'principal_amount',
        'interest_amount',
        'admin_charge_amount',
        'other_charge_amount',
        'total_amount',
        'effective_date',
        'period',
        'state',
        'evidence',
        'idempotency_key',
        'approved_by_id',
        'approved_at',
        'approved_source',
        'reversal_of_id',
        'reason',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'integer',
            'interest_amount' => 'integer',
            'admin_charge_amount' => 'integer',
            'other_charge_amount' => 'integer',
            'total_amount' => 'integer',
            'effective_date' => 'date',
            'state' => PaymentState::class,
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Period derivation: DEC-010 RESOLVED. Default allocation period derived from effective_date.
            if ($model->period === null && $model->effective_date !== null) {
                $model->period = Carbon::parse($model->effective_date)->format('Y-m');
            }

            // Component amounts must be non-negative (PRD §4 invariant 1)
            if ($model->principal_amount < 0
                || $model->interest_amount < 0
                || $model->admin_charge_amount < 0
                || $model->other_charge_amount < 0) {
                throw new InvalidArgumentException('Allocation component amounts must be non-negative.');
            }

            // Allocation components sum to the total amount (PRD §4 invariant 2)
            $componentSum = (int) $model->principal_amount
                + (int) $model->interest_amount
                + (int) $model->admin_charge_amount
                + (int) $model->other_charge_amount;

            if ((int) $model->total_amount !== $componentSum) {
                throw new InvalidArgumentException("Allocation total ({$model->total_amount}) must equal component sum ({$componentSum}).");
            }

            // Posted payments must be strictly positive (PRD §4 invariant 1)
            if ($model->state === PaymentState::Posted && $model->total_amount <= 0) {
                throw new InvalidArgumentException('Posted payment allocation must be strictly positive.');
            }

            // Over-allocation check: allocated + unapplied <= transaction amount (PRD §4 invariant 2)
            if ($model->bank_transaction_id !== null && $model->state !== PaymentState::Reversed) {
                $transaction = $model->bankTransaction ?? BankTransaction::find($model->bank_transaction_id);
                if ($transaction !== null) {
                    $otherAllocations = (int) static::where('bank_transaction_id', $model->bank_transaction_id)
                        ->where('id', '!=', $model->id ?? '')
                        ->where('state', '!=', PaymentState::Reversed->value)
                        ->sum('total_amount');

                    $unapplied = (int) Overpayment::where('bank_transaction_id', $model->bank_transaction_id)
                        ->sum('unapplied_amount');

                    if (($otherAllocations + (int) $model->total_amount + $unapplied) > $transaction->amount) {
                        throw new InvalidArgumentException(
                            "Allocation exceeds transaction capacity: transaction amount is {$transaction->amount}, but attempted total is "
                            .($otherAllocations + (int) $model->total_amount + $unapplied)
                        );
                    }
                }
            }

            // Double-posting guard for approved source rows (PRD §4 invariant 5)
            if ($model->approved_source !== null && $model->state === PaymentState::Posted) {
                $sourceExists = static::where('approved_source', $model->approved_source)
                    ->where('id', '!=', $model->id ?? '')
                    ->where('state', PaymentState::Posted->value)
                    ->exists();

                if ($sourceExists) {
                    throw new InvalidArgumentException("Approved source '{$model->approved_source}' has already been posted.");
                }
            }
        });
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    /** @return HasMany<AllocationInstallmentLine, $this> */
    public function installmentLines(): HasMany
    {
        return $this->hasMany(AllocationInstallmentLine::class);
    }
}
