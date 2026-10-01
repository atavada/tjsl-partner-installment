<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\AgreementResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\PaymentResource;
use App\Models\Agreement;
use App\Models\BankTransaction;
use App\Models\Partner;
use App\Models\PaymentAllocation;
use App\Services\PaymentReversalService;
use App\Services\PaymentStagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments for the specified partner and agreement.
     */
    public function index(Request $request, Partner $partner, Agreement $agreement): InertiaResponse|AnonymousResourceCollection
    {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        Gate::authorize('viewAny', BankTransaction::class);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');
        $agreement->load(['partner', 'documents']);

        $payments = BankTransaction::whereHas('allocations', function ($query) use ($agreement): void {
            $query->where('agreement_id', $agreement->id);
        })
            ->with([
                'allocations.agreement',
                'allocations.approvedBy',
                'overpayments',
                'recordedBy',
            ])
            ->orderByDesc('transaction_datetime')
            ->orderByDesc('created_at')
            ->get();

        if ($request->wantsJson()) {
            return PaymentResource::collection($payments);
        }

        return Inertia::render('Payments/Index', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreement' => (new AgreementResource($agreement))->resolve($request),
            'payments' => PaymentResource::collection($payments)->resolve($request),
        ]);
    }

    /**
     * Show the form for capturing and staging a new payment.
     */
    public function create(Request $request, Partner $partner, Agreement $agreement): InertiaResponse
    {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        Gate::authorize('create', BankTransaction::class);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');
        $agreement->load(['partner', 'documents']);

        return Inertia::render('Payments/Create', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreement' => (new AgreementResource($agreement))->resolve($request),
            'default_idempotency_key' => Str::uuid()->toString(),
        ]);
    }

    /**
     * Store and stage a captured payment transaction with allocation proposal.
     */
    public function store(
        StorePaymentRequest $request,
        Partner $partner,
        Agreement $agreement,
        PaymentStagingService $stagingService
    ): JsonResponse|RedirectResponse {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        $transaction = $stagingService->stage($request->validated(), $request->user());

        if ($request->wantsJson()) {
            return (new PaymentResource($transaction))->response()->setStatusCode(201);
        }

        return redirect()
            ->route('payments.show', [$partner, $agreement, $transaction])
            ->with('success', 'Pembayaran berhasil dicatat dan diajukan sebagai draft alokasi.');
    }

    /**
     * Display the specified payment transaction details and allocation proposals.
     */
    public function show(
        Request $request,
        Partner $partner,
        Agreement $agreement,
        BankTransaction $payment
    ): InertiaResponse|PaymentResource {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');

        $hasAllocation = $payment->allocations()->where('agreement_id', $agreement->id)->exists();
        abort_unless($hasAllocation, 404, 'Payment does not have allocations for this agreement.');

        Gate::authorize('view', $payment);

        $partner->load(['aliases', 'virtualAccounts'])->loadCount('agreements');
        $agreement->load(['partner', 'documents']);

        $payment->load([
            'allocations.agreement',
            'allocations.reversals',
            'allocations.reversalOf',
            'allocations.approvedBy',
            'overpayments',
            'recordedBy',
        ]);

        if ($request->wantsJson()) {
            return new PaymentResource($payment);
        }

        return Inertia::render('Payments/Show', [
            'partner' => (new PartnerResource($partner))->resolve($request),
            'agreement' => (new AgreementResource($agreement))->resolve($request),
            'payment' => (new PaymentResource($payment))->resolve($request),
        ]);
    }

    /**
     * Reverse a payment allocation, creating a compensating entry.
     */
    public function reverse(
        Request $request,
        Partner $partner,
        Agreement $agreement,
        BankTransaction $payment,
        PaymentAllocation $allocation,
        PaymentReversalService $reversalService
    ): JsonResponse|RedirectResponse {
        abort_unless($agreement->partner_id === $partner->id, 404, 'Agreement not found for this partner.');
        abort_unless($allocation->bank_transaction_id === $payment->id, 404, 'Allocation does not belong to this transaction.');
        abort_unless($allocation->agreement_id === $agreement->id, 404, 'Allocation does not belong to this agreement.');

        Gate::authorize('reverse', $payment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $reversal = $reversalService->reverse($allocation, $validated['reason'], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Alokasi pembayaran berhasil dibalikkan.',
                'reversal_id' => $reversal->id,
            ]);
        }

        return redirect()
            ->route('payments.show', [$partner, $agreement, $payment])
            ->with('success', 'Alokasi pembayaran berhasil dibalikkan dengan entri kompensasi.');
    }
}
