<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartnerAliasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAlias extends Model
{
    /** @use HasFactory<PartnerAliasFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'partner_id',
        'name_raw',
        'name_normalized',
        'source',
        'reviewer_id',
        'state',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Normalize name: trim, lowercase for search.
     */
    public static function normalizeName(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
