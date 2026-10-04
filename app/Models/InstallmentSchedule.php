<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\InstallmentScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends Model
{
    /** @use HasFactory<InstallmentScheduleFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'agreement_id',
        'installment_number',
        'due_date',
        'principal_due',
        'interest_due',
        'admin_charge_due',
        'other_charge_due',
        'total_due',
        'principal_paid',
        'interest_paid',
        'admin_charge_paid',
        'other_charge_paid',
        'total_paid',
        'status',
        'policy_version',
        'is_calculated',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'principal_due' => 'integer',
            'interest_due' => 'integer',
            'admin_charge_due' => 'integer',
            'other_charge_due' => 'integer',
            'total_due' => 'integer',
            'principal_paid' => 'integer',
            'interest_paid' => 'integer',
            'admin_charge_paid' => 'integer',
            'other_charge_paid' => 'integer',
            'total_paid' => 'integer',
            'is_calculated' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Compute total outstanding balance for this installment schedule row.
     *
     * Implements DEC-008 (RESOLVED IN PART 2026-10-03):
     * outstanding = total_due - total_paid.
     *
     * Note: allocation-to-installment linking depends on DP-6 (FIMPL-007, OPEN).
     * Schedule generation depends on DP-3 / DP-4 (OPEN).
     *
     * @return int Total outstanding amount in Rupiah.
     */
    public function calculateOutstanding(): int
    {
        return (int) $this->total_due - (int) $this->total_paid;
    }

    /**
     * Compute component outstanding breakdown for this installment.
     *
     * @return array{
     *     principal: int,
     *     interest: int,
     *     admin_charge: int,
     *     other_charge: int,
     *     total: int
     * }
     */
    public function calculateComponentOutstanding(): array
    {
        return [
            'principal' => (int) $this->principal_due - (int) $this->principal_paid,
            'interest' => (int) $this->interest_due - (int) $this->interest_paid,
            'admin_charge' => (int) $this->admin_charge_due - (int) $this->admin_charge_paid,
            'other_charge' => (int) $this->other_charge_due - (int) $this->other_charge_paid,
            'total' => (int) $this->total_due - (int) $this->total_paid,
        ];
    }
}
