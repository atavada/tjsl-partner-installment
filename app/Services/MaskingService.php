<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\Partner;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MaskingService
{
    public function maskNik(?string $nik): ?string
    {
        if ($nik === null || $nik === '') {
            return null;
        }

        $length = strlen($nik);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($nik, 0, 2).str_repeat('*', $length - 6).substr($nik, -4);
    }

    public function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $length = strlen($phone);
        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 3).str_repeat('*', $length - 5).substr($phone, -2);
    }

    public function maskAddress(?string $address): ?string
    {
        if ($address === null || $address === '') {
            return null;
        }

        if (strlen($address) <= 4) {
            return '***';
        }

        return substr($address, 0, 4).str_repeat('*', min(20, strlen($address) - 4));
    }

    public function maskVaNumber(?string $vaNumber): ?string
    {
        if ($vaNumber === null || $vaNumber === '') {
            return null;
        }

        $length = strlen($vaNumber);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($vaNumber, 0, 4).str_repeat('*', $length - 8).substr($vaNumber, -4);
    }

    /**
     * Mask sensitive partner fields based on viewer role (DEC-004).
     * Non-viewer roles see fields unmasked; Viewer (Auditor) or guest sees masked fields.
     *
     * @return array<string, mixed>
     */
    public function maskPartner(Partner $partner, ?User $viewer = null): array
    {
        $canRevealNik = $viewer !== null && Gate::forUser($viewer)->allows('revealNik', $partner);
        $canRevealPhone = $viewer !== null && Gate::forUser($viewer)->allows('revealPhone', $partner);
        $canRevealAddress = $viewer !== null && Gate::forUser($viewer)->allows('revealAddress', $partner);

        return [
            'id' => $partner->id,
            'partner_no_id' => $partner->partner_no_id,
            'name' => $partner->name,
            'nik' => $canRevealNik ? $partner->nik : $this->maskNik($partner->nik),
            'phone' => $canRevealPhone ? $partner->phone : $this->maskPhone($partner->phone),
            'address' => $canRevealAddress ? $partner->address : $this->maskAddress($partner->address),
            'business_type' => $partner->business_type,
            'region' => $partner->region,
            'verification_state' => $partner->verification_state,
            'version' => $partner->version,
            'is_masked' => ! ($canRevealNik && $canRevealPhone && $canRevealAddress),
        ];
    }

    /**
     * Mask virtual account number based on viewer role (DEC-004).
     * Non-viewer roles see VA unmasked; Viewer (Auditor) or guest sees masked VA.
     *
     * @return array<string, mixed>
     */
    public function maskVirtualAccount(VirtualAccount $va, ?User $viewer = null): array
    {
        $canRevealVa = $viewer !== null && Gate::forUser($viewer)->allows('revealVaNumber', $va);

        return [
            'id' => $va->id,
            'partner_id' => $va->partner_id,
            'va_number' => $canRevealVa ? $va->va_number : $this->maskVaNumber($va->va_number),
            'provider' => $va->provider,
            'valid_from' => $va->valid_from?->toDateString(),
            'valid_until' => $va->valid_until?->toDateString(),
            'evidence' => $va->evidence,
            'version' => $va->version,
            'is_masked' => ! $canRevealVa,
        ];
    }

    /**
     * Reveal a sensitive field with authorization check and audit logging without raw values (DEC-004).
     *
     * @throws AuthorizationException
     */
    public function reveal(User $actor, Model $target, string $field, string $purpose): string
    {
        $permission = match ($field) {
            'nik' => Permission::NikReveal,
            'phone' => Permission::PhoneReveal,
            'address' => Permission::AddressReveal,
            'va_number' => Permission::VaReveal,
            default => throw new \InvalidArgumentException("Field [{$field}] is not unmaskable."),
        };

        $allowed = Gate::forUser($actor)->allows($permission->value, $target);

        // DEC-004: Log actor, purpose, target, time WITHOUT logging revealed values
        Log::info('Sensitive field unmask attempt', [
            'event' => 'sensitive_field_unmask_attempt',
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role->value,
            'target_type' => get_class($target),
            'target_id' => (string) $target->getKey(),
            'field' => $field,
            'permission' => $permission->value,
            'purpose' => $purpose,
            'allowed' => $allowed,
            'timestamp' => now()->toIso8601String(),
        ]);

        if (! $allowed) {
            if (app()->bound(AuditService::class)) {
                app(AuditService::class)->logAuthFailure(
                    action: 'unauthorized_field_access_denied',
                    target: $target,
                    delta: [
                        'field' => $field,
                        'permission' => $permission->value,
                        'purpose' => $purpose,
                    ],
                    reason: "Unauthorized attempt to reveal sensitive field [{$field}].",
                    actor: $actor,
                );
            }

            throw new AuthorizationException("Access denied for sensitive field reveal: [{$field}].");
        }

        if (app()->bound(AuditService::class)) {
            app(AuditService::class)->log(
                action: 'sensitive_field_revealed',
                target: $target,
                delta: [
                    'field' => $field,
                    'permission' => $permission->value,
                    'purpose' => $purpose,
                ],
                reason: "Sensitive field [{$field}] unmasked for purpose: {$purpose}",
                actor: $actor,
            );
        }

        return (string) $target->getAttribute($field);
    }
}
