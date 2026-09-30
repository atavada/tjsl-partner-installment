<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class NotApprovedException extends RuntimeException
{
    public static function forLifecycleTransition(string $targetStatus): self
    {
        return new self("Operational lifecycle transition to '{$targetStatus}' is not approved per DEC-002.");
    }

    public static function forSigningTransition(string $targetStatus): self
    {
        return new self("Operational signing transition to '{$targetStatus}' is not approved per DEC-003.");
    }

    public static function forBalanceCalculation(): self
    {
        return new self('Balance and installment schedule calculation is blocked pending DEC-008 approval.');
    }
}
