# Formula Specification

## 0. How to read this spec

- Tags: **[WB]** = copied from a workbook formula (cell refs given). **[RULING]** = staff rule. **[PRT]** = stated by staff from PRT 0010/PRT/DRUT/IX/2025. **[PROPOSAL]** = my recommended default, not decided. **[DECISION POINT DP-n]** = ambiguity must resolve. Build the default only if the DP says so, and keep it behind a rule-version field so it can change.

## 1. Global rules

1. Money is integer Rupiah (BIGINT). No floats. Never use `max(x,0)` to hide a negative number (legacy bug: balance floored at 0, overpayment silently lost).
2. All amounts of bunga and jasa administrasi are **manual inputs by TJSL staff** [RULING]. The app has **no rate engine**: no 3%, no 0.5%/month, no annuity formula. Do not generate charges from cohort resemblance. The interest-rate conflict (3% flat vs 0.5%/month annuity) is moot.
3. **No late penalty, no grace-period fee** [RULING]. `late_fee = 0` always. Reject any charge component that acts as a penalty (`other_charge` must be rejected unless an allowed meaning is defined).
4. **No return or refund** of any money [RULING].
5. Cashier posts directly, no approval step [RULING]. Keep audit trail and reversal.
6. Unknown is not zero. A default-0 column must not create debt or LUNAS. Require complete verified input before computing a balance.
7. Compute balances from authoritative events (contract amounts, dated adjustments, effective posted allocations). Do not trust cached totals or typed LUNAS from imports.
8. Every derived value is computed "as of" a date. Store `as_of`, `rule_version` with any snapshot.

## 2. Inputs per agreement

| Field            | Meaning                                       | Source                                                                                                                   |
| ---------------- | --------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `P_contract`     | contract pokok (principal)                    | typed [WB]                                                                                                               |
| `C_contract`     | contract bunga / jasa administrasi (manual)   | typed [WB]                                                                                                               |
| `tenor_months`   | number of monthly installments                | typed [WB]; default 24 for new agreements [RULING]; store per agreement because legacy cohorts include 7 to 8 month rows |
| `start_date`     | agreement start / disbursement date           | typed [WB]                                                                                                               |
| `first_due_date` | due date of installment 1                     | see DP-3                                                                                                                 |
| schedule rows    | per installment: `due_date`, `due_P`, `due_C` | see section 4                                                                                                            |

## 3. Totals and remaining balance

```text
contract_total      = P_contract + C_contract                      # WB
paid_P(as_of)       = SUM(allocation.P  WHERE effective_date <= as_of AND not effectively reversed)   # WB
paid_C(as_of)       = SUM(allocation.C  WHERE ... same filter)     # WB
paid_total          = paid_P + paid_C                              # WB
remaining_P(as_of)  = P_contract + adj_P(as_of) - paid_P(as_of)    # WB
remaining_C(as_of)  = C_contract + adj_C(as_of) - paid_C(as_of)    # WB
remaining(as_of)    = remaining_P + remaining_C                    # WB
```

- `adj_*` = signed dated receivable adjustments (addenda, corrections) effective on or before `as_of`.
- **Opening basis rule**: use either contract amounts OR an imported outstanding balance at a cutoff date, never both (double counting). Opening basis choice is DP-10 (migration, not Phase A runtime).
- Posting must never produce `remaining_P < 0` or `remaining_C < 0`. Surplus goes to an excess lot (section 7). If a negative still appears (data error), surface it as an explicit exception state. Do not floor it.
- Reversal: a reversal is a separately dated event. A balance as of a date before the reversal date still includes the original payment. Do not just flip the original row's status and filter on current state.
- Draft or not-yet-active agreements do not create debt.

### 3.1 LUNAS

```text
is_lunas(as_of) = data_verified AND remaining(as_of) == 0 AND remaining_P == 0 AND remaining_C == 0
```

Typed LUNAS in imports is a source claim, not proof. Empty/unknown collectibility must never become Lunas (legacy bug). LUNAS (money) is separate from agreement status **Selesai** (business lifecycle). Selesai must not automatically mean Lunas.

### 3.2 Derived display counters

| Value                           | App rule                                                                                                             |
| ------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| months paid                     | `installments_fully_covered(as_of)` = count of installments with `outstanding_P = 0 AND outstanding_C = 0`. Integer. |
| months remaining                | `tenor_months - installments_fully_covered`, floor at 0 for display only                                             |
| remaining installments estimate | display only: `ceil(remaining / typical_installment_amount)`. Never used for status.                                 |
| first-month installment         | `due_P + due_C` of installment 1                                                                                     |

## 4. Schedule and due dates

```text
agreement_end_date = EDATE(start_date, tenor_months)               # WB K
due_date(n)        = EDATE(anchor_date, n - 1)   for n = 1..tenor_months   # anchor = first_due_date
```

- Always compute each due date from the anchor, never by repeatedly adding one month (month-end drift). EDATE semantics: if the target month is shorter, use its last day.
- **DP-3 (first due date and numbering)**: is installment 1 due one month after `start_date` (i.e. `EDATE(start_date,1)`) or in the start month? Workbook example: start 15 Jun 2021, first month column labelled Jun 2021, end `EDATE(start,24)` = 15 Jun 2023. Staff must confirm. Until then store `first_due_date` as an explicit input per agreement and do not infer it. Also confirm whether the fixed due day-of-month per cohort is a rule or just an observation.
- **DP-4 (how monthly dues are determined)**, because bunga/admin and installments are typed:
    - Option A (recommended): staff enter or import the schedule per installment (`due_P`, `due_C`), like the workbook. Validate `SUM(due_P) = P_contract` and `SUM(due_C) = C_contract` per agreement, warn if not.
    - Option B [PROPOSAL, only if staff enter totals only]: `due_P(n) = round_to_1000(P_contract / tenor)` for n < tenor and the last installment takes the remainder; same for C. The remainder goes to the same component. Do **not** copy the workbook's last-month plug (21.26 month 23 shows a 112,400 "bunga" that is only a plug to reach 467,000).
    - Observed legacy rounding for reference: monthly pokok about P/24 rounded to 1,000; bunga rounded half up to 1,000. These are typed values, not rules.
- Monthly history display: one row per month from loan start, format "(bulan) (tahun)". A month with no payment shows Rp 0 and status "Belum Bayar" [RULING]. These Rp 0 rows are display only. Never fabricate zero-value transaction records.
- Receipt month is not the liability month. Keep both: `receipt_date/month` and the installment(s) each allocation clears (see section 6).
- Rescheduled/addendum agreements: judge the new agreement on its own schedule.

## 5. Manual bunga and admin inputs

- Staff type `C_contract` and per-installment `due_C`, and the cashier types or confirms received components when posting.
- Validation only: integer, `>= 0`, sums consistent (section 4). A hard check against any percentage is NOT required. A soft warning against a configurable reference is optional and must not block.
- **DP-1 (charge terminology and priority)**: Default for the build: treat `C` as one component named "jasa administrasi / bunga" and apply admin-first. If staff say they are two, add `C_admin` and `C_bunga` and a priority list (admin?, then bunga?, then pokok) and re-run tests. Keep the priority order as data (an ordered list), not hard-coded.

## 6. Allocation of a payment

For one installment with `out_C`, `out_P` outstanding and a payment amount `A` applied to it:

```text
alloc_C = MIN(A, out_C)
alloc_P = MIN(A - alloc_C, out_P)
leftover = A - alloc_C - alloc_P          # move to the next installment, else to excess handling
```

Across installments:

- Record, per allocation, which installment(s) it clears (allocation-to-installment link). The current ERD lacks this. Without it, late-month counts cannot know which due month a late payment cleared.
- If the cashier enters components manually, the server must **validate** them and then either reject or auto-correct.

Worked example (illustrative numbers, not data). Installment 1: `due_C = 50,000`, `due_P = 417,000` (total 467,000). Installment 2: `due_C = 48,000`, `due_P = 419,000`.

- Payment 300,000 on installment 1: alloc_C = 50,000, alloc_P = 250,000. Outstanding after: C 0, P 167,000.
- Payment 500,000 with both installments open (oldest first): installment 1 takes 50,000 C + 417,000 P = 467,000. Leftover 33,000 goes to installment 2: alloc_C = 33,000, alloc_P = 0. Outstanding on installment 2: C 15,000, P 419,000.

## 7. Overpayment and excess (no return)

Rulings: overpayment is allowed, but excess cannot be returned (complicated policy), so the extra money is reallocated to other partners "for now" [RULING]. ABT has no return either [RULING].

```text
partner_total_remaining(as_of) = SUM(remaining over that partner's active agreements)
excess_amount = payment amount left after covering partner_total_remaining      # true excess
```

- Anything above the **current due installment** is excess. Confirm.
- Excess becomes an `excess lot`: `lot_id, source_receipt_id, source_partner_id, amount, created_at, status`. It never leaves the system and has no refund action.
- Reallocation to another partner creates an explicit transfer record: `source_lot_id, target_partner_id, target_agreement_id, amount, reason, actor, effective_date, linked_allocation_id`. The target allocation then follows section 6.
- **DP-8 (excess target choice)**: Manual cashier choice. What reason/evidence is required? No rule exists.
- Lot consumption is atomic: lock the lot and receipt, check capacity, write transfer and allocation in one transaction. Never double-count parked money in capacity.

## 8. ABT (Angsuran Belum Teridentifikasi) money flow

ABT = installment deposits whose owner is not identified. It is **not** overpayment.

```text
raw_receipt (immutable, no owner needed)
   -> ABT lot (owner unknown, NO debt effect)
   -> identify (actor, time, evidence; owner = partner)         # now an identified-unallocated lot
   -> allocate to agreement(s) per section 6                    # only now does remaining() change
```

- Unidentified lots do not reduce any balance.
- No return, no delete: an unmatched ABT lot stays in the ABT queue permanently [RULING].
- No approval step and no second reviewer for now [RULING]. Record who identified and allocated.
- Keep four concepts separate: (1) raw receipt, (2) ABT (unknown owner), (3) identified but unallocated, (4) true excess (section 7). Do not label "bank amount minus entered components" as ABT (the current build does).
- Legacy hint: bank-sheet rows with unmatched No ID/name went to a "PERLU VERIFIKASI" queue. Reproduce that as a workflow, with a form that can capture a deposit without partner or agreement.

## 9. Lateness and collectibility [FINAL LABELS AND MONTH-EXPRESSED BANDS; DP-2]

### 9.1 Workbook formula [WB]

```text
late_months  = COUNTIF($N3:$CO3,"0")/2                       # 21.26!GB3: typed zeros / 2
label = IF(late_months>9,"Macet", IF(late_months>6,"Ragu-ragu", IF(late_months>1,"Kurang Lancar","Lancar")))   # 21.26!GC81
balance = 0 -> LUNAS
```

Workbook month thresholds numerically resemble the PRT day thresholds (30, 180, 270), but month counts and calendar-day lateness are not equivalent.

### 9.2 Owner ruling tonight [FINAL]

```text
late months 0 to 1   -> Lancar
late months 2 to 6   -> Kurang Lancar
late months 7 to 9   -> Diragukan (Ragu-ragu)
late months > 9      -> Bermasalah (Macet)
balance = 0          -> LUNAS (override)
```

This supersedes the provisional status and four-label variant. Keep the confirmed mapping in a versioned rule table; exact month-count semantics remain DP-2.

### 9.3 PRT day bands

Counted from the installment due date: Lancar = on time or late up to 30 calendar days; Kurang Lancar = more than 30 up to 180; Diragukan = more than 180 up to 270; Bermasalah = more than 270.

### 9.4 Final label function

```text
label(as_of):
  if NOT data_verified            -> "Unknown"          # never Lunas
  if remaining(as_of) == 0        -> "Lunas"
  m = late_months(as_of) per the resolved DP-2 month-count semantics
  apply the FINAL five-label month band table from 9.2 via the active rule_version
```

- Store snapshots with `as_of, rule_version, basis, qualifying unpaid installment, coverage`. One mutable status column cannot explain an as-of category.

## 10. Idempotency, duplicates and concurrency (affects formulas being correct)

- Same idempotency key + same payload returns the original. Same key + different payload fails.
- Allocation: lock receipt/lot and agreement rows; compute capacity and write in one transaction. Prevent double reversal.
- The register interprets it as bank reference unique within a partner. Scope per bank/account.
- Do not force receipt time to 12:00. Store the real time, or mark the precision as date-only.

## 11. Things the workbook does that must NOT be copied

1. Counting typed zeros as lateness (`COUNTIF(...,"0")/2`), including half months.
2. Inconsistent thresholds (`>0` vs `>1`) and wrong ranges.
3. Final-installment plug in the bunga column.
4. LUNAS typed by hand, or `=GD11` copying.
5. Recap ranges that start at the wrong row.
6. Rounding hidden inside typed values (installments of one 10M loan are 443,000 for some partners and 467,000 for others in the same cohort).
7. Legacy app: floor at 0, hard delete, no duplicate check, empty collectibility treated as Lunas, partner deduplication that drops paid loans.

## 12. Decision points summary

| ID    | Question                                                                                                                                       | Default until answered                                                             |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| DP-1  | Bunga and jasa admin: one charge or two? Priority in partial payment?                                                                          | One component C, admin-first, priority stored as data                              |
| DP-2  | Exact month-count semantics: missed installment months vs elapsed months since oldest unpaid; partial/non-consecutive handling and boundaries? | CLOSED: Month-expressed bands FINAL. Do explore the counting algorithm.            |
| DP-3  | First due date and installment numbering; fixed due day rule?                                                                                  | Store explicit `first_due_date`, do not infer                                      |
| DP-4  | Monthly dues: entered/imported vs divided from totals?                                                                                         | Entered or imported schedule; division fallback with remainder on last installment |
| DP-5  | Cashier enters one amount or components?                                                                                                       | One amount                                                                         |
| DP-6  | Application order across installments; prepayment allowed?                                                                                     | Oldest due first                                                                   |
| DP-7  | Excess = beyond total partner debt, or beyond current installment?                                                                             | Beyond total remaining debt                                                        |
| DP-8  | Who picks excess target partner/agreement, by what rule?                                                                                       | Park the lot, no auto-pick                                                         |
| DP-9  | Rescheduled agreements: does earlier lateness carry over?                                                                                      | New agreement judged on its own schedule                                           |
| DP-10 | Migration opening basis: contract amounts or outstanding at cutoff?                                                                            | Not Phase A runtime; never both                                                    |

## 13. Test checklist for formulas

- Totals: `contract_total`, paid, remaining match hand calculation for several agreements; remaining never negative after posting.
- LUNAS only when verified zero; unknown input never gives LUNAS; Selesai does not imply LUNAS.
- Partial payment: admin first then pokok; leftover moves to next installment, then excess handling; boundary amounts (exactly due, due + 1, due - 1).
- EDATE: 31 Jan start, 29/30 Feb leap year, anchor-based computation.
- Excess: lot created, no refund path, transfer lineage, atomic consumption, concurrent allocations cannot overspend.
- ABT: unidentified deposit saved with no partner; no balance change until identified and allocated.
- Late: confirmed month-band boundaries from 9.4; counting tests after DP-2 is answered, including non-consecutive misses and partial installments.
- Reversal: balance as of a date before the reversal still counts the payment; no double reversal.
- Aggregates: all metrics use the same population; label names match.
- Rule version: confirmed five-label month mapping is active. Historical variants remain traceable; do not silently activate four labels or day bands. Record the counting algorithm after DP-2 is resolved.
