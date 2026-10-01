<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Posted => 'Dibukukan',
            self::Reversed => 'Dibalikkan',
        };
    }

    public function isFinalized(): bool
    {
        return match ($this) {
            self::Posted, self::Reversed => true,
            default => false,
        };
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function isReversed(): bool
    {
        return $this === self::Reversed;
    }
}
