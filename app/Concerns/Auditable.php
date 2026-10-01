<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait Auditable
 *
 * Automatically records create and update audit events with delta payloads
 * and user/system actor attribution.
 *
 * @mixin Model
 *
 * @method static void created(\Closure|callable|array|string $callback)
 * @method static void updated(\Closure|callable|array|string $callback)
 */
trait Auditable
{
    protected ?string $auditReason = null;

    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            if (AuditService::$auditingDisabled) {
                return;
            }

            /** @var Auditable|Model $model */
            $reason = method_exists($model, 'getAuditReason') ? $model->getAuditReason() : null;

            app(AuditService::class)->logModelCreated($model, reason: $reason);
        });

        static::updated(function (Model $model): void {
            if (AuditService::$auditingDisabled) {
                return;
            }

            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if (empty($changes)) {
                return;
            }

            /** @var Auditable|Model $model */
            $reason = method_exists($model, 'getAuditReason') ? $model->getAuditReason() : null;

            app(AuditService::class)->logModelUpdated(
                model: $model,
                changes: $changes,
                original: $model->getOriginal(),
                reason: $reason,
            );
        });
    }

    public function setAuditReason(?string $reason): static
    {
        $this->auditReason = $reason;

        return $this;
    }

    public function getAuditReason(): ?string
    {
        return $this->auditReason;
    }
}
