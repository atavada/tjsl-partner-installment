<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgreementDocument;
use App\Models\AuditEvent;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class AuditService
{
    protected ?string $correlationId = null;

    public static bool $auditingDisabled = false;

    public function __construct(
        protected MaskingService $maskingService,
    ) {}

    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    public function getCorrelationId(): string
    {
        if ($this->correlationId !== null) {
            return $this->correlationId;
        }

        $contextId = Context::get('correlation_id');
        if (is_string($contextId) && $contextId !== '') {
            return $contextId;
        }

        if (app()->bound('request')) {
            $reqAttr = request()?->attributes?->get('correlation_id');
            if (is_string($reqAttr) && $reqAttr !== '') {
                return $reqAttr;
            }
        }

        $newId = (string) Str::uuid();
        $this->correlationId = $newId;

        return $newId;
    }

    /**
     * Temporarily disable auditing for bulk operations or test fixtures.
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = static::$auditingDisabled;
        static::$auditingDisabled = true;

        try {
            return $callback();
        } finally {
            static::$auditingDisabled = $previous;
        }
    }

    /**
     * Resolve actor attributes based on explicit actor, auth state, or system execution.
     *
     * @return array{actor_id: int|null, actor_type: string, actor_identifier: string|null}
     */
    public function resolveActor(?User $actor = null): array
    {
        if ($actor !== null) {
            return [
                'actor_id' => $actor->id,
                'actor_type' => 'user',
                'actor_identifier' => $actor->email,
            ];
        }

        if (Auth::check()) {
            /** @var User $currentUser */
            $currentUser = Auth::user();

            return [
                'actor_id' => $currentUser->id,
                'actor_type' => 'user',
                'actor_identifier' => $currentUser->email,
            ];
        }

        if (app()->runningInConsole()) {
            return [
                'actor_id' => null,
                'actor_type' => 'system',
                'actor_identifier' => 'cli/console',
            ];
        }

        $ip = app()->bound('request') ? request()?->ip() : null;

        return [
            'actor_id' => null,
            'actor_type' => 'guest',
            'actor_identifier' => $ip ?? 'system',
        ];
    }

    /**
     * Recursively mask sensitive fields in audit delta payloads (PRD §4, §8).
     *
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    public function maskDelta(array $delta): array
    {
        $masked = [];

        foreach ($delta as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (is_array($value)) {
                // If it's a diff array with old/new
                if (array_key_exists('old', $value) || array_key_exists('new', $value)) {
                    $masked[$key] = [
                        'old' => $this->maskValueForKey($lowerKey, $value['old'] ?? null),
                        'new' => $this->maskValueForKey($lowerKey, $value['new'] ?? null),
                    ];
                } else {
                    $masked[$key] = $this->maskDelta($value);
                }
            } else {
                $masked[$key] = $this->maskValueForKey($lowerKey, $value);
            }
        }

        return $masked;
    }

    /**
     * Apply specific field masking rules based on field name.
     */
    protected function maskValueForKey(string $key, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return match ($key) {
            'nik', 'nik_normalized' => $this->maskingService->maskNik($value),
            'phone' => $this->maskingService->maskPhone($value),
            'address' => $this->maskingService->maskAddress($value),
            'va_number', 'va_number_normalized', 'payer_va' => $this->maskingService->maskVaNumber($value),
            'password', 'remember_token', 'token', 'secret' => '[REDACTED]',
            default => $value,
        };
    }

    /**
     * Core append-only audit event logging method.
     *
     * @param  array<string, mixed>|null  $delta
     */
    public function log(
        string $action,
        ?Model $target = null,
        ?array $delta = null,
        ?string $reason = null,
        ?User $actor = null,
    ): ?AuditEvent {
        if (static::$auditingDisabled) {
            return null;
        }

        $actorInfo = $this->resolveActor($actor);
        $correlationId = $this->getCorrelationId();

        $ipAddress = app()->bound('request') ? request()?->ip() : null;
        $userAgent = app()->bound('request') ? request()?->userAgent() : null;

        $maskedDelta = $delta !== null ? $this->maskDelta($delta) : null;

        return AuditEvent::create([
            'correlation_id' => $correlationId,
            'actor_id' => $actorInfo['actor_id'],
            'actor_type' => $actorInfo['actor_type'],
            'actor_identifier' => $actorInfo['actor_identifier'],
            'target_type' => $target ? get_class($target) : null,
            'target_id' => $target ? (string) $target->getKey() : null,
            'action' => $action,
            'delta' => $maskedDelta,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Log model creation event.
     */
    public function logModelCreated(Model $model, ?User $actor = null, ?string $reason = null): ?AuditEvent
    {
        $attributes = $model->getAttributes();
        unset($attributes['updated_at']);

        return $this->log(
            action: 'create',
            target: $model,
            delta: $attributes,
            reason: $reason,
            actor: $actor,
        );
    }

    /**
     * Log model update event with diff.
     *
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $original
     */
    public function logModelUpdated(
        Model $model,
        array $changes,
        array $original,
        ?User $actor = null,
        ?string $reason = null,
    ): ?AuditEvent {
        unset($changes['updated_at']);

        if (empty($changes)) {
            return null;
        }

        $diff = [];
        foreach ($changes as $key => $newValue) {
            $diff[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        return $this->log(
            action: 'update',
            target: $model,
            delta: $diff,
            reason: $reason,
            actor: $actor,
        );
    }

    /**
     * Log payment reversal linking compensating allocation to original allocation.
     */
    public function logReversal(
        PaymentAllocation $reversal,
        PaymentAllocation $original,
        string $reason,
        User $actor,
    ): ?AuditEvent {
        return $this->log(
            action: 'reversal',
            target: $reversal,
            delta: [
                'reversal_allocation_id' => $reversal->id,
                'original_allocation_id' => $original->id,
                'agreement_id' => $reversal->agreement_id,
                'bank_transaction_id' => $reversal->bank_transaction_id,
                'principal_amount' => $reversal->principal_amount,
                'interest_amount' => $reversal->interest_amount,
                'admin_charge_amount' => $reversal->admin_charge_amount,
                'other_charge_amount' => $reversal->other_charge_amount,
                'total_amount' => $reversal->total_amount,
                'reason' => $reason,
            ],
            reason: $reason,
            actor: $actor,
        );
    }

    /**
     * Log authorization failure / denied attempt.
     *
     * @param  array<string, mixed>|null  $delta
     */
    public function logAuthFailure(
        string $action,
        ?Model $target = null,
        ?array $delta = null,
        ?string $reason = null,
        ?User $actor = null,
    ): ?AuditEvent {
        return $this->log(
            action: $action,
            target: $target,
            delta: $delta,
            reason: $reason,
            actor: $actor,
        );
    }

    /**
     * Log document access (PRD §4, FR-06).
     */
    public function logDocumentAccess(
        AgreementDocument $document,
        User $actor,
        string $action = 'document_access',
    ): ?AuditEvent {
        return $this->log(
            action: $action,
            target: $document,
            delta: [
                'agreement_id' => $document->agreement_id,
                'file_name' => $document->file_name,
                'checksum_sha256' => $document->checksum_sha256,
                'document_type' => $document->document_type,
            ],
            reason: 'Document access by user',
            actor: $actor,
        );
    }

    /**
     * Log data export with explicit scope and count (PRD §4, FR-06, §8).
     */
    public function logExport(
        string $scope,
        int $count,
        ?User $actor = null,
        ?string $reason = null,
    ): ?AuditEvent {
        return $this->log(
            action: 'export',
            target: null,
            delta: [
                'scope' => $scope,
                'record_count' => $count,
            ],
            reason: $reason ?? "Export data for scope [{$scope}]",
            actor: $actor,
        );
    }
}
