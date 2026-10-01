<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentState;
use Database\Factories\BankTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class BankTransaction extends Model
{
    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'reference',
        'reference_normalized',
        'reference_namespace',
        'transaction_datetime',
        'timezone',
        'amount',
        'payer_name',
        'payer_va',
        'source',
        'source_row_identifier',
        'fingerprint',
        'idempotency_key',
        'state',
        'receipt_month',
        'provenance',
        'notes',
        'recorded_by_id',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'transaction_datetime' => 'datetime',
            'amount' => 'integer',
            'state' => PaymentState::class,
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Raw deposits must be non-negative (PRD §4 invariant 1)
            if ($model->amount < 0) {
                throw new InvalidArgumentException('Bank transaction amount must be non-negative.');
            }

            // Posted payments must be strictly positive (PRD §4 invariant 1)
            if ($model->state === PaymentState::Posted && $model->amount <= 0) {
                throw new InvalidArgumentException('Posted bank transaction amount must be strictly positive.');
            }

            // Normalize reference if provided
            if ($model->reference !== null && trim($model->reference) !== '') {
                $model->reference_normalized = mb_strtoupper(trim($model->reference));
            }

            // Derive receipt_month from transaction_datetime if null (DEC-010)
            if ($model->receipt_month === null && $model->transaction_datetime !== null) {
                $model->receipt_month = $model->transaction_datetime->format('Y-m');
            }
        });

        // BankTransaction is immutable: no updates or physical deletes allowed (PRD §4 invariant 4)
        static::updating(function (): void {
            throw new LogicException('BankTransaction is immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('BankTransaction is immutable and cannot be deleted.');
        });
    }

    /**
     * Compute SHA-256 fingerprint for duplicate detection.
     */
    public static function computeFingerprint(
        string $source,
        string $datetime,
        int $amount,
        ?string $reference = null,
        ?string $payerVa = null
    ): string {
        $components = [
            trim($source),
            trim($datetime),
            (string) $amount,
            mb_strtoupper(trim((string) $reference)),
            trim((string) $payerVa),
        ];

        return hash('sha256', implode('|', $components));
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<Overpayment, $this> */
    public function overpayments(): HasMany
    {
        return $this->hasMany(Overpayment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
