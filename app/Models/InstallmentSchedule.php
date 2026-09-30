<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\NotApprovedException;
use Database\Factories\InstallmentScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentSchedule extends Model
{
    /** @use HasFactory<InstallmentScheduleFactory> */
    use HasFactory, HasUuids;

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
     * DEC-008: Balance calculation blocked.
     */
    public function calculateOutstanding(): void
    {
        throw NotApprovedException::forBalanceCalculation();
    }
}
