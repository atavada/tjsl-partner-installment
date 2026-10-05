<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchPartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\PartnerSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PartnerController extends Controller
{
    /**
     * Display a listing of partners or search results.
     */
    public function index(SearchPartnerRequest $request, PartnerSearchService $searchService): InertiaResponse|AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Partner::class);

        $query = $request->validated('query');
        $type = $request->validated('type');
        $perPage = (int) ($request->validated('per_page') ?? 15);

        $paginator = $searchService->search($query, $type, $perPage, $request->user())->withQueryString();

        if ($request->wantsJson()) {
            return PartnerResource::collection($paginator);
        }

        return Inertia::render('Partners/Index', [
            'partners' => $paginator->through(
                fn (Partner $partner) => (new PartnerResource($partner))->resolve($request)
            ),
            'filters' => [
                'query' => $query,
                'type' => $type ?? 'no_id',
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Display the specified partner details.
     */
    public function show(Request $request, Partner $partner): InertiaResponse|PartnerResource
    {
        Gate::authorize('view', $partner);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');

        if ($request->wantsJson()) {
            return new PartnerResource($partner);
        }

        return Inertia::render('Partners/Show', [
            'partner' => (new PartnerResource($partner))->resolve($request),
        ]);
    }
}
