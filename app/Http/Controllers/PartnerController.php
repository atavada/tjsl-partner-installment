<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FundLotType;
use App\Enums\Permission;
use App\Http\Requests\SearchPartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Agreement;
use App\Models\FundLot;
use App\Models\Partner;
use App\Services\BalanceService;
use App\Services\CollectibilityCalculationService;
use App\Services\MaskingService;
use App\Services\PartnerSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
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
     * Display the specified partner details with complete receivable breakdown.
     *
     * Implements PRD FR-05 (Receivable detail drill-down) and Finding #23.
     */
    public function show(
        Request $request,
        Partner $partner,
        BalanceService $balanceService,
        CollectibilityCalculationService $collectibilityService
    ): InertiaResponse|JsonResponse {
        Gate::authorize('view', $partner);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');

        $asOfInput = $request->query('as_of');
        $asOf = $asOfInput ? Carbon::parse((string) $asOfInput) : Carbon::now();
        $canRevealVa = (bool) $request->user()?->can(Permission::VaReveal->value);
        $maskingService = app(MaskingService::class);

        $agreements = Agreement::query()
            ->where('partner_id', $partner->id)
            ->with([
                'documents',
                'schedules' => fn ($q) => $q->orderBy('installment_number'),
                'paymentAllocations' => fn ($q) => $q->with(['bankTransaction', 'approvedBy'])->orderByDesc('effective_date'),
                'receivableAdjustments' => fn ($q) => $q->with('approvedBy')->orderByDesc('effective_date'),
            ])
            ->orderBy('created_at')
            ->get();

        $contractP = 0;
        $contractC = 0;
        $contractTotal = 0;
        $paidP = 0;
        $paidC = 0;
        $paidTotal = 0;
        $adjP = 0;
        $adjC = 0;
        $adjTotal = 0;
        $remainingP = 0;
        $remainingC = 0;
        $remainingTotal = 0;
        $activeAgreementsCount = 0;
        $hasException = false;
        $rollupWarnings = [];

        $agreementsDetail = [];

        foreach ($agreements as $agreement) {
            $balance = $balanceService->getBalance($agreement, $asOf);
            $collectibility = $collectibilityService->calculate($agreement, $asOf);

            if ($agreement->isActive()) {
                $activeAgreementsCount++;
                $contractP += (int) $balance['contract_principal'];
                $contractC += (int) $balance['contract_charge'];
                $contractTotal += (int) $balance['contract_total'];
                $paidP += (int) $balance['paid_principal'];
                $paidC += (int) $balance['paid_charge'];
                $paidTotal += (int) $balance['paid_total'];
                $adjP += (int) $balance['adjustment_principal'];
                $adjC += (int) $balance['adjustment_charge'];
                $adjTotal += (int) $balance['adjustment_total'];

                if (is_numeric($balance['principal_remaining'])) {
                    $remainingP += (int) $balance['principal_remaining'];
                }
                if (is_numeric($balance['charge_remaining'])) {
                    $remainingC += (int) $balance['charge_remaining'];
                }
                if (is_numeric($balance['total_remaining'])) {
                    $remainingTotal += (int) $balance['total_remaining'];
                }
            }

            if (! empty($balance['warnings'])) {
                $rollupWarnings = array_merge($rollupWarnings, $balance['warnings']);
            }
            if ($balance['status'] === 'exception') {
                $hasException = true;
            }

            // Map payment allocations with bank transaction source coordinates
            $allocationsDetail = $agreement->paymentAllocations->map(function ($alloc) use ($canRevealVa, $maskingService) {
                $bankTxn = $alloc->bankTransaction;

                return [
                    'id' => (string) $alloc->id,
                    'effective_date' => $alloc->effective_date?->toDateString(),
                    'period' => $alloc->period,
                    'principal_amount' => (int) $alloc->principal_amount,
                    'interest_amount' => (int) $alloc->interest_amount,
                    'admin_charge_amount' => (int) $alloc->admin_charge_amount,
                    'other_charge_amount' => (int) $alloc->other_charge_amount,
                    'total_amount' => (int) $alloc->total_amount,
                    'state' => $alloc->state?->value,
                    'evidence' => $alloc->evidence,
                    'idempotency_key' => $alloc->idempotency_key,
                    'approved_source' => $alloc->approved_source,
                    'bank_transaction' => $bankTxn ? [
                        'id' => (string) $bankTxn->id,
                        'reference' => $bankTxn->reference,
                        'reference_normalized' => $bankTxn->reference_normalized,
                        'transaction_datetime' => $bankTxn->transaction_datetime?->toIso8601String(),
                        'amount' => (int) $bankTxn->amount,
                        'payer_name' => $bankTxn->payer_name,
                        'payer_va' => $canRevealVa ? $bankTxn->payer_va : $maskingService->maskVa($bankTxn->payer_va),
                        'is_va_masked' => ! $canRevealVa,
                        'source' => $bankTxn->source,
                        'source_row_identifier' => $bankTxn->source_row_identifier,
                        'source_row_number' => $bankTxn->source_row_number ?? null,
                        'provenance' => $bankTxn->provenance,
                    ] : null,
                    'approved_by' => $alloc->approvedBy ? [
                        'id' => $alloc->approvedBy->id,
                        'name' => $alloc->approvedBy->name,
                    ] : null,
                ];
            })->values()->all();

            // Map receivable adjustments
            $adjustmentsDetail = $agreement->receivableAdjustments->map(function ($adj) {
                return [
                    'id' => (string) $adj->id,
                    'adjustment_type' => $adj->adjustment_type,
                    'principal_amount' => (int) $adj->principal_amount,
                    'interest_amount' => (int) $adj->interest_amount,
                    'admin_charge_amount' => (int) $adj->admin_charge_amount,
                    'other_charge_amount' => (int) $adj->other_charge_amount,
                    'total_amount' => (int) $adj->total_amount,
                    'effective_date' => $adj->effective_date?->toDateString(),
                    'reason' => $adj->reason,
                    'evidence' => $adj->evidence,
                    'state' => $adj->state?->value,
                    'approved_at' => $adj->approved_at?->toIso8601String(),
                    'approved_by' => $adj->approvedBy ? [
                        'id' => $adj->approvedBy->id,
                        'name' => $adj->approvedBy->name,
                    ] : null,
                ];
            })->values()->all();

            // Map installment schedules
            $schedulesDetail = $agreement->schedules->map(function ($sched) {
                return [
                    'id' => (string) $sched->id,
                    'installment_number' => (int) $sched->installment_number,
                    'due_date' => (string) $sched->due_date,
                    'principal_due' => (int) $sched->principal_due,
                    'interest_due' => (int) $sched->interest_due,
                    'admin_charge_due' => (int) $sched->admin_charge_due,
                    'other_charge_due' => (int) $sched->other_charge_due,
                    'total_due' => (int) $sched->total_due,
                    'principal_paid' => (int) $sched->principal_paid,
                    'interest_paid' => (int) $sched->interest_paid,
                    'admin_charge_paid' => (int) $sched->admin_charge_paid,
                    'other_charge_paid' => (int) $sched->other_charge_paid,
                    'total_paid' => (int) $sched->total_paid,
                    'outstanding' => (int) max(0, $sched->total_due - $sched->total_paid),
                    'status' => (string) $sched->status,
                ];
            })->values()->all();

            $agreementsDetail[] = [
                'id' => (string) $agreement->id,
                'agreement_number' => $agreement->agreement_number,
                'agreement_number_normalized' => $agreement->agreement_number_normalized,
                'batch_year' => $agreement->batch_year,
                'business_group' => $agreement->business_group,
                'effective_date' => $agreement->effective_date?->toDateString(),
                'maturity_date' => $agreement->maturity_date?->toDateString(),
                'tenor_months' => $agreement->tenor_months,
                'source_row_number' => $agreement->source_row_number,
                'lifecycle_status' => $agreement->lifecycle_status?->value,
                'lifecycle_status_label' => $agreement->lifecycle_status?->label(),
                'collectibility_status' => $collectibility['status'],
                'collectibility_status_label' => $collectibility['label'],
                'late_months' => $collectibility['late_months'],
                'data_verified' => (bool) $agreement->data_verified,
                'balance' => $balance,
                'payment_allocations' => $allocationsDetail,
                'receivable_adjustments' => $adjustmentsDetail,
                'schedules' => $schedulesDetail,
            ];
        }

        // Parked funds for this partner per DEC-006
        $parkedFundsTotal = (int) FundLot::query()
            ->where('partner_id', $partner->id)
            ->whereIn('lot_type', [
                FundLotType::IdentifiedUnallocated->value,
                FundLotType::Excess->value,
            ])
            ->sum('amount');

        $receivableRollup = [
            'contract_principal' => $contractP,
            'contract_charge' => $contractC,
            'contract_total' => $contractTotal,
            'paid_principal' => $paidP,
            'paid_charge' => $paidC,
            'paid_total' => $paidTotal,
            'adjustment_principal' => $adjP,
            'adjustment_charge' => $adjC,
            'adjustment_total' => $adjTotal,
            'remaining_principal' => $remainingP,
            'remaining_charge' => $remainingC,
            'remaining_total' => $remainingTotal,
            'active_agreements_count' => $activeAgreementsCount,
            'total_agreements_count' => $agreements->count(),
            'has_exception' => $hasException,
            'warnings' => array_values(array_unique($rollupWarnings)),
            'parked_funds_total' => $parkedFundsTotal,
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'partner' => (new PartnerResource($partner))->resolve($request),
                'receivable_rollup' => $receivableRollup,
                'agreements_detail' => $agreementsDetail,
                'as_of' => $asOf->toDateString(),
            ]);
        }

        return Inertia::render('Partners/Show', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'receivable_rollup' => $receivableRollup,
            'agreements_detail' => $agreementsDetail,
            'as_of' => $asOf->toDateString(),
        ]);
    }
}
