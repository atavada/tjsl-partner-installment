<?php

declare(strict_types=1);

use App\Models\MetricDefinition;
use Database\Seeders\MetricDefinitionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MetricDefinition Registry (PRD §4 & §9 Deliverable)', function () {
    it('seeds all 10 confirmed metrics from docs/metric-definitions.md', function () {
        $this->seed(MetricDefinitionSeeder::class);

        expect(MetricDefinition::count())->toBe(10);

        $codes = MetricDefinition::pluck('code')->all();
        expect($codes)->toContain(
            'remaining_principal',
            'remaining_charge',
            'total_remaining_balance',
            'lunas',
            'collectibility_label',
            'allocation_order',
            'months_paid',
            'months_remaining',
            'late_fee',
            'excess_amount'
        );

        // Check specific confirmed metric attributes
        $principal = MetricDefinition::where('code', 'remaining_principal')->first();
        expect($principal)->not->toBeNull()
            ->and($principal->name)->toBe('Remaining Principal')
            ->and($principal->version)->toBe('v1')
            ->and($principal->formula_expression)->toContain('remaining_P(as_of) = P_contract + adj_P(as_of) - paid_P(as_of)')
            ->and($principal->decision_ref)->toBe('DEC-008')
            ->and($principal->is_active)->toBeTrue()
            ->and($principal->approved_at)->not->toBeNull();

        $collectibility = MetricDefinition::where('code', 'collectibility_label')->first();
        expect($collectibility)->not->toBeNull()
            ->and($collectibility->formula_expression)->toContain('Lancar')
            ->and($collectibility->decision_ref)->toBe('DEC-007');

        $excess = MetricDefinition::where('code', 'excess_amount')->first();
        expect($excess)->not->toBeNull()
            ->and($excess->formula_expression)->toContain('partner_total_remaining')
            ->and($excess->decision_ref)->toBe('DEC-006, DEC-008');
    });

    it('enforces active and approved scopes correctly', function () {
        $this->seed(MetricDefinitionSeeder::class);

        expect(MetricDefinition::active()->count())->toBe(10);
        expect(MetricDefinition::approved()->count())->toBe(10);

        // Create inactive metric
        MetricDefinition::create([
            'code' => 'draft_metric',
            'name' => 'Draft Metric',
            'version' => 'v1',
            'formula_expression' => 'x = y',
            'is_active' => false,
        ]);

        expect(MetricDefinition::active()->count())->toBe(10);
        expect(MetricDefinition::count())->toBe(11);
    });

    it('prevents duplicate code and version pairs', function () {
        $this->seed(MetricDefinitionSeeder::class);

        expect(function () {
            MetricDefinition::create([
                'code' => 'remaining_principal',
                'name' => 'Duplicate Remaining Principal',
                'version' => 'v1',
                'formula_expression' => 'some formula',
            ]);
        })->toThrow(QueryException::class);
    });
});
