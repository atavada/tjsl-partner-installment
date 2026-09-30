<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class CyclicTransitionException extends DomainException
{
    public static function between(string $predecessorId, string $successorId): self
    {
        return new self("Transition from agreement '{$predecessorId}' to '{$successorId}' would create a cycle in the transition graph.");
    }
}
