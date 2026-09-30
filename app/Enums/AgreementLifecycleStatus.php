<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementLifecycleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case PaidOff = 'paid_off';
    case ClosedByRescheduling = 'closed_by_rescheduling';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::PaidOff => 'Lunas',
            self::ClosedByRescheduling => 'Ditutup karena Rescheduling',
            self::Cancelled => 'Dibatalkan',
            self::Unknown => 'Tidak Diketahui',
        };
    }

    public function isClosed(): bool
    {
        return match ($this) {
            self::PaidOff, self::ClosedByRescheduling, self::Cancelled => true,
            default => false,
        };
    }

    public function isPaidOff(): bool
    {
        return $this === self::PaidOff;
    }

    public function isClosedByRescheduling(): bool
    {
        return $this === self::ClosedByRescheduling;
    }
}
