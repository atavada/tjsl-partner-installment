<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * Collectibility risk classification levels.
 *
 * Implements DEC-007 (RESOLVED 2026-10-03) and docs/formula-specification.md §9.4.
 * Metric reference: Collectibility Label v1.
 *
 * Note: DP-2 remains OPEN. The month-count algorithm is an input parameter ($lateMonths),
 * not computed within this enum.
 */
enum CollectibilityStatus: string
{
    case Lancar = 'lancar';
    case KurangLancar = 'kurang_lancar';
    case Diragukan = 'diragukan';
    case Bermasalah = 'bermasalah';
    case Lunas = 'lunas';
    case Unknown = 'unknown';

    public const ACTIVE_RULE_VERSION = 'v1';

    public const RULE_VERSIONS = [
        'v1' => [
            'decision_ref' => 'DEC-007',
            'metric_ref' => 'Collectibility Label v1',
            'bands' => [
                [
                    'status' => self::Lancar,
                    'min_late_months' => 0,
                    'max_late_months' => 1,
                    'label' => 'Lancar',
                ],
                [
                    'status' => self::KurangLancar,
                    'min_late_months' => 2,
                    'max_late_months' => 6,
                    'label' => 'Kurang Lancar',
                ],
                [
                    'status' => self::Diragukan,
                    'min_late_months' => 7,
                    'max_late_months' => 9,
                    'label' => 'Diragukan',
                ],
                [
                    'status' => self::Bermasalah,
                    'min_late_months' => 10,
                    'max_late_months' => null,
                    'label' => 'Bermasalah',
                ],
            ],
        ],
    ];

    public function label(): string
    {
        return match ($this) {
            self::Lancar => 'Lancar',
            self::KurangLancar => 'Kurang Lancar',
            self::Diragukan => 'Diragukan',
            self::Bermasalah => 'Bermasalah',
            self::Lunas => 'LUNAS',
            self::Unknown => 'Tidak Diketahui',
        };
    }

    /**
     * @return list<array{status: self, min_late_months: int, max_late_months: int|null, label: string}>
     */
    public static function monthBand(string $ruleVersion = self::ACTIVE_RULE_VERSION): array
    {
        if (! isset(self::RULE_VERSIONS[$ruleVersion])) {
            throw new InvalidArgumentException("Unknown collectibility rule version: {$ruleVersion}");
        }

        return self::RULE_VERSIONS[$ruleVersion]['bands'];
    }

    /**
     * Determine collectibility status from late months using active rule version table.
     * Clamps negative months to 0 (treated as on-time / Lancar).
     */
    public static function fromLateMonths(int $lateMonths, string $ruleVersion = self::ACTIVE_RULE_VERSION): self
    {
        $effectiveMonths = max(0, $lateMonths);
        $bands = self::monthBand($ruleVersion);

        foreach ($bands as $band) {
            $min = $band['min_late_months'];
            $max = $band['max_late_months'];

            if ($effectiveMonths >= $min && ($max === null || $effectiveMonths <= $max)) {
                return $band['status'];
            }
        }

        return self::Bermasalah;
    }

    /**
     * Determine collectibility status per formula-specification.md §9.4.
     *
     * Legacy bug guard: unverified data MUST NEVER return Lunas, even when balance is zero.
     */
    public static function fromBalance(
        int $remainingBalance,
        bool $dataVerified,
        int $lateMonths,
        string $ruleVersion = self::ACTIVE_RULE_VERSION
    ): self {
        if (! $dataVerified) {
            return self::Unknown;
        }

        if ($remainingBalance === 0) {
            return self::Lunas;
        }

        return self::fromLateMonths($lateMonths, $ruleVersion);
    }
}
