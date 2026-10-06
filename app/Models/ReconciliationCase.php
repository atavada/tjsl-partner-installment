<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DiscrepancyType;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationCase extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'case_number',
        'case_type',
        'source_row_id',
        'bank_transaction_id',
        'agreement_id',
        'discrepancy_type',
        'status',
        'candidate_matches',
        'evidence',
        'resolution_notes',
        'reviewer_id',
        'approved_by_id',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'discrepancy_type' => DiscrepancyType::class,
            'candidate_matches' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SourceRow, $this>
     */
    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(SourceRow::class, 'source_row_id');
    }

    /**
     * @return BelongsTo<BankTransaction, $this>
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    /**
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'agreement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
