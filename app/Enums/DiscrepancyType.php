<?php

declare(strict_types=1);

namespace App\Enums;

enum DiscrepancyType: string
{
    case LunasWithPositiveBalance = 'lunas_with_positive_balance';
    case FormulaError = 'formula_error';
    case MissingPartnerId = 'missing_partner_id';
    case NameMismatch = 'name_mismatch';
    case AmountDiscrepancy = 'amount_discrepancy';
    case UnmatchedDeposit = 'unmatched_deposit';

    public function label(): string
    {
        return match ($this) {
            self::LunasWithPositiveBalance => 'Status Lunas dengan Saldo Positif',
            self::FormulaError => 'Kesalahan Formula Excel (#REF! / #VALUE!)',
            self::MissingPartnerId => 'ID Mitra Tidak Ditemukan',
            self::NameMismatch => 'Ketidakcocokan Nama Mitra',
            self::AmountDiscrepancy => 'Ketidakcocokan Nominal',
            self::UnmatchedDeposit => 'Setoran Tidak Teridentifikasi',
        };
    }
}
