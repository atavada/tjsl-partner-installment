# Task: Implement allocation algorithm (admin-first, oldest-due-first)

**Phase:** Formula Implementation
**ID:** FIMPL-007
**Depends on:** FIMPL-006
**PRD reference:** FR-03 (Payment capture), §4 (PaymentAllocation)

## Decision

**DEC-008 — Balance calculation rules/formulas — `RESOLVED IN PART` 2026-10-03**

> "**Allocation order:** Admin/bunga first, then pokok (principal). See
> `docs/formula-specification.md` §6 for the allocation algorithm."
>
> — `docs/decisions.md` DEC-008

**Allocation algorithm (formula-specification.md §6):**

> "For one installment with `out_C`, `out_P` outstanding and a payment amount `A`
> applied to it:
>
> ```text
> alloc_C = MIN(A, out_C)
> alloc_P = MIN(A - alloc_C, out_P)
> leftover = A - alloc_C - alloc_P    # move to the next installment, else to excess handling
> ```
>
> Across installments:
> - Record, per allocation, which installment(s) it clears
>   (allocation-to-installment link). The current ERD lacks this. Without it,
>   late-month counts cannot know which due month a late payment cleared."

**DP-1 (DEFAULT_ACTIVE):** "One component C, admin-first, priority stored as data."
**DP-6 (DEFAULT_ACTIVE):** "Oldest due first."

**Metric ref:** Allocation Order v1 (`docs/metric-definitions.md`)

## Current stub

- `app/Services/PaymentStagingService.php:195-201` — `post()` throws `NotApprovedException::forPaymentPosting()`.
- `app/Policies/PaymentPolicy.php:35-39` — `post()` returns `false` ("Posting is blocked on balance rules (DEC-008)").
- `app/Exceptions/NotApprovedException.php:36-39` — `forPaymentPosting()` factory.
- No `AllocationService` exists yet (listed in TASK-007 scope but not created).

## Implementation

1. **Create `app/Services/AllocationService.php`** implementing the §6 algorithm:
   - Method `allocate(PaymentAllocation $allocation, Agreement $agreement, CarbonInterface $asOf): AllocationResult`
   - Load installment schedule ordered by `due_date ASC` (oldest first, DP-6).
   - For each installment with outstanding amounts, apply: `alloc_C = MIN(remaining_payment, out_C)`, then `alloc_P = MIN(remaining_payment, out_P)`.
   - Carry leftover to next installment.
   - After all installments, any leftover is excess (→ FIMPL-008).
   - Priority order stored as data constant, not hardcoded if/else (DP-1: admin/bunga-first is the default).
   - Record allocation-to-installment links (new pivot table or JSON column — ERD gap noted in formula spec).
2. **Update `PaymentStagingService::post()`** — replace `NotApprovedException` throw with call to `AllocationService::allocate()`, then update `BalanceService` components.
3. **Update `PaymentPolicy::post()`** — replace `return false` with actual permission check: `$user->hasPermission(Permission::PaymentPost)`.
4. **DEC-005 interaction:** Per FIMPL-001, cashier posts directly without second review.
5. **Excess handling** (leftover after all installments): create an excess lot per §7 (detailed in FIMPL-008). For this task, store leftover amount as an `Overpayment` record.
6. **Still OPEN:** DP-5 (cashier enters one amount or components) — current form already accepts components; this DP governs the UX, not the allocation algorithm.
7. Docblock cites `DEC-008`, `Allocation Order v1`, `DP-1`, `DP-6`.

## Pest tests

- Assert single installment, exact payment: `alloc_C = out_C`, `alloc_P = out_P`, leftover = 0.
- Assert single installment, partial payment: admin/bunga first, then principal.
- Assert two installments, payment covers first fully + partial second: oldest-first order.
- Assert payment exceeds all installments: leftover becomes excess.
- Assert allocation records link to specific installment(s) cleared.
- Assert reversed allocation does not affect subsequent allocation runs.
- Assert zero-amount allocation rejected.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-007 | implemented | Allocation algorithm: admin-first, oldest-due-first per DEC-008 §6 | DEC-008 |

Previous entries to update: TASK-006 and TASK-007 rows mentioning DEC-008 posting stub.

## Acceptance criteria

- [ ] `AllocationService` implements §6 algorithm exactly
- [ ] Admin/bunga first, then principal (DP-1 DEFAULT_ACTIVE)
- [ ] Oldest due date first across installments (DP-6 DEFAULT_ACTIVE)
- [ ] Priority order stored as data, not hardcoded
- [ ] Allocation-to-installment links recorded
- [ ] `PaymentStagingService::post()` calls allocation service instead of throwing
- [ ] `PaymentPolicy::post()` returns actual permission check
- [ ] Excess leftover stored (detailed handling in FIMPL-008)
- [ ] Docblocks cite `DEC-008`, `DP-1`, `DP-6`, `Allocation Order v1`
- [ ] Pest tests assert allocation behavior with computed values
- [ ] Change log updated

## Status

`pending`
