<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentState;
use App\Exceptions\NotApprovedException;
use Database\Factories\ReceivableAdjustmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class ReceivableAdjustment extends Model
{
    /** @use HasFactory<ReceivableAdjustmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'agreement_id',
        'adjustment_type',
        'principal_amount',
        'interest_amount',
        'admin_charge_amount',
        'other_charge_amount',
        'total_amount',
        'effective_date',
        'reason',
        'evidence',
        'idempotency_key',
        'state',
        'approved_by_id',
        'approved_at',
        'reversal_of_id',
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
            // Components must sum to total amount
            $componentSum = (int) $model->principal_amount
                + (int) $model->interest_amount
                + (int) $model->admin_charge_amount
                + (int) $model->other_charge_amount;

            if ((int) $model->total_amount !== $componentSum) {
                throw new InvalidArgumentException("Adjustment total ({$model->total_amount}) must equal component sum ({$componentSum}).");
            }
        });
    }

    /**
     * Apply adjustment to receivable balance.
     * Blocked pending DEC-008 approval (balance formula not approved).
     */
    public function applyToBalance(): never
    {
        throw NotApprovedException::forReceivableAdjustmentPosting();
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

    /** @return BelongsTo<ReceivableAdjustment, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasMany<ReceivableAdjustment, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
