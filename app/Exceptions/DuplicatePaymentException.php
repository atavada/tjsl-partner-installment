<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class DuplicatePaymentException extends InvalidArgumentException
{
    public static function withFingerprint(string $fingerprint): self
    {
        return new self("Duplicate payment detected with transaction fingerprint '{$fingerprint}'.");
    }

    public static function withIdempotencyKey(string $key): self
    {
        return new self("Duplicate payment detected with idempotency key '{$key}'.");
    }
}
