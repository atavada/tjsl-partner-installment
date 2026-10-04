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

    public static function forOverpaymentDisposition(): self
    {
        return new self('ABT / overpayment disposition execution is blocked pending DEC-006 approval.');
    }

    public static function forReceivableAdjustmentPosting(): self
    {
        return new self('Receivable adjustment posting and balance application is blocked pending DEC-008 approval.');
    }

    public static function forPaymentPosting(): self
    {
        return new self('Payment posting to receivable ledger is blocked pending DEC-008 approval.');
    }

    /**
     * Payment period override is blocked pending DEC-010 approval.
     * Note: Period derivation from receipt date is RESOLVED per DEC-010.
     * Override authority and closed-period allocation rules remain OPEN.
     */
    public static function forPeriodOverride(): self
    {
        return new self('Payment period override is blocked pending DEC-010 approval.');
    }

    /**
     * Retained for future approval workflow; currently not called per DEC-005 ruling.
     */
    public static function forSecondReview(): self
    {
        return new self('Second review requirement configuration is blocked pending DEC-005 approval.');
    }
}
