<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * System roles and permissions enum.
 *
 * Business role labels confirmed per DEC-009 (resolved 2026-10-03, PRD §3).
 */
enum Role: string
{
    case Operator = 'operator';
    case ReconciliationReviewer = 'reconciliation_reviewer';
    case ProcessOwner = 'process_owner';
    case Auditor = 'auditor';
    case SystemAdmin = 'system_admin';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Kasir TJSL',
            self::ReconciliationReviewer => 'Kepala Sub Divisi',
            self::ProcessOwner => 'Sekper / Kepala Divisi',
            self::Auditor => 'Viewer',
            self::SystemAdmin => 'System Admin',
        };
    }

    /**
     * Alias for label() returning confirmed business name per DEC-009.
     */
    public function businessName(): string
    {
        return $this->label();
    }

    public function isFinancial(): bool
    {
        return match ($this) {
            self::Operator, self::ReconciliationReviewer, self::ProcessOwner => true,
            self::Auditor, self::SystemAdmin => false,
        };
    }
}
