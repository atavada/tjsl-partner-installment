<?php

declare(strict_types=1);

namespace App\Enums;

enum ExportType: string
{
    case MonitoringMonthly = 'monitoring_monthly';
    case ReconciliationDifferences = 'reconciliation_differences';
    case PartnerLedger = 'partner_ledger';
    case AuditExtract = 'audit_extract';

    public function label(): string
    {
        return match ($this) {
            self::MonitoringMonthly => 'Monitoring Bulanan',
            self::ReconciliationDifferences => 'Perbedaan Rekonsiliasi',
            self::PartnerLedger => 'Buku Besar Mitra',
            self::AuditExtract => 'Ekstrak Audit',
        };
    }
}
