<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParallelRunDiscrepancy extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'run_date',
        'agreement_id',
        'legacy_values',
        'ledger_values',
        'variance_amount',
        'variance_type',
        'status',
        'notes',
        'resolved_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'legacy_values' => 'array',
            'ledger_values' => 'array',
            'variance_amount' => 'integer',
        ];
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
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
