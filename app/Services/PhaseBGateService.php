<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotApprovedException;

class PhaseBGateService
{
    /**
     * Determine whether Phase B gates have been signed off.
     */
    public function isPhaseBApproved(): bool
    {
        return (bool) config('finance.phase_b_approved', false);
    }

    /**
     * Assert that workbook/banking feed ingestion is permitted.
     * Synthetic data ingestion is allowed in prototype mode.
     * Live/production data ingestion is gated on Phase B sign-off per PRD §9.
     *
     * @throws NotApprovedException
     */
    public function assertIngestionAllowed(bool $isSynthetic, string $sourceIdentifier = 'unknown'): void
    {
        if ($isSynthetic) {
            return;
        }

        if (! $this->isPhaseBApproved()) {
            throw NotApprovedException::forPhaseBGate(
                "Ingestion for [{$sourceIdentifier}] is blocked pending Phase B gate approval per PRD §9."
            );
        }
    }
}
