<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceRow extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'snapshot_id',
        'sheet_name',
        'row_number',
        'cell_coordinates',
        'raw_values',
        'formula_text',
        'cached_values',
        'is_hidden',
        'parse_warnings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_values' => 'array',
            'formula_text' => 'array',
            'cached_values' => 'array',
            'parse_warnings' => 'array',
            'is_hidden' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SourceSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SourceSnapshot::class, 'snapshot_id');
    }

    /**
     * @return HasMany<ReconciliationCase, $this>
     */
    public function reconciliationCases(): HasMany
    {
        return $this->hasMany(ReconciliationCase::class, 'source_row_id');
    }
}
