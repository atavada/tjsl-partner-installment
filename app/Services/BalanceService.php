<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementLifecycleStatus;
use App\Models\Agreement;
use Carbon\CarbonInterface;

class BalanceService
{
    /**
     * Return balance components for an agreement as of a given date.
     *
     * Per DEC-008, balance calculation is deferred and unapproved.
     * Always returns 'unverified' status and components, never a plausible zero.
     * Draft agreements create no debt per PRD FR-02.
     *
     * @return array{
     *     agreement_id: string,
     *     status: string,
     *     label: string,
     *     as_of: string|null,
     *     policy_version: string|null,
     *     is_draft: bool,
     *     creates_debt: bool,
     *     principal_remaining: string,
     *     interest_remaining: string,
     *     admin_charge_remaining: string,
     *     other_charge_remaining: string,
     *     total_remaining: string,
     *     included_event_ids: list<string>,
     *     warnings: list<string>
     * }
     */
    public function getBalance(
        Agreement|string $agreement,
        ?CarbonInterface $asOf = null,
        ?string $approvedPolicyVersion = null
    ): array {
        $agreementModel = $agreement instanceof Agreement ? $agreement : Agreement::find($agreement);
        $agreementId = $agreementModel instanceof Agreement ? (string) $agreementModel->id : (string) $agreement;

        $isDraft = $agreementModel instanceof Agreement
            && $agreementModel->lifecycle_status === AgreementLifecycleStatus::Draft;

        if ($isDraft) {
            return [
                'agreement_id' => $agreementId,
                'status' => 'draft',
                'label' => 'Draft (Tidak Menimbulkan Piutang)',
                'as_of' => $asOf?->toIso8601String(),
                'policy_version' => $approvedPolicyVersion,
                'is_draft' => true,
                'creates_debt' => false,
                'principal_remaining' => 'unverified',
                'interest_remaining' => 'unverified',
                'admin_charge_remaining' => 'unverified',
                'other_charge_remaining' => 'unverified',
                'total_remaining' => 'unverified',
                'included_event_ids' => [],
                'warnings' => [
                    'Perjanjian draft belum aktif dan tidak menimbulkan kewajiban piutang (PRD FR-02).',
                ],
            ];
        }

        return [
            'agreement_id' => $agreementId,
            'status' => 'unverified',
            'label' => 'Belum Terverifikasi',
            'as_of' => $asOf?->toIso8601String(),
            'policy_version' => $approvedPolicyVersion,
            'is_draft' => false,
            'creates_debt' => true,
            'principal_remaining' => 'unverified',
            'interest_remaining' => 'unverified',
            'admin_charge_remaining' => 'unverified',
            'other_charge_remaining' => 'unverified',
            'total_remaining' => 'unverified',
            'included_event_ids' => [],
            'warnings' => [
                'Perhitungan saldo piutang belum disetujui (DEC-008). Nilai saldo tidak tersedia.',
            ],
        ];
    }
}
