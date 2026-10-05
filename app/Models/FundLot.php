<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\FundLotType;
use App\Enums\PaymentState;
use Database\Factories\FundLotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * FundLot model representing parked funds across four distinct concepts per DEC-006:
 *
 * 1. Raw receipt: BankTransaction (immutable, no owner needed)
 * 2. ABT (Angsuran Belum Teridentifikasi): owner unknown, NO debt effect
 * 3. Identified but unallocated: owner known, not yet applied to agreement(s)
 * 4. True excess: owner known, payment exceeds total remaining debt
 *
 * Citations:
 * - DEC-006 (2026-10-03): ABT != overpayment. No return, no refund, no delete.
 * - Excess Amount v1 (docs/metric-definitions.md)
 * - formula-specification.md §7–§8
 */
class FundLot extends Model
{
    /** @use HasFactory<FundLotFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $table = 'fund_lots';

    protected $fillable = [
        'bank_transaction_id',
        'partner_id',
        'lot_type',
        'amount',
        'evidence',
        'identified_by_id',
        'identified_at',
        'identification_evidence',
        'source_agreement_id',
        'idempotency_key',
        'reason',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'lot_type' => FundLotType::class,
            'amount' => 'integer',
            'identified_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->amount < 0) {
                throw new InvalidArgumentException('Fund lot amount must be non-negative.');
            }

            // Invariant: allocated + fund lots <= transaction amount (DEC-006, PRD §4)
            if ($model->bank_transaction_id !== null) {
                $transaction = $model->bankTransaction ?? BankTransaction::find($model->bank_transaction_id);
                if ($transaction !== null) {
                    $allocated = (int) PaymentAllocation::where('bank_transaction_id', $model->bank_transaction_id)
                        ->where('state', '!=', PaymentState::Reversed->value)
                        ->sum('total_amount');

                    $otherLots = (int) static::where('bank_transaction_id', $model->bank_transaction_id)
                        ->where('id', '!=', $model->id ?? '')
                        ->sum('amount');

                    if (($allocated + $otherLots + (int) $model->amount) > $transaction->amount) {
                        throw new InvalidArgumentException(
                            "Fund lot exceeds transaction capacity: transaction amount is {$transaction->amount}, but attempted total is "
                            .($allocated + $otherLots + (int) $model->amount)
                        );
                    }
                }
            }
        });

        // No physical delete of fund lots per DEC-006 (unmatched ABT stays queued permanently)
        static::deleting(function (): void {
            throw new LogicException('Fund lots cannot be deleted per DEC-006.');
        });
    }

    /**
     * Identify an ABT lot: transitions abt -> identified_unallocated per DEC-006 & formula-spec §8.
     * Records actor, timestamp, and evidence.
     *
     * @throws InvalidArgumentException When lot is not in ABT state.
     */
    public function identify(Partner $partner, User $actor, string $evidence): self
    {
        if ($this->lot_type !== FundLotType::Abt) {
            throw new InvalidArgumentException("Only ABT lots can be identified. Current lot type: '{$this->lot_type->value}'.");
        }

        if (trim($evidence) === '') {
            throw new InvalidArgumentException('Identification evidence is required.');
        }

        $this->partner_id = $partner->id;
        $this->lot_type = FundLotType::IdentifiedUnallocated;
        $this->identified_by_id = $actor->id;
        $this->identified_at = Carbon::now();
        $this->identification_evidence = $evidence;
        $this->version = (int) $this->version + 1;
        $this->save();

        return $this;
    }

    public function isAbt(): bool
    {
        return $this->lot_type === FundLotType::Abt;
    }

    public function isIdentified(): bool
    {
        return $this->lot_type === FundLotType::IdentifiedUnallocated;
    }

    public function isExcess(): bool
    {
        return $this->lot_type === FundLotType::Excess;
    }

    /**
     * Compute remaining capacity of this lot available for transfer or allocation.
     */
    public function calculateRemainingCapacity(): int
    {
        $transferred = (int) $this->transfers()->sum('amount');

        return max(0, (int) $this->amount - $transferred);
    }

    /**
     * Create an ABT lot with no owner.
     */
    public static function createAbtLot(
        BankTransaction $transaction,
        int $amount,
        ?string $evidence = null,
        ?string $reason = null,
        ?string $idempotencyKey = null
    ): self {
        return self::create([
            'bank_transaction_id' => $transaction->id,
            'partner_id' => null,
            'lot_type' => FundLotType::Abt,
            'amount' => $amount,
            'evidence' => $evidence,
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'reason' => $reason,
            'version' => 1,
        ]);
    }

    /**
     * Create an excess lot when payment exceeds partner total remaining.
     */
    public static function createExcessLot(
        BankTransaction $transaction,
        Partner $partner,
        Agreement $agreement,
        int $amount,
        ?string $evidence = null,
        ?string $reason = null,
        ?string $idempotencyKey = null
    ): self {
        return self::create([
            'bank_transaction_id' => $transaction->id,
            'partner_id' => $partner->id,
            'source_agreement_id' => $agreement->id,
            'lot_type' => FundLotType::Excess,
            'amount' => $amount,
            'evidence' => $evidence,
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
            'reason' => $reason,
            'version' => 1,
        ]);
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
    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by_id');
    }

    /** @return BelongsTo<Agreement, $this> */
    public function sourceAgreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'source_agreement_id');
    }

    /** @return HasMany<FundTransfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(FundTransfer::class, 'source_lot_id');
    }
}
