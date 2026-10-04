<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationInstallmentLine extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'payment_allocation_id',
        'installment_schedule_id',
        'principal_amount',
        'interest_amount',
        'admin_charge_amount',
        'other_charge_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'integer',
            'interest_amount' => 'integer',
            'admin_charge_amount' => 'integer',
            'other_charge_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<PaymentAllocation, $this> */
    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class);
    }

    /** @return BelongsTo<InstallmentSchedule, $this> */
    public function installmentSchedule(): BelongsTo
    {
        return $this->belongsTo(InstallmentSchedule::class);
    }
}
