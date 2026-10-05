<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fund lot classification per DEC-006 (2026-10-03) and formula-specification.md §7–§8.
 *
 * Distinguishes four money concepts:
 * 1. Raw receipt: BankTransaction (immutable, no owner needed)
 * 2. ABT: FundLotType::Abt (owner unknown, no debt effect)
 * 3. Identified but unallocated: FundLotType::IdentifiedUnallocated (owner known, not applied to agreement)
 * 4. True excess: FundLotType::Excess (owner known, payment exceeds total remaining debt)
 */
enum FundLotType: string
{
    case Abt = 'abt';
    case IdentifiedUnallocated = 'identified_unallocated';
    case Excess = 'excess';

    public function label(): string
    {
        return match ($this) {
            self::Abt => 'Angsuran Belum Teridentifikasi (ABT)',
            self::IdentifiedUnallocated => 'Teridentifikasi Belum Teralokasi',
            self::Excess => 'Kelebihan Pembayaran (Excess)',
        };
    }
}
