<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementSigningStatus: string
{
    case NotPrepared = 'not_prepared';
    case Draft = 'draft';
    case AwaitingPartnerSignature = 'awaiting_partner_signature';
    case AwaitingCompanySignature = 'awaiting_company_signature';
    case Signed = 'signed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::NotPrepared => 'Belum Dibuat',
            self::Draft => 'Draft Dokumen',
            self::AwaitingPartnerSignature => 'Menunggu TTD Mitra',
            self::AwaitingCompanySignature => 'Menunggu TTD Perusahaan',
            self::Signed => 'Sudah Ditandatangani',
            self::Unknown => 'Tidak Diketahui',
        };
    }
}
