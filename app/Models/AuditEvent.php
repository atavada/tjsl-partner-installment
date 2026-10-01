<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'correlation_id',
        'actor_id',
        'actor_type',
        'actor_identifier',
        'target_type',
        'target_id',
        'action',
        'delta',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // AuditEvent is strictly append-only: immutable, no updates or physical deletes (PRD §4)
        static::updating(function (): void {
            throw new LogicException('AuditEvent is immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('AuditEvent is immutable and cannot be deleted.');
        });
    }

    /**
     * Override update to prevent direct Eloquent updates.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('AuditEvent is immutable and cannot be updated.');
    }

    /**
     * Override delete to prevent direct Eloquent deletes.
     */
    public function delete(): ?bool
    {
        throw new LogicException('AuditEvent is immutable and cannot be deleted.');
    }

    /**
     * Authenticated user who performed the action, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Polymorphic target entity associated with the audit event.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target', 'target_type', 'target_id');
    }
}
