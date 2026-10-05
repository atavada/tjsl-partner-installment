<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Enums\Permission;
use App\Http\Requests\IdentifyAbtLotRequest;
use App\Http\Requests\StoreAbtLotRequest;
use App\Http\Resources\FundLotResource;
use App\Models\BankTransaction;
use App\Models\FundLot;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Controller for managing fund lots across the four-concept model per DEC-006.
 */
class FundLotController extends Controller
{
    /**
     * Display a listing of fund lots, optionally filtered by lot_type.
     */
    public function index(Request $request): AnonymousResourceCollection|InertiaResponse
    {
        Gate::authorize('viewAny', FundLot::class);

        $query = FundLot::query()
            ->with(['partner', 'identifiedBy', 'sourceAgreement', 'bankTransaction'])
            ->orderByDesc('created_at');

        if ($request->filled('lot_type')) {
            $query->where('lot_type', $request->input('lot_type'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $escapedSearch = addcslashes($search, '%_\\');
            $canRevealVa = (bool) $request->user()?->can(Permission::VaReveal->value);

            $query->where(function ($q) use ($escapedSearch, $canRevealVa): void {
                $q->whereHas('partner', function ($partnerQuery) use ($escapedSearch): void {
                    $partnerQuery->where('name', 'like', "%{$escapedSearch}%")
                        ->orWhere('partner_no_id', 'like', "%{$escapedSearch}%");
                })
                    ->orWhereHas('bankTransaction', function ($txnQuery) use ($escapedSearch, $canRevealVa): void {
                        $txnQuery->where('reference', 'like', "%{$escapedSearch}%")
                            ->orWhere('payer_name', 'like', "%{$escapedSearch}%");

                        if ($canRevealVa) {
                            $txnQuery->orWhere('payer_va', 'like', "%{$escapedSearch}%");
                        }
                    })
                    ->orWhere('evidence', 'like', "%{$escapedSearch}%")
                    ->orWhere('reason', 'like', "%{$escapedSearch}%");
            });
        }

        $lots = $query->paginate($request->integer('per_page', 15))->withQueryString();

        if ($request->wantsJson()) {
            return FundLotResource::collection($lots);
        }

        $stats = [
            'total_abt_amount' => (int) FundLot::where('lot_type', FundLotType::Abt->value)->sum('amount'),
            'total_abt_count' => FundLot::where('lot_type', FundLotType::Abt->value)->count(),
            'total_identified_amount' => (int) FundLot::where('lot_type', FundLotType::IdentifiedUnallocated->value)->sum('amount'),
            'total_identified_count' => FundLot::where('lot_type', FundLotType::IdentifiedUnallocated->value)->count(),
            'total_excess_amount' => (int) FundLot::where('lot_type', FundLotType::Excess->value)->sum('amount'),
            'total_excess_count' => FundLot::where('lot_type', FundLotType::Excess->value)->count(),
            'grand_total_amount' => (int) FundLot::sum('amount'),
            'grand_total_count' => FundLot::count(),
        ];

        $verifiedPartners = Partner::query()
            ->where('verification_state', 'verified')
            ->orderBy('name')
            ->get(['id', 'name', 'partner_no_id']);

        return Inertia::render('Abt/Index', [
            'lots' => FundLotResource::collection($lots)->response()->getData(true),
            'stats' => $stats,
            'verifiedPartners' => $verifiedPartners,
            'filters' => [
                'lot_type' => $request->input('lot_type'),
                'search' => $request->input('search'),
                'per_page' => $request->integer('per_page', 15),
            ],
        ]);
    }

    /**
     * Capture an unallocated/unidentified deposit (ABT lot) per DEC-006 & formula-spec §8.
     * Parks money received without an identified partner or agreement.
     */
    public function storeAbt(StoreAbtLotRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('createAbt', FundLot::class);

        $validated = $request->validated();
        $actor = $request->user();

        $fundLot = DB::transaction(function () use ($validated, $actor): FundLot {
            $amount = (int) $validated['amount'];
            $receiptDate = (string) $validated['receipt_date'];
            $receiptCarbon = Carbon::parse($receiptDate);
            $transactionDatetime = $receiptCarbon->setTime(12, 0, 0)->format('Y-m-d H:i:s');
            $derivedPeriod = $receiptCarbon->format('Y-m');

            if (! empty($validated['bank_transaction_id'])) {
                $transaction = BankTransaction::findOrFail($validated['bank_transaction_id']);
            } else {
                $source = (string) ($validated['source'] ?? 'MANUAL_ABT_CAPTURE');
                $reference = isset($validated['reference']) && trim((string) $validated['reference']) !== ''
                    ? trim((string) $validated['reference'])
                    : null;
                $payerVa = isset($validated['payer_va']) && trim((string) $validated['payer_va']) !== ''
                    ? trim((string) $validated['payer_va'])
                    : null;

                $fingerprint = BankTransaction::computeFingerprint(
                    source: $source,
                    datetime: $transactionDatetime,
                    amount: $amount,
                    reference: $reference,
                    payerVa: $payerVa,
                );

                $transaction = BankTransaction::create([
                    'reference' => $reference,
                    'reference_namespace' => 'MANUAL_ABT',
                    'transaction_datetime' => $transactionDatetime,
                    'timezone' => 'Asia/Jakarta',
                    'amount' => $amount,
                    'payer_name' => $validated['payer_name'] ?? null,
                    'payer_va' => $payerVa,
                    'source' => $source,
                    'fingerprint' => $fingerprint,
                    'idempotency_key' => (string) Str::uuid(),
                    'state' => PaymentState::Draft,
                    'receipt_month' => $derivedPeriod,
                    'provenance' => 'manual_abt_capture',
                    'notes' => $validated['notes'] ?? null,
                    'recorded_by_id' => $actor?->id,
                    'version' => 1,
                ]);
            }

            return FundLot::createAbtLot(
                transaction: $transaction,
                amount: $amount,
                evidence: $validated['evidence'] ?? null,
                reason: $validated['reason'] ?? 'Setoran belum teridentifikasi (ABT)',
                idempotencyKey: $validated['idempotency_key'],
            );
        });

        if ($request->wantsJson()) {
            return (new FundLotResource($fundLot->load(['bankTransaction'])))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()->route('abt.index')->with('success', 'Dana ABT berhasil dicatat ke antrean.');
    }

    /**
     * Identify an ABT lot and transition it to identified_unallocated per DEC-006.
     */
    public function identify(IdentifyAbtLotRequest $request, FundLot $fundLot): JsonResponse|RedirectResponse
    {
        Gate::authorize('identify', $fundLot);

        $partner = Partner::findOrFail($request->validated('partner_id'));

        $fundLot->identify(
            partner: $partner,
            actor: $request->user(),
            evidence: $request->validated('evidence')
        );

        if ($request->wantsJson()) {
            return (new FundLotResource($fundLot->load(['partner', 'identifiedBy', 'bankTransaction'])))
                ->response()
                ->setStatusCode(200);
        }

        return redirect()->route('abt.index')->with('success', 'Dana ABT berhasil diidentifikasi pada mitra.');
    }

    /**
     * Display a specific fund lot.
     */
    public function show(FundLot $fundLot): FundLotResource
    {
        Gate::authorize('view', $fundLot);

        return new FundLotResource(
            $fundLot->load(['partner', 'identifiedBy', 'sourceAgreement', 'bankTransaction'])
        );
    }
}
