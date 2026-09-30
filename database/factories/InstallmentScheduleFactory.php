<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\InstallmentSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentSchedule>
 */
class InstallmentScheduleFactory extends Factory
{
    protected $model = InstallmentSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principalDue = 500_000;
        $interestDue = 30_000;
        $adminChargeDue = 5_000;
        $totalDue = $principalDue + $interestDue + $adminChargeDue;

        return [
            'agreement_id' => Agreement::factory(),
            'installment_number' => 1,
            'due_date' => '2026-03-01',
            'principal_due' => $principalDue,
            'interest_due' => $interestDue,
            'admin_charge_due' => $adminChargeDue,
            'other_charge_due' => 0,
            'total_due' => $totalDue,
            'principal_paid' => 0,
            'interest_paid' => 0,
            'admin_charge_paid' => 0,
            'other_charge_paid' => 0,
            'total_paid' => 0,
            'status' => 'pending',
            'policy_version' => null,
            'is_calculated' => false,
            'version' => 1,
        ];
    }
}
