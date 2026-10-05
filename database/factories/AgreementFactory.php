<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\CollectibilityStatus;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\Partner;
use App\Services\ScheduleGeneratorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    protected $model = Agreement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (string) $this->faker->numberBetween(2020, 2026);
        $num = str_pad((string) $this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);
        $agreementNumber = "{$num}/SP-TJSL/{$year}";

        $principal = $this->faker->numberBetween(5, 50) * 1_000_000;
        $interest = (int) ($principal * 0.06);
        $admin = 100_000;
        $total = $principal + $interest + $admin;

        return [
            'partner_id' => Partner::factory(),
            'agreement_number' => $agreementNumber,
            'agreement_number_normalized' => Agreement::normalizeAgreementNumber($agreementNumber),
            'batch_year' => $year,
            'business_group' => $this->faker->randomElement(['Kelompok Tani Makmur', 'Sentra Batik Jaya', 'Koperasi Nelayan Sejahtera']),
            'source_row_number' => $this->faker->numberBetween(1, 5000),
            'tenor_months' => 24,
            'application_date' => "{$year}-01-10",
            'contract_date' => "{$year}-01-15",
            'effective_date' => "{$year}-02-01",
            'loan_start_date' => "{$year}-01-15",
            'first_due_date' => "{$year}-02-01",
            'maturity_date' => ((int) $year + 2).'-01-31',
            'principal_amount' => $principal,
            'interest_amount' => $interest,
            'admin_charge_amount' => $admin,
            'other_charge_amount' => 0,
            'total_amount' => $total,
            'interest_rate_percent' => '6.00',
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'legacy_lifecycle_status' => 'Draft',
            'collectibility_status' => CollectibilityStatus::Unknown,
            'legacy_collectibility_status' => null,
            'signing_status' => AgreementSigningStatus::NotPrepared,
            'signature_summary' => SignatureSummary::Unknown,
            'legacy_signing_status' => null,
            'provenance' => 'synthetic_factory',
            'approved_source' => null,
            'approved_by_id' => null,
            'approved_at' => null,
            'version' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => AgreementLifecycleStatus::Active,
            'legacy_lifecycle_status' => 'Aktif',
            'collectibility_status' => CollectibilityStatus::Lancar,
            'signing_status' => AgreementSigningStatus::Signed,
            'signature_summary' => SignatureSummary::Signed,
        ]);
    }

    public function paidOff(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => AgreementLifecycleStatus::PaidOff,
            'legacy_lifecycle_status' => 'Lunas',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => AgreementLifecycleStatus::Completed,
            'legacy_lifecycle_status' => 'Selesai',
        ]);
    }

    public function closedByRescheduling(): static
    {
        return $this->state(fn () => [
            'lifecycle_status' => AgreementLifecycleStatus::ClosedByRescheduling,
            'legacy_lifecycle_status' => 'Ditutup Rescheduling',
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn () => [
            'signing_status' => AgreementSigningStatus::Signed,
            'signature_summary' => SignatureSummary::Signed,
        ]);
    }

    public function withGeneratedSchedules(): static
    {
        return $this->afterCreating(function (Agreement $agreement): void {
            app(ScheduleGeneratorService::class)->generateForAgreement($agreement, persist: true);
        });
    }
}
