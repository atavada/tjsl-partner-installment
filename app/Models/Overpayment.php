<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PaymentState;
use App\Exceptions\NotApprovedException;
use Database\Factories\OverpaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class Overpayment extends Model
{
    /** @use HasFactory<OverpaymentFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'bank_transaction_id',
        'partner_id',
        'unapplied_amount',
        'proposed_disposition',
        'disposition_status',
        'evidence',
        'idempotency_key',
        'approved_by_id',
        'approved_at',
        'reason',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'unapplied_amount' => 'integer',
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->unapplied_amount < 0) {
                throw new InvalidArgumentException('Unapplied amount must be non-negative.');
            }

            // Invariant 2: allocated + unapplied <= transaction amount
            if ($model->bank_transaction_id !== null) {
                $transaction = $model->bankTransaction ?? BankTransaction::find($model->bank_transaction_id);
                if ($transaction !== null) {
                    $allocated = (int) PaymentAllocation::where('bank_transaction_id', $model->bank_transaction_id)
                        ->where('state', '!=', PaymentState::Reversed->value)
                        ->sum('total_amount');

                    $otherUnapplied = (int) static::where('bank_transaction_id', $model->bank_transaction_id)
                        ->where('id', '!=', $model->id ?? '')
                        ->sum('unapplied_amount');

                    if (($allocated + $otherUnapplied + (int) $model->unapplied_amount) > $transaction->amount) {
                        throw new InvalidArgumentException(
                            "Overpayment exceeds transaction capacity: transaction amount is {$transaction->amount}, but attempted total is "
                            .($allocated + $otherUnapplied + (int) $model->unapplied_amount)
                        );
                    }
                }
            }
        });
    }

    /**
     * Execute overpayment disposition (offset/refund).
     * Blocked pending DEC-006 approval (ABT/overpayment disposition policy OPEN).
     */
    public function executeDisposition(): never
    {
        throw NotApprovedException::forOverpaymentDisposition();
    }

    /** @return BelongsTo<BankTransaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
