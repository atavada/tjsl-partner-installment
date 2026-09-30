<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'partner_no_id',
        'partner_no_id_normalized',
        'nik',
        'nik_normalized',
        'name',
        'phone',
        'address',
        'business_type',
        'region',
        'verification_state',
        'provenance',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    /** @return HasMany<PartnerAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(PartnerAlias::class);
    }

    /** @return HasMany<VirtualAccount, $this> */
    public function virtualAccounts(): HasMany
    {
        return $this->hasMany(VirtualAccount::class);
    }

    /** @return HasMany<Agreement, $this> */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /**
     * Normalize partner_no_id: trim whitespace, uppercase.
     * Leading zeros are preserved — this is string normalization, not numeric conversion.
     */
    public static function normalizePartnerNoId(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_strtoupper(trim($value));
    }

    /**
     * Normalize NIK: trim whitespace.
     * Leading zeros are preserved.
     */
    public static function normalizeNik(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
