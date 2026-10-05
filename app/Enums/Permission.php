<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    // DEC-004 Sensitive field permissions (all masked by default for Viewer/Auditor)
    case NikReveal = 'nik.reveal';
    case PhoneReveal = 'phone.reveal';
    case AddressReveal = 'address.reveal';
    case VaReveal = 'va.reveal';
    case DocumentView = 'document.view';
    case DocumentDownload = 'document.download';
    case SensitiveExport = 'sensitive.export';

    // DEC-009 Action-level capabilities (grants default empty)
    case PartnerView = 'partner.view';
    case PartnerCreate = 'partner.create';
    case PartnerUpdate = 'partner.update';
    case AgreementView = 'agreement.view';
    case AgreementCreate = 'agreement.create';
    case AgreementRestructure = 'agreement.restructure';
    case DocumentUpload = 'document.upload';
    case PaymentStage = 'payment.stage';
    case PaymentPost = 'payment.post';
    case MatchPropose = 'match.propose';
    case AgreementActivate = 'agreement.activate';
    case PolicyDefine = 'policy.define';
    case UsersManage = 'users.manage';
    case ConfigManage = 'config.manage';

    /**
     * Determine whether this permission governs sensitive field or document data.
     *
     * Per DEC-004: All roles except Viewer (Auditor) may access sensitive data
     * (NIK, phone, address, VA, documents) by default. Scoped export (SensitiveExport)
     * requires explicit grant per DEC-009.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::NikReveal,
            self::PhoneReveal,
            self::AddressReveal,
            self::VaReveal,
            self::DocumentView,
            self::DocumentDownload => true,
            default => false,
        };
    }
}
