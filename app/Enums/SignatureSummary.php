<?php

declare(strict_types=1);

namespace App\Enums;

enum SignatureSummary: string
{
    case Unsigned = 'unsigned';
    case Signed = 'signed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Unsigned => 'Belum TTD',
            self::Signed => 'Sudah TTD',
            self::Unknown => 'Tidak Diketahui',
        };
    }
}
