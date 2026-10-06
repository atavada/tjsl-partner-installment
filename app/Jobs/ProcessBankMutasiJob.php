<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DiscrepancyType;
use App\Enums\PaymentState;
use App\Enums\ReconciliationStatus;
use App\Models\BankTransaction;
use App\Models\ReconciliationCase;
use App\Services\CandidateMatchingService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class ProcessBankMutasiJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{
     *     reference?: string|null,
     *     datetime: string,
     *     amount: int,
     *     payer_name?: string|null,
     *     payer_va?: string|null,
     *     notes?: string|null
     * }>  $rawRecords
     */
    public function __construct(
        public array $rawRecords,
        public string $source = 'bank_mutasi_upload',
        public ?int $recordedById = null,
    ) {}

    public function handle(CandidateMatchingService $matchingService): void
    {
        foreach ($this->rawRecords as $record) {
            $datetimeStr = $record['datetime'];
            $amount = (int) $record['amount'];
            $reference = $record['reference'] ?? null;
            $payerVa = $record['payer_va'] ?? null;
            $payerName = $record['payer_name'] ?? null;
            $notes = $record['notes'] ?? null;

            $fingerprint = BankTransaction::computeFingerprint(
                source: $this->source,
                datetime: $datetimeStr,
                amount: $amount,
                reference: $reference,
                payerVa: $payerVa
            );

            // PRD §4 invariant 5 & DEC-011: Idempotency check. Never insert duplicate fingerprint.
            if (BankTransaction::where('fingerprint', $fingerprint)->exists()) {
                continue;
            }

            $tx = BankTransaction::create([
                'reference' => $reference,
                'reference_namespace' => 'BANK_MUTASI',
                'transaction_datetime' => Carbon::parse($datetimeStr),
                'timezone' => 'Asia/Jakarta',
                'amount' => $amount,
                'payer_name' => $payerName,
                'payer_va' => $payerVa,
                'source' => $this->source,
                'fingerprint' => $fingerprint,
                'idempotency_key' => (string) Str::uuid(),
                'state' => PaymentState::Draft,
                'notes' => $notes,
                'recorded_by_id' => $this->recordedById,
                'version' => 1,
            ]);

            // Match candidate identities
            $candidates = $matchingService->findCandidatesForTransaction($tx);

            // If no perfect single match, create a ReconciliationCase for reviewer attention (PRD §5 FR-04)
            $topCandidate = $candidates[0] ?? null;
            $isUnambiguousMatch = $topCandidate !== null && $topCandidate['confidence'] >= 1.0 && ($topCandidate['competing_candidate_count'] ?? 0) === 0;

            if (! $isUnambiguousMatch) {
                $caseNumber = 'REC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

                ReconciliationCase::create([
                    'case_number' => $caseNumber,
                    'case_type' => 'unmatched_deposit',
                    'bank_transaction_id' => $tx->id,
                    'discrepancy_type' => DiscrepancyType::UnmatchedDeposit,
                    'status' => ReconciliationStatus::Unreviewed,
                    'candidate_matches' => $candidates,
                    'evidence' => "Bank transaction [{$tx->reference}] with amount Rp ".number_format($tx->amount, 0, ',', '.').' requires matching review',
                    'resolution_notes' => count($candidates) > 0 ? 'Multiple or partial candidates found.' : 'No candidate identified.',
                    'version' => 1,
                ]);
            }
        }
    }
}
