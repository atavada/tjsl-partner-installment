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
    case Kasir = 'operator';
    case KepalaSubDivisi = 'reconciliation_reviewer';
    case Sekper = 'process_owner';
    case Viewer = 'viewer';
    case Auditor = 'auditor';
    case SystemAdmin = 'system_admin';

    public const Operator = self::Kasir;

    public const ReconciliationReviewer = self::KepalaSubDivisi;

    public const ProcessOwner = self::Sekper;

    public function label(): string
    {
        return match ($this) {
            self::Kasir => 'Kasir TJSL',
            self::KepalaSubDivisi => 'Kepala Sub Divisi',
            self::Sekper => 'Sekper / Kepala Divisi',
            self::Viewer, self::Auditor => 'Viewer',
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
            self::Kasir, self::KepalaSubDivisi, self::Sekper => true,
            self::Viewer, self::Auditor, self::SystemAdmin => false,
        };
    }
}
