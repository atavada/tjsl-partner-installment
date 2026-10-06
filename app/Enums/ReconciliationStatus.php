<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Unreviewed = 'unreviewed';
    case NeedsEvidence = 'needs_evidence';
    case Resolved = 'resolved';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unreviewed => 'Belum Ditinjau',
            self::NeedsEvidence => 'Perlu Bukti',
            self::Resolved => 'Terselesaikan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }
}
