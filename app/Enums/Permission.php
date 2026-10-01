<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    // DEC-004 Sensitive field permissions (all masked by default)
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
    case PaymentStage = 'payment.stage';
    case PaymentPost = 'payment.post';
    case MatchPropose = 'match.propose';
    case AllocationApprove = 'allocation.approve';
    case AgreementActivate = 'agreement.activate';
    case PolicyDefine = 'policy.define';
    case UsersManage = 'users.manage';
    case ConfigManage = 'config.manage';
}
