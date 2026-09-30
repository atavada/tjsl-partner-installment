<?php

declare(strict_types=1);

namespace App\Enums;

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
            self::Operator => 'Operator',
            self::ReconciliationReviewer => 'Reconciliation Reviewer',
            self::ProcessOwner => 'Process Owner',
            self::Auditor => 'Auditor',
            self::SystemAdmin => 'System Admin',
        };
    }

    public function isFinancial(): bool
    {
        return match ($this) {
            self::Operator, self::ReconciliationReviewer, self::ProcessOwner => true,
            self::Auditor, self::SystemAdmin => false,
        };
    }
}
