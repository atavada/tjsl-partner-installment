<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MetricDefinition;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    /**
     * Display the financial dashboard and portfolio risk analytics.
     */
    public function index(Request $request, DashboardService $dashboardService): InertiaResponse
    {
        $asOfInput = $request->query('as_of');
        $asOf = $asOfInput ? Carbon::parse((string) $asOfInput) : null;
        $batchYear = $request->query('batch_year') ? (string) $request->query('batch_year') : null;
        $region = $request->query('region') ? (string) $request->query('region') : null;

        $metrics = $dashboardService->getPortfolioMetrics($asOf, $batchYear, $region);

        $metricDefinitions = MetricDefinition::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return Inertia::render('dashboard', [
            'metrics' => $metrics,
            'metric_definitions' => $metricDefinitions,
        ]);
    }
}
