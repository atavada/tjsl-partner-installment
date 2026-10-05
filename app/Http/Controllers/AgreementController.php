<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Enums\AgreementTransitionType;
use App\Enums\CollectibilityStatus;
use App\Enums\SignatureSummary;
use App\Http\Requests\RestructureAgreementRequest;
use App\Http\Requests\StoreAgreementRequest;
use App\Http\Resources\AgreementResource;
use App\Http\Resources\PartnerResource;
use App\Models\Agreement;
use App\Models\Partner;
use App\Services\AgreementDocumentService;
use App\Services\AgreementTransitionService;
use App\Services\AuditService;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
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
     * Show the form for creating a new agreement.
     */
    public function create(Request $request): InertiaResponse
    {
        Gate::authorize('create', Agreement::class);

        $partners = Partner::query()
            ->select(['id', 'name', 'partner_no_id'])
            ->orderBy('name')
            ->get();

        $selectedPartnerId = $request->query('partner_id');
        $selectedPartner = null;
        if ($selectedPartnerId) {
            $foundPartner = Partner::find($selectedPartnerId);
            if ($foundPartner) {
                $selectedPartner = (new PartnerResource($foundPartner))->resolve($request);
            }
        }

        return Inertia::render('Agreements/Create', [
            'partners' => $partners,
            'selectedPartner' => $selectedPartner,
            'selectedPartnerId' => $selectedPartnerId,
        ]);
    }

    /**
     * Show the form for creating a new agreement for a specific partner.
     */
    public function createForPartner(Request $request, Partner $partner): InertiaResponse
    {
        Gate::authorize('create', Agreement::class);

        return Inertia::render('Agreements/Create', [
            'partners' => [
                [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'partner_no_id' => $partner->partner_no_id,
                ],
            ],
            'selectedPartner' => (new PartnerResource($partner))->resolve($request),
            'selectedPartnerId' => $partner->id,
        ]);
    }

    /**
     * Store a newly created agreement (starts in Draft lifecycle status).
     */
    public function store(
        StoreAgreementRequest $request,
        AgreementDocumentService $documentService,
        AuditService $auditService
    ): JsonResponse|RedirectResponse {
        $partnerId = (string) $request->input('partner_id');
        $partner = Partner::findOrFail($partnerId);

        $principal = (int) $request->input('principal_amount');
        $interest = (int) ($request->input('interest_amount') ?? 0);
        $admin = (int) ($request->input('admin_charge_amount') ?? 0);
        $other = (int) ($request->input('other_charge_amount') ?? 0);
        $totalAmount = $principal + $interest + $admin + $other;

        $normalizedNumber = Agreement::normalizeAgreementNumber((string) $request->input('agreement_number'));

        $agreement = DB::transaction(function () use (
            $request,
            $partner,
            $principal,
            $interest,
            $admin,
            $other,
            $totalAmount,
            $normalizedNumber,
            $documentService,
            $auditService
        ) {
            $agreement = Agreement::create([
                'partner_id' => $partner->id,
                'agreement_number' => $request->input('agreement_number'),
                'agreement_number_normalized' => $normalizedNumber,
                'batch_year' => $request->input('batch_year'),
                'business_group' => $request->input('business_group'),
                'tenor_months' => (int) $request->input('tenor_months'),
                'application_date' => $request->input('application_date'),
                'contract_date' => $request->input('contract_date'),
                'effective_date' => $request->input('effective_date'),
                'loan_start_date' => $request->input('loan_start_date'),
                'first_due_date' => $request->input('first_due_date'),
                'maturity_date' => $request->input('maturity_date'),
                'principal_amount' => $principal,
                'interest_amount' => $interest,
                'admin_charge_amount' => $admin,
                'other_charge_amount' => $other,
                'total_amount' => $totalAmount,
                'interest_rate_percent' => $request->input('interest_rate_percent'),
                'lifecycle_status' => AgreementLifecycleStatus::Draft,
                'signing_status' => AgreementSigningStatus::NotPrepared,
                'signature_summary' => SignatureSummary::Unknown,
                'collectibility_status' => CollectibilityStatus::Unknown,
                'version' => 1,
            ]);

            if ($request->hasFile('document')) {
                $file = $request->file('document');
                $documentService->store(
                    agreement: $agreement,
                    file: $file,
                    originalFileName: $file->getClientOriginalName(),
                    mimeType: 'application/pdf',
                    documentType: 'contract',
                    notes: 'Dokumen kontrak awal saat pendaftaran',
                    uploader: $request->user(),
                );
            }

            $auditService->logModelCreated($agreement, $request->user(), 'Perjanjian baru dibuat (Draft)');

            return $agreement;
        });

        if ($request->wantsJson()) {
            return (new AgreementResource($agreement))->response()->setStatusCode(201);
        }

        return redirect()->route('agreements.show', [
            'partner' => $partner->id,
            'agreement' => $agreement->id,
        ])->with('success', 'Perjanjian draft berhasil dibuat.');
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

    /**
     * Show the agreement restructuring interface.
     */
    public function restructure(
        Request $request,
        Partner $partner,
        Agreement $agreement,
        BalanceService $balanceService
    ): InertiaResponse {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        Gate::authorize('restructure', $agreement);

        $balance = $balanceService->getBalance($agreement);

        return Inertia::render('Agreements/Restructure', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreement' => (new AgreementResource($agreement))->resolve($request),
            'balance' => $balance,
        ]);
    }

    /**
     * Process agreement restructuring and create successor agreement.
     */
    public function processRestructure(
        RestructureAgreementRequest $request,
        Partner $partner,
        Agreement $agreement,
        AgreementTransitionService $transitionService,
        AgreementDocumentService $documentService,
        AuditService $auditService
    ): JsonResponse|RedirectResponse {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        Gate::authorize('restructure', $agreement);

        $newPrincipal = (int) $request->input('approved_principal_amount');
        $newInterest = (int) ($request->input('approved_interest_amount') ?? 0);
        $newAdmin = (int) ($request->input('approved_admin_charge_amount') ?? 0);
        $newOther = (int) ($request->input('other_charge_amount') ?? 0);
        $newTotal = $newPrincipal + $newInterest + $newAdmin + $newOther;

        $normalizedNumber = Agreement::normalizeAgreementNumber((string) $request->input('successor_agreement_number'));

        $successor = DB::transaction(function () use (
            $request,
            $partner,
            $agreement,
            $newPrincipal,
            $newInterest,
            $newAdmin,
            $newOther,
            $newTotal,
            $normalizedNumber,
            $transitionService,
            $documentService,
            $auditService
        ) {
            $successor = Agreement::create([
                'partner_id' => $partner->id,
                'agreement_number' => $request->input('successor_agreement_number'),
                'agreement_number_normalized' => $normalizedNumber,
                'batch_year' => $agreement->batch_year,
                'business_group' => $agreement->business_group,
                'tenor_months' => (int) $request->input('tenor_months'),
                'effective_date' => $request->input('effective_date'),
                'loan_start_date' => $request->input('effective_date'),
                'first_due_date' => $request->input('first_due_date'),
                'maturity_date' => $request->input('maturity_date'),
                'principal_amount' => $newPrincipal,
                'interest_amount' => $newInterest,
                'admin_charge_amount' => $newAdmin,
                'other_charge_amount' => $newOther,
                'total_amount' => $newTotal,
                'interest_rate_percent' => $request->input('interest_rate_percent'),
                'lifecycle_status' => AgreementLifecycleStatus::Active,
                'signing_status' => AgreementSigningStatus::NotPrepared,
                'signature_summary' => SignatureSummary::Unknown,
                'collectibility_status' => CollectibilityStatus::Unknown,
                'version' => 1,
            ]);

            $transition = $transitionService->createTransition(
                predecessor: $agreement,
                successor: $successor,
                type: AgreementTransitionType::Rescheduling,
                attributes: [
                    'effective_date' => $request->input('effective_date'),
                    'reason' => $request->input('reason'),
                    'approved_principal_amount' => $newPrincipal,
                    'approved_interest_amount' => $newInterest,
                    'approved_admin_charge_amount' => $newAdmin,
                ],
                authorizer: $request->user(),
            );

            if ($request->hasFile('addendum_document')) {
                $file = $request->file('addendum_document');
                $documentService->store(
                    agreement: $successor,
                    file: $file,
                    originalFileName: $file->getClientOriginalName(),
                    mimeType: 'application/pdf',
                    documentType: 'addendum',
                    notes: 'Dokumen addendum restrukturisasi dari '.$agreement->agreement_number,
                    uploader: $request->user(),
                    transitionId: $transition->id,
                );
            }

            $auditService->log(
                action: 'agreement_restructured',
                target: $agreement,
                delta: [
                    'predecessor_id' => $agreement->id,
                    'successor_id' => $successor->id,
                    'transition_id' => $transition->id,
                    'reason' => $request->input('reason'),
                ],
                reason: $request->input('reason'),
                actor: $request->user(),
            );

            return $successor;
        });

        if ($request->wantsJson()) {
            return (new AgreementResource($successor))->response()->setStatusCode(201);
        }

        return redirect()->route('agreements.show', [
            'partner' => $partner->id,
            'agreement' => $successor->id,
        ])->with('success', 'Restrukturisasi perjanjian berhasil diproses. Perjanjian lama ditutup sebagai closed_by_rescheduling.');
    }
}
