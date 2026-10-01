<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\VirtualAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualAccount extends Model
{
    /** @use HasFactory<VirtualAccountFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'partner_id',
        'va_number',
        'va_number_normalized',
        'provider',
        'valid_from',
        'valid_until',
        'evidence',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Normalize VA number: trim whitespace.
     * Leading zeros are preserved.
     */
    public static function normalizeVaNumber(string $value): string
    {
        return trim($value);
    }
}
