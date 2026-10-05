<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementLifecycleStatus;
use App\Enums\FundLotType;
use App\Enums\PaymentState;
use App\Models\Agreement;
use App\Models\FundLot;
use App\Models\FundTransfer;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service orchestrating ABT fund lot allocations to agreements and fund transfers
 * per DEC-006, DP-7, DP-8, and docs/formula-specification.md §7–§8.
 *
 * Citation:
 * - DEC-006: Four-concept ABT fund model (2026-10-03).
 * - DP-7: Partner total remaining debt excess scope.
 * - DP-8: Manual target choice for parked funds.
 * - formula-specification.md §7-§8: Reallocation and atomic money movement.
 */
class FundTransferService
{
    public function __construct(
        protected PaymentStagingService $paymentStagingService,
        protected AuditService $auditService,
        protected BalanceService $balanceService,
    ) {}

    /**
     * Allocate an identified unallocated ABT lot to an active agreement of the identified partner.
     *
     * Invariants (DEC-006, PRD §4):
     * - Atomic lock-check-write: SELECT ... FOR UPDATE on lot, agreement, schedules.
     * - Lot capacity strictly validated; double-consumption rejected.
     * - Lot amount is decremented by allocated amount.
     * - If lot amount reaches 0, lot transitions to Allocated.
     * - PaymentAllocation posted directly by cashier per DEC-005.
     * - FundTransfer record links source lot and resulting posted allocation.
     *
     * @throws InvalidArgumentException When validation fails.
     */
    public function allocateAbtToAgreement(
        FundLot $lot,
        Agreement $agreement,
        int $amount,
        User $actor,
        ?string $reason = null,
        ?string $effectiveDate = null,
        ?string $idempotencyKey = null,
    ): FundTransfer {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah alokasi harus lebih besar dari 0.');
        }

        if ($lot->lot_type !== FundLotType::IdentifiedUnallocated) {
            throw new InvalidArgumentException("Hanya dana parkir yang telah teridentifikasi mitranya yang dapat dialokasikan. Status saat ini: '{$lot->lot_type->value}'.");
        }

        if ($lot->partner_id === null) {
            throw new InvalidArgumentException('Dana parkir belum memiliki mitra yang teridentifikasi.');
        }

        if ($agreement->partner_id !== $lot->partner_id) {
            throw new InvalidArgumentException('Perjanjian tujuan bukan milik mitra pemilik dana parkir.');
        }

        if ($agreement->lifecycle_status !== AgreementLifecycleStatus::Active) {
            throw new InvalidArgumentException("Hanya perjanjian berstatus Aktif yang dapat menerima alokasi. Status saat ini: '{$agreement->lifecycle_status->value}'.");
        }

        if ($idempotencyKey !== null) {
            $existing = FundTransfer::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load(['sourceLot', 'targetPartner', 'targetAgreement', 'linkedAllocation']);
            }
        }

        return DB::transaction(function () use (
            $lot,
            $agreement,
            $amount,
            $actor,
            $reason,
            $effectiveDate,
            $idempotencyKey,
        ): FundTransfer {
            /** @var FundLot $lockedLot */
            $lockedLot = FundLot::where('id', $lot->id)->lockForUpdate()->firstOrFail();

            if ($lockedLot->lot_type !== FundLotType::IdentifiedUnallocated) {
                throw new InvalidArgumentException("Status dana parkir tidak valid untuk alokasi: '{$lockedLot->lot_type->value}'.");
            }

            $capacity = $lockedLot->calculateRemainingCapacity();
            if ($amount > $capacity) {
                throw new InvalidArgumentException('Jumlah alokasi (Rp '.number_format($amount, 0, ',', '.').') melebihi sisa kapasitas dana (Rp '.number_format($capacity, 0, ',', '.').').');
            }

            /** @var Agreement $lockedAgreement */
            $lockedAgreement = Agreement::where('id', $agreement->id)->lockForUpdate()->firstOrFail();

            if ($lockedAgreement->lifecycle_status !== AgreementLifecycleStatus::Active) {
                throw new InvalidArgumentException('Perjanjian tujuan harus berstatus Aktif.');
            }

            $date = $effectiveDate ?? now()->toDateString();
            $allocReason = $reason ?? "Alokasi dana ABT ke perjanjian {$lockedAgreement->agreement_number}";

            // 1. Deduct lot amount & update status atomically
            $lockedLot->amount = (int) $lockedLot->amount - $amount;
            if ($lockedLot->amount === 0) {
                $lockedLot->lot_type = FundLotType::Allocated;
            }
            $lockedLot->version = (int) $lockedLot->version + 1;
            $lockedLot->save();

            // Sync original in-memory instance
            $lot->amount = $lockedLot->amount;
            $lot->lot_type = $lockedLot->lot_type;

            // 2. Create draft PaymentAllocation with lot bank_transaction_id
            $allocation = PaymentAllocation::create([
                'bank_transaction_id' => $lockedLot->bank_transaction_id,
                'agreement_id' => $lockedAgreement->id,
                'principal_amount' => $amount,
                'interest_amount' => 0,
                'admin_charge_amount' => 0,
                'other_charge_amount' => 0,
                'total_amount' => $amount,
                'effective_date' => $date,
                'state' => PaymentState::Draft,
                'evidence' => $lockedLot->evidence ?? $allocReason,
                'idempotency_key' => (string) Str::uuid(),
                'reason' => $allocReason,
                'version' => 1,
            ]);

            // 3. Post allocation directly per DEC-005 (runs AllocationService::allocate)
            $postedAllocation = $this->paymentStagingService->post($allocation, $actor);

            // 4. Create FundTransfer audit link
            $transfer = FundTransfer::create([
                'source_lot_id' => $lockedLot->id,
                'target_partner_id' => $lockedAgreement->partner_id,
                'target_agreement_id' => $lockedAgreement->id,
                'amount' => $amount,
                'reason' => $allocReason,
                'actor_id' => $actor->id,
                'effective_date' => $date,
                'linked_allocation_id' => $postedAllocation->id,
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                'version' => 1,
            ]);

            // 5. Emit audit event
            $this->auditService->log(
                action: 'fund_lot_allocated',
                target: $lockedLot,
                delta: [
                    'allocated_amount' => $amount,
                    'remaining_amount' => $lockedLot->amount,
                    'target_agreement_id' => $lockedAgreement->id,
                    'allocation_id' => $postedAllocation->id,
                    'transfer_id' => $transfer->id,
                ],
                reason: $allocReason,
                actor: $actor,
            );

            return $transfer->load(['sourceLot', 'targetPartner', 'targetAgreement', 'linkedAllocation']);
        });
    }
}
