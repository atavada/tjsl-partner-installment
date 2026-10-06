<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\InstallmentSchedule;
use App\Models\Partner;
use App\Models\VirtualAccount;
use Carbon\Carbon;

class CandidateMatchingService
{
    /**
     * Find candidate matches for a bank transaction across VA, partner ID, amount, and name similarity.
     * PRD §5 FR-04: Show candidates with rationale and competing candidate count.
     * Hard boundary: fuzzy name similarity only suggests; never auto-post or auto-merge.
     *
     * @return array<int, array{
     *     partner_id: string,
     *     partner_name: string,
     *     agreement_id: string|null,
     *     agreement_number: string|null,
     *     confidence: float,
     *     match_type: string,
     *     rationale: string,
     *     competing_candidate_count: int
     * }>
     */
    public function findCandidatesForTransaction(BankTransaction $transaction): array
    {
        $candidates = [];

        // 1. Exact VA Match (Confidence 1.00)
        if ($transaction->payer_va !== null && trim($transaction->payer_va) !== '') {
            $normalizedVa = preg_replace('/\D/', '', $transaction->payer_va);
            $vaRecords = VirtualAccount::with('partner.agreements')
                ->where('va_number_normalized', $normalizedVa)
                ->orWhere('va_number', trim($transaction->payer_va))
                ->get();

            foreach ($vaRecords as $va) {
                if ($va->partner !== null) {
                    $agreement = $va->partner->agreements->first();
                    $candidates[] = [
                        'partner_id' => $va->partner->id,
                        'partner_name' => $va->partner->name,
                        'agreement_id' => $agreement?->id,
                        'agreement_number' => $agreement?->agreement_number,
                        'confidence' => 1.00,
                        'match_type' => 'va_exact',
                        'rationale' => "Exact Virtual Account match on [{$transaction->payer_va}]",
                    ];
                }
            }
        }

        // 2. Exact Partner NO ID Match (Confidence 0.95)
        // If reference or notes contains a 15-digit or known partner_no_id
        $extractedId = $this->extractPartnerId($transaction->reference ?? $transaction->notes ?? '');
        if ($extractedId !== null) {
            $partners = Partner::with('agreements')
                ->where('partner_no_id_normalized', $extractedId)
                ->orWhere('partner_no_id', $extractedId)
                ->get();

            foreach ($partners as $partner) {
                $candidates[] = [
                    'partner_id' => $partner->id,
                    'partner_name' => $partner->name,
                    'agreement_id' => $partner->agreements->first()?->id,
                    'agreement_number' => $partner->agreements->first()?->agreement_number,
                    'confidence' => 0.95,
                    'match_type' => 'partner_id_exact',
                    'rationale' => "Exact Partner ID match on [{$extractedId}] in transaction metadata",
                ];
            }
        }

        // 3. Amount + Due Date Proximity Match (Confidence 0.80)
        if ($transaction->transaction_datetime !== null && $transaction->amount > 0) {
            $txDate = Carbon::parse($transaction->transaction_datetime);
            $startDate = $txDate->copy()->subDays(3)->toDateString();
            $endDate = $txDate->copy()->addDays(3)->toDateString();

            $schedules = InstallmentSchedule::with('agreement.partner')
                ->whereBetween('due_date', [$startDate, $endDate])
                ->where('total_due', $transaction->amount)
                ->limit(5)
                ->get();

            foreach ($schedules as $sched) {
                if ($sched->agreement?->partner !== null) {
                    $candidates[] = [
                        'partner_id' => $sched->agreement->partner->id,
                        'partner_name' => $sched->agreement->partner->name,
                        'agreement_id' => $sched->agreement->id,
                        'agreement_number' => $sched->agreement->agreement_number,
                        'confidence' => 0.80,
                        'match_type' => 'amount_date_proximity',
                        'rationale' => 'Installment due amount [Rp '.number_format($sched->total_due, 0, ',', '.')."] matches exactly with due date within 3 days [{$sched->due_date}]",
                    ];
                }
            }
        }

        // 4. Normalized Name Fuzzy Match (Confidence 0.70)
        if ($transaction->payer_name !== null && trim($transaction->payer_name) !== '') {
            $partners = Partner::with('agreements')->limit(100)->get();
            foreach ($partners as $partner) {
                $similarity = $this->calculateNameSimilarity($transaction->payer_name, $partner->name);
                if ($similarity >= 0.80) {
                    $candidates[] = [
                        'partner_id' => $partner->id,
                        'partner_name' => $partner->name,
                        'agreement_id' => $partner->agreements->first()?->id,
                        'agreement_number' => $partner->agreements->first()?->agreement_number,
                        'confidence' => 0.70,
                        'match_type' => 'name_fuzzy',
                        'rationale' => 'Fuzzy name similarity of '.round($similarity * 100, 1)."% between payer [{$transaction->payer_name}] and partner [{$partner->name}]",
                    ];
                }
            }
        }

        // Deduplicate candidates by partner_id + agreement_id keeping highest confidence
        $deduped = [];
        foreach ($candidates as $cand) {
            $key = $cand['partner_id'].':'.($cand['agreement_id'] ?? 'null');
            if (! isset($deduped[$key]) || $cand['confidence'] > $deduped[$key]['confidence']) {
                $deduped[$key] = $cand;
            }
        }

        $result = array_values($deduped);
        $count = count($result);

        // Sort descending by confidence
        usort($result, fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        // Attach competing candidate count
        foreach ($result as &$item) {
            $item['competing_candidate_count'] = max(0, $count - 1);
        }

        return $result;
    }

    /**
     * Calculate normalized string similarity between two names (0.0 to 1.0).
     */
    public function calculateNameSimilarity(string $nameA, string $nameB): float
    {
        $normA = mb_strtolower(trim(preg_replace('/\s+/', ' ', $nameA)));
        $normB = mb_strtolower(trim(preg_replace('/\s+/', ' ', $nameB)));

        if ($normA === '' || $normB === '') {
            return 0.0;
        }

        if ($normA === $normB) {
            return 1.0;
        }

        similar_text($normA, $normB, $percent);

        return round($percent / 100, 4);
    }

    /**
     * Extract normalized 15-digit partner NO ID if embedded in text.
     */
    protected function extractPartnerId(string $text): ?string
    {
        if (preg_match('/\b\d{10,16}\b/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }
}
