<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\CollectibilityStatus;
use App\Enums\SignatureSummary;
use App\Services\CollectibilityCalculationService;
use Carbon\CarbonInterface;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'partner_id',
        'agreement_number',
        'agreement_number_normalized',
        'batch_year',
        'business_group',
        'source_row_number',
        'tenor_months',
        'application_date',
        'contract_date',
        'effective_date',
        'loan_start_date',
        'first_due_date',
        'maturity_date',
        'principal_amount',
        'interest_amount',
        'admin_charge_amount',
        'other_charge_amount',
        'total_amount',
        'interest_rate_percent',
        'lifecycle_status',
        'legacy_lifecycle_status',
        'collectibility_status',
        'legacy_collectibility_status',
        'signing_status',
        'signature_summary',
        'legacy_signing_status',
        'provenance',
        'approved_source',
        'approved_by_id',
        'approved_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'tenor_months' => 'integer',
            'application_date' => 'date',
            'contract_date' => 'date',
            'effective_date' => 'date',
            'loan_start_date' => 'date',
            'first_due_date' => 'date',
            'maturity_date' => 'date',
            'principal_amount' => 'integer',
            'interest_amount' => 'integer',
            'admin_charge_amount' => 'integer',
            'other_charge_amount' => 'integer',
            'total_amount' => 'integer',
            'interest_rate_percent' => 'decimal:2',
            'source_row_number' => 'integer',
            'lifecycle_status' => AgreementLifecycleStatus::class,
            'collectibility_status' => CollectibilityStatus::class,
            'signing_status' => AgreementSigningStatus::class,
            'signature_summary' => SignatureSummary::class,
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<AgreementTransition, $this> */
    public function predecessorTransitions(): HasMany
    {
        return $this->hasMany(AgreementTransition::class, 'predecessor_id');
    }

    /** @return HasMany<AgreementTransition, $this> */
    public function successorTransitions(): HasMany
    {
        return $this->hasMany(AgreementTransition::class, 'successor_id');
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function successors(): BelongsToMany
    {
        return $this->belongsToMany(
            Agreement::class,
            'agreement_transitions',
            'predecessor_id',
            'successor_id'
        )->withPivot('transition_type', 'effective_date', 'version')->withTimestamps();
    }

    /** @return BelongsToMany<Agreement, $this> */
    public function predecessors(): BelongsToMany
    {
        return $this->belongsToMany(
            Agreement::class,
            'agreement_transitions',
            'successor_id',
            'predecessor_id'
        )->withPivot('transition_type', 'effective_date', 'version')->withTimestamps();
    }

    /** @return HasMany<AgreementDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AgreementDocument::class);
    }

    /** @return HasMany<InstallmentSchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(InstallmentSchedule::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<ReceivableAdjustment, $this> */
    public function receivableAdjustments(): HasMany
    {
        return $this->hasMany(ReceivableAdjustment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * Normalize agreement number: trim whitespace, uppercase.
     * Leading zeros, slashes, and punctuation are preserved.
     */
    public static function normalizeAgreementNumber(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_strtoupper(trim($value));
    }

    public function isPaidOff(): bool
    {
        return $this->lifecycle_status === AgreementLifecycleStatus::PaidOff;
    }

    public function isCompleted(): bool
    {
        return $this->lifecycle_status === AgreementLifecycleStatus::Completed;
    }

    public function isClosedByRescheduling(): bool
    {
        return $this->lifecycle_status === AgreementLifecycleStatus::ClosedByRescheduling;
    }

    /**
     * Calculate dynamic collectibility status and metrics as of a given date.
     *
     * @return array<string, mixed>
     */
    public function calculateCollectibility(?CarbonInterface $asOf = null): array
    {
        return app(CollectibilityCalculationService::class)->calculate($this, $asOf);
    }
}
