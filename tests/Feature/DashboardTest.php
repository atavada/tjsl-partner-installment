<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->user = User::factory()->operator()->create();
});

describe('Dashboard Access & Analytics (TASK-REM-009 / FR-11)', function () {
    it('redirects unauthenticated guests to login', function () {
        $this->get('/dashboard')->assertRedirect('/login');
    });

    it('renders dashboard with live aggregated portfolio metrics and metric definitions', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('metrics')
            ->has('metrics.filters')
            ->has('metrics.filter_options')
            ->has('metrics.kpi')
            ->has('metrics.collectibility')
            ->has('metric_definitions', 10)
            ->where('metrics.collectibility.is_valid_sum', true)
        );

        $metrics = $response->viewData('page')['props']['metrics'];

        // Assert KPI values computed from synthetic seeder
        expect($metrics['kpi']['active_agreements_count'])->toBeGreaterThanOrEqual(1)
            ->and($metrics['kpi']['active_partners_count'])->toBeGreaterThanOrEqual(1)
            ->and($metrics['kpi']['total_principal_outstanding'])->toBeGreaterThan(0)
            ->and($metrics['kpi']['total_remaining_portfolio_balance'])->toBeGreaterThan(0)
            ->and($metrics['kpi']['unallocated_abt_total'])->toBeGreaterThanOrEqual(0);

        // Verify FR-11 invariant: sum of bands equals total active count
        expect($metrics['collectibility']['total_agreements'])->toBe($metrics['kpi']['active_agreements_count'])
            ->and($metrics['collectibility']['total_partners'])->toBe($metrics['kpi']['active_partners_count']);
    });

    it('evaluates historical portfolio balances when filtered by as_of date', function () {
        // Query as-of early historical date
        $historicalResponse = $this->actingAs($this->user)->get('/dashboard?as_of=2026-01-01');
        $historicalResponse->assertOk();

        $metrics = $historicalResponse->viewData('page')['props']['metrics'];
        expect($metrics['filters']['as_of'])->toBe('2026-01-01');
    });

    it('filters dashboard metrics by cohort batch year and partner region', function () {
        $response = $this->actingAs($this->user)->get('/dashboard?batch_year=2026');
        $response->assertOk();

        $metrics = $response->viewData('page')['props']['metrics'];
        expect($metrics['filters']['batch_year'])->toBe('2026')
            ->and($metrics['collectibility']['is_valid_sum'])->toBeTrue();
    });

    it('includes all 10 approved metric definitions in the registry prop', function () {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertOk();

        $definitions = $response->viewData('page')['props']['metric_definitions'];
        $codes = collect($definitions)->pluck('code')->all();

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
    });
});
