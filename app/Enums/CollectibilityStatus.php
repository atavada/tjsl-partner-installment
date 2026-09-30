<?php

declare(strict_types=1);

namespace App\Enums;

enum CollectibilityStatus: string
{
    case Current = 'current';
    case Substandard = 'substandard';
    case Loss = 'loss';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Lancar',
            self::Substandard => 'Kurang Lancar',
            self::Loss => 'Bermasalah',
            self::Unknown => 'Tidak Diketahui',
        };
    }
}
