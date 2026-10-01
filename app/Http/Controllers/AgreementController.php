<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AgreementResource;
use App\Http\Resources\PartnerResource;
use App\Models\Agreement;
use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AgreementController extends Controller
{
    /**
     * Display a listing of agreements for the specified partner.
     */
    public function index(Request $request, Partner $partner): InertiaResponse|AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Agreement::class);
        Gate::authorize('view', $partner);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');

        $agreements = $partner->agreements()
            ->with([
                'documents',
                'predecessors',
                'successors',
            ])
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->get();

        if ($request->wantsJson()) {
            return AgreementResource::collection($agreements);
        }

        return Inertia::render('Agreements/Index', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreements' => AgreementResource::collection($agreements)->resolve($request),
        ]);
    }

    /**
     * Display the specified agreement timeline and detail.
     */
    public function show(Request $request, Partner $partner, Agreement $agreement): InertiaResponse|AgreementResource
    {
        // Enforce agreement belongs to partner
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        Gate::authorize('view', $agreement);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');

        $agreement->load([
            'partner',
            'documents',
            'predecessors',
            'successors',
            'predecessorTransitions.approvedBy',
            'predecessorTransitions.predecessor',
            'predecessorTransitions.successor',
            'successorTransitions.approvedBy',
            'successorTransitions.predecessor',
            'successorTransitions.successor',
            'approvedBy',
        ]);

        if ($request->wantsJson()) {
            return new AgreementResource($agreement);
        }

        return Inertia::render('Agreements/Show', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreement' => (new AgreementResource($agreement))->resolve($request),
        ]);
    }
}
