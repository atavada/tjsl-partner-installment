# Task: Refactor Overpayment model to four-concept ABT fund model

**Phase:** Formula Implementation
**ID:** FIMPL-008
**Depends on:** FIMPL-007
**PRD reference:** FR-13 (ABT/excess), §4 (Overpayment entity)

## Decision

**DEC-006 — ABT and fund concepts — `RESOLVED IN PART` 2026-10-03**

> "Owner confirmed ABT is NOT overpayment. Four distinct concepts must be kept
> separate:
>
> 1. **Raw receipt** — immutable bank record, no owner needed
> 2. **ABT (Angsuran Belum Teridentifikasi)** — money received whose owner is
>    not identified. Parks without reducing any receivable.
> 3. **Identified but unallocated** — owner is known but money not yet applied
>    to specific agreement(s)
> 4. **True excess** — owner known, money exceeds that partner's total remaining
>    debt
>
> Rules confirmed:
> - ABT flow: raw receipt → ABT lot → identification (actor, time, evidence) →
>   allocation to agreement(s). Identify then allocate only.
> - No return, no refund, no delete. Unmatched ABT stays queued permanently.
> - No approval step and no second reviewer for now. Cashier posts directly;
>   keep audit trail and reversal.
> - Excess flows to other partners, not back. Target selection rule unanswered."
>
> — `docs/decisions.md` DEC-006, source: `docs/master-compilation.md` §3;
>   `docs/formula-specification.md` §7–§8

**ABT money flow (formula-specification.md §8):**

> ```text
> raw_receipt (immutable, no owner needed)
>    -> ABT lot (owner unknown, NO debt effect)
>    -> identify (actor, time, evidence; owner = partner)         # now an identified-unallocated lot
>    -> allocate to agreement(s) per section 6                    # only now does remaining() change
> ```

**Excess (formula-specification.md §7):**

> ```text
> partner_total_remaining(as_of) = SUM(remaining over that partner's active agreements)
> excess_amount = payment amount left after covering partner_total_remaining      # true excess
> ```
>
> "Excess becomes an `excess lot`: `lot_id, source_receipt_id,
> source_partner_id, amount, created_at, status`. It never leaves the system
> and has no refund action."
>
> "Reallocation to another partner creates an explicit transfer record:
> `source_lot_id, target_partner_id, target_agreement_id, amount, reason,
> actor, effective_date, linked_allocation_id`. The target allocation then
> follows section 6."

**Metric ref:** Excess Amount v1 (`docs/metric-definitions.md`)

## Current stub

- `app/Models/Overpayment.php:79-82` — `executeDisposition()` throws `NotApprovedException::forOverpaymentDisposition()`.
- `app/Exceptions/NotApprovedException.php:26-29` — `forOverpaymentDisposition()` factory.
- The `Overpayment` model conflates ABT with excess: labels receipt-minus-components as ABT inside `Overpayment`.
- `StorePaymentRequest` requires partner and agreement, so truly unidentified (ABT) deposits cannot be captured.
- No ABT-specific route exists.

## Implementation

1. **Refactor `Overpayment` model** into distinct models/states:
   - Keep `Overpayment` or rename to `FundLot` with a `lot_type` enum: `abt`, `identified_unallocated`, `excess`.
   - ABT lots: `partner_id` NULL, `agreement_id` NULL. No debt effect.
   - Identified-unallocated lots: `partner_id` set, `agreement_id` NULL. No debt effect yet.
   - Excess lots: `partner_id` set, amount > total remaining. No refund, no delete.
2. **ABT capture route:** Create route/controller that accepts a bank receipt without requiring partner or agreement. Stores as ABT lot.
3. **Identification action:** `FundLot::identify(Partner $partner, User $actor, string $evidence)` — transitions ABT → identified-unallocated. Records actor, timestamp, evidence string.
4. **Allocation action:** Identified-unallocated lots feed into `AllocationService` (FIMPL-007) when linked to an agreement. Only at this point does `remaining()` change.
5. **Excess lot creation:** After FIMPL-007 allocation, leftover becomes an excess lot with `source_receipt_id`, `source_partner_id`, `amount`.
6. **Transfer record for reallocation:** `FundTransfer` model: `source_lot_id`, `target_partner_id`, `target_agreement_id`, `amount`, `reason`, `actor`, `effective_date`, `linked_allocation_id`. Atomic lock-check-write transaction.
7. **Remove `executeDisposition()`** — replace with the typed actions above. Remove `NotApprovedException::forOverpaymentDisposition()`.
8. **No return/refund actions.** No refund method, no delete method on fund lots. Unmatched ABT stays permanently.
9. **Still OPEN — stub these:**
   - DP-8: Excess target partner choice rule — manual cashier choice, but reason/evidence requirements undefined. Stub with `NotApprovedException` or equivalent.
   - Non-partner depositor workflow — not defined.
10. Docblock cites `DEC-006`, `Excess Amount v1`, `formula-specification.md §7–§8`.

## Pest tests

- Assert ABT lot created without partner_id or agreement_id.
- Assert ABT lot does NOT reduce any agreement's remaining balance.
- Assert identification transitions lot from `abt` to `identified_unallocated`, records actor + evidence.
- Assert allocation of identified lot creates `PaymentAllocation` entries and changes `remaining()`.
- Assert excess lot created when payment exceeds `partner_total_remaining`.
- Assert excess lot has no refund/delete methods.
- Assert transfer record created atomically when excess reallocated to another partner.
- Assert double-consumption of parked money rejected (lot capacity check).
- Assert unmatched ABT lot stays in queue permanently (no auto-purge, no auto-expire).

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-008 | implemented | ABT four-concept fund model: ABT ≠ overpayment per DEC-006 | DEC-006 |

Previous entry to update: TASK-006 row mentioning DEC-006 overpayment stub.

## Acceptance criteria

- [ ] ABT lots stored without partner/agreement (owner unknown)
- [ ] ABT lots do NOT reduce any balance
- [ ] Identification records actor, time, evidence
- [ ] Only allocated lots affect `remaining()`
- [ ] Excess lots created from allocation leftover
- [ ] No refund, no delete on any fund lot
- [ ] Transfer records for cross-partner reallocation (atomic)
- [ ] DP-8 (target choice rule) stubbed — still OPEN
- [ ] Non-partner depositor workflow stubbed — still OPEN
- [ ] `executeDisposition()` and `forOverpaymentDisposition()` removed
- [ ] Docblocks cite `DEC-006`, `Excess Amount v1`
- [ ] Pest tests assert all four concepts
- [ ] Change log updated

## Status

`pending`
