<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementTransitionType: string
{
    case Amendment = 'amendment';
    case Rescheduling = 'rescheduling';
    case Closure = 'closure';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Amendment => 'Adendum',
            self::Rescheduling => 'Rescheduling',
            self::Closure => 'Penutupan',
            self::Reversal => 'Pembalikan',
        };
    }
}
