<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\FundLotType;
use Database\Factories\FundTransferFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * FundTransfer model representing cross-partner reallocation of fund lots
 * per DEC-006 and formula-specification.md §7.
 *
 * Citation: DEC-006, formula-specification.md §7.
 * "Reallocation to another partner creates an explicit transfer record:
 * source_lot_id, target_partner_id, target_agreement_id, amount, reason,
 * actor, effective_date, linked_allocation_id."
 */
class FundTransfer extends Model
{
    /** @use HasFactory<FundTransferFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $table = 'fund_transfers';

    protected $fillable = [
        'source_lot_id',
        'target_partner_id',
        'target_agreement_id',
        'amount',
        'reason',
        'actor_id',
        'effective_date',
        'linked_allocation_id',
        'idempotency_key',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_date' => 'date',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->amount <= 0) {
                throw new InvalidArgumentException('Fund transfer amount must be strictly positive.');
            }
        });
    }

    /**
     * Atomically execute a fund transfer from a fund lot to a target partner agreement.
     * Implements atomic lock-check-write transaction per formula-spec §7.
     *
     * @throws InvalidArgumentException When lot has insufficient capacity or invalid state.
     */
    public static function executeTransfer(
        FundLot $sourceLot,
        Partner $targetPartner,
        Agreement $targetAgreement,
        int $amount,
        string $reason,
        User $actor,
        string $effectiveDate,
        ?string $linkedAllocationId = null,
        ?string $idempotencyKey = null
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be strictly positive.');
        }

        if ($targetAgreement->partner_id !== $targetPartner->id) {
            throw new InvalidArgumentException('Target agreement does not belong to the target partner.');
        }

        return DB::transaction(function () use (
            $sourceLot,
            $targetPartner,
            $targetAgreement,
            $amount,
            $reason,
            $actor,
            $effectiveDate,
            $linkedAllocationId,
            $idempotencyKey
        ): self {
            /** @var FundLot|null $lockedLot */
            $lockedLot = FundLot::where('id', $sourceLot->id)->lockForUpdate()->first();

            if ($lockedLot === null) {
                throw new InvalidArgumentException('Source fund lot not found.');
            }

            if ($lockedLot->lot_type === FundLotType::Abt) {
                throw new InvalidArgumentException('Cannot transfer an unidentified ABT lot. Identify the lot before transferring.');
            }

            $remainingCapacity = $lockedLot->calculateRemainingCapacity();
            if ($amount > $remainingCapacity) {
                throw new InvalidArgumentException(
                    "Transfer amount ({$amount}) exceeds available lot capacity ({$remainingCapacity}) (double-consumption rejected)."
                );
            }

            // Deduct lot amount & transition status when fully exhausted per TASK-REM-007
            $lockedLot->amount = (int) $lockedLot->amount - $amount;
            if ($lockedLot->amount === 0) {
                $lockedLot->lot_type = FundLotType::Allocated;
            }
            $lockedLot->version = (int) $lockedLot->version + 1;
            $lockedLot->save();

            $sourceLot->amount = $lockedLot->amount;
            $sourceLot->lot_type = $lockedLot->lot_type;

            return self::create([
                'source_lot_id' => $lockedLot->id,
                'target_partner_id' => $targetPartner->id,
                'target_agreement_id' => $targetAgreement->id,
                'amount' => $amount,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'effective_date' => $effectiveDate,
                'linked_allocation_id' => $linkedAllocationId,
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                'version' => 1,
            ]);
        });
    }

    /** @return BelongsTo<FundLot, $this> */
    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(FundLot::class, 'source_lot_id');
    }

    /** @return BelongsTo<Partner, $this> */
    public function targetPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'target_partner_id');
    }

    /** @return BelongsTo<Agreement, $this> */
    public function targetAgreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'target_agreement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function linkedAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'linked_allocation_id');
    }
}
