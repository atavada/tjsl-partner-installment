<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceSnapshot extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'file_hash',
        'filename',
        'as_of_date',
        'parser_version',
        'sheet_inventory',
        'status',
        'is_synthetic',
        'recorded_by_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'sheet_inventory' => 'array',
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SourceRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(SourceRow::class, 'snapshot_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
