# Task: Implement balance formulas in BalanceService

**Phase:** Formula Implementation
**ID:** FIMPL-006
**Depends on:** FIMPL-005
**PRD reference:** §4 (balance() interface), FR-02

## Decision

**DEC-008 — Balance calculation rules/formulas — `RESOLVED IN PART` 2026-10-03**

> "Owner confirmed the following balance and formula rules:
>
> - **Tenor:** 2-year (24 months) default for new agreements. Store per agreement
>   because legacy cohorts include 7 to 8 month rows.
> - **No late penalty.** `late_fee = 0` always. `other_charge` must not become a
>   late fee.
> - **Allocation order:** Admin/bunga first, then pokok (principal).
> - **Bunga and admin are manual inputs** by TJSL staff. No rate engine.
> - **Excess goes to other partners**, not back.
> - **No return or refund** of any money.
> - **Money is integer Rupiah (BIGINT).** No floats. Never use `max(x,0)` to
>   hide a negative."
>
> — `docs/decisions.md` DEC-008, source: `docs/master-compilation.md` §2, §4; `docs/formula-specification.md` §1–§7

**Balance formulas (formula-specification.md §3):**

> ```text
> contract_total      = P_contract + C_contract
> paid_P(as_of)       = SUM(allocation.P  WHERE effective_date <= as_of AND not effectively reversed)
> paid_C(as_of)       = SUM(allocation.C  WHERE ... same filter)
> paid_total          = paid_P + paid_C
> remaining_P(as_of)  = P_contract + adj_P(as_of) - paid_P(as_of)
> remaining_C(as_of)  = C_contract + adj_C(as_of) - paid_C(as_of)
> remaining(as_of)    = remaining_P + remaining_C
> ```

> "- `adj_*` = signed dated receivable adjustments effective on or before `as_of`.
> - Posting must never produce `remaining_P < 0` or `remaining_C < 0`. Surplus
>   goes to an excess lot. If a negative still appears (data error), surface it
>   as an explicit exception state. Do not floor it.
> - Reversal: a reversal is a separately dated event. A balance as of a date
>   before the reversal date still includes the original payment.
> - Draft or not-yet-active agreements do not create debt."

**LUNAS rule (formula-specification.md §3.1):**

> ```text
> is_lunas(as_of) = data_verified AND remaining(as_of) == 0 AND remaining_P == 0 AND remaining_C == 0
> ```

**Metric refs:** Remaining Principal v1, Remaining Charge v1, Total Remaining Balance v1, LUNAS v1 (`docs/metric-definitions.md`)

## Current stub

- `app/Services/BalanceService.php:37-87` — `getBalance()` returns `'unverified'` for all components with status `'unverified'`.
- `app/Models/InstallmentSchedule.php:69-72` — `calculateOutstanding()` throws `NotApprovedException::forBalanceCalculation()`.
- `app/Models/ReceivableAdjustment.php:76-78` — `applyToBalance()` throws `NotApprovedException::forReceivableAdjustmentPosting()`.
- `app/Exceptions/NotApprovedException.php:21-24` — `forBalanceCalculation()` factory.
- `app/Exceptions/NotApprovedException.php:31-34` — `forReceivableAdjustmentPosting()` factory.

## Implementation

1. **Replace `BalanceService::getBalance()`** with actual computation:
   - Query `PaymentAllocation` records for the agreement, filtering by `effective_date <= $asOf` and excluding effectively-reversed allocations (where a reversal exists for the same allocation).
   - Sum `principal_amount` and (interest + admin + other) as paid components.
   - Query `ReceivableAdjustment` records for the agreement, filtering by `effective_date <= $asOf` and `state = 'posted'`.
   - Compute: `remaining_P = P_contract + adj_P - paid_P`, `remaining_C = C_contract + adj_C - paid_C`.
   - Return integer values, NOT `'unverified'` strings.
   - If `remaining_P < 0` or `remaining_C < 0`, set status to `'exception'` and add warning. Do NOT floor to 0.
   - If `remaining == 0` and all components verified, set `is_lunas = true`.
   - Include `as_of`, `rule_version = 'DEC-008-v1'`, and list of included event IDs.
2. **Replace `InstallmentSchedule::calculateOutstanding()`** — compute outstanding per installment from allocations. Keep stub for schedule generation (DP-3/DP-4 dependent).
3. **Keep `ReceivableAdjustment::applyToBalance()` as stub** — the adjustment records already exist in the DB; `BalanceService` reads them. The `applyToBalance()` method implied a write-back pattern that isn't needed when balance is computed from events.
4. **Draft agreements** continue returning no-debt response (PRD FR-02). Already handled.
5. **Still OPEN:** DP-1 through DP-10 formula decision points. The balance formula above uses DPs only for schedule-level detail. The core `remaining = contract + adjustments - payments` is fully approved. Installment-level allocation (which installment a payment clears) depends on DP-6 (FIMPL-007).
6. Docblock cites `DEC-008`, `Remaining Principal v1`, `Remaining Charge v1`, `Total Remaining Balance v1`, `LUNAS v1`.

## Pest tests

- Assert `getBalance()` for agreement with no payments returns contract amounts as remaining.
- Assert `getBalance()` for agreement with one posted allocation returns correct remaining.
- Assert `getBalance()` with reversed allocation: balance as-of date before reversal includes payment; as-of date after reversal excludes it.
- Assert `getBalance()` with receivable adjustment adds to remaining.
- Assert draft agreement returns no-debt response.
- Assert negative remaining (data error) produces exception status, not floor to 0.
- Assert `is_lunas` when all components are zero and data is verified.
- Assert `remaining` values are integers, not strings.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-006 | implemented | Balance formulas: remaining = contract + adj - paid per DEC-008 | DEC-008 |

Previous entries to update: TASK-003, TASK-005, TASK-006 rows mentioning DEC-008 stub.

## Acceptance criteria

- [ ] `BalanceService::getBalance()` returns integer component values, not `'unverified'`
- [ ] Formula: `remaining_P = P_contract + adj_P - paid_P` (same for C)
- [ ] Reversals handled as separately dated events
- [ ] Negative remaining surfaces as exception, not floored
- [ ] LUNAS rule: `remaining == 0 AND data_verified`
- [ ] Draft agreements create no debt
- [ ] `rule_version` and `as_of` included in response
- [ ] Docblocks cite `DEC-008`, metric versions
- [ ] Pest tests assert computed values (not just `'unverified'`)
- [ ] Change log updated

## Status

`pending`
