# Metric definitions

Repository deliverable required by PRD §9, backing the `MetricDefinition`
entity (§4) and the dashboard requirement (FR-11). Every metric shown on
any dashboard or export must have a row here, versioned, before it ships.

**No metric in this file may be invented.** If a dashboard needs a number
and its exact formula, population, or date semantics isn't settled, add an
`OPEN` row to `docs/decisions.md` instead of guessing a formula here — the
dashboard shows `unverified` for that metric until it's resolved.

## Template

```
### <Metric name> — v<N>

- **Status:** draft | approved
- **Owner:** <role from PRD §3>
- **Formula / scope:** <exact definition>
- **Numerator:** <what's counted, and what's excluded>
- **Denominator:** <what's counted, and what's excluded>
- **Date semantics:** <as-of date meaning — transaction date? posting date? effective date?>
- **Included population:** <which agreements/partners/states count>
- **Excluded / unclassified:** <what falls outside, and where it's shown — PRD FR-11 requires an explicit "unclassified" bucket so category sums equal totals>
- **Decision ref:** <DEC-NNN if this formula depends on an OPEN/RESOLVED decision>
- **Approved by:** <name/role + date, once status is "approved">
```

## Definitions

<!-- Phase A balance computation is still gated behind DP resolution
     (DEC-008 RESOLVED IN PART), but the formulas themselves are now
     approved. Metrics below are "approved" for their formula definitions;
     live computation ships once the DP-gated engine is built. -->

### Remaining Principal — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `remaining_P(as_of) = P_contract + adj_P(as_of) - paid_P(as_of)` per agreement
- **Numerator:** Contract principal + signed dated principal adjustments effective ≤ as_of
- **Denominator:** Net posted principal allocations effective ≤ as_of (original allocations − linked reversals)
- **Date semantics:** As-of date; effective date of allocations and adjustments, not receipt date
- **Included population:** Agreements with lifecycle_status in (active, paid_off, closed_by_rescheduling). Draft/cancelled excluded
- **Excluded / unclassified:** Draft and cancelled agreements shown separately. Unknown lifecycle shown in "unclassified" bucket
- **Decision ref:** DEC-008 (RESOLVED IN PART — formula approved, DPs open). Source: `docs/formula-specification.md` §3
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### Remaining Charge (Bunga / Jasa Administrasi) — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `remaining_C(as_of) = C_contract + adj_C(as_of) - paid_C(as_of)` per agreement
- **Numerator:** Contract charge + signed dated charge adjustments effective ≤ as_of
- **Denominator:** Net posted charge allocations effective ≤ as_of (original allocations − linked reversals)
- **Date semantics:** As-of date; effective date of allocations and adjustments, not receipt date
- **Included population:** Same as Remaining Principal
- **Excluded / unclassified:** Same as Remaining Principal
- **Decision ref:** DEC-008 (RESOLVED IN PART). DP-1 governs whether C splits into admin + bunga (DEFAULT_ACTIVE: one component). Source: `docs/formula-specification.md` §3, §5
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2, §4)

### Total Remaining Balance — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `remaining(as_of) = remaining_P(as_of) + remaining_C(as_of)` per agreement
- **Numerator:** N/A (sum of two component metrics)
- **Denominator:** N/A
- **Date semantics:** Same as components
- **Included population:** Same as Remaining Principal
- **Excluded / unclassified:** Same as Remaining Principal. Must never produce negative without explicit exception state (no floor at 0)
- **Decision ref:** DEC-008 (RESOLVED IN PART). Source: `docs/formula-specification.md` §3
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### LUNAS (Zero Balance Override) — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `is_lunas(as_of) = data_verified AND remaining(as_of) == 0 AND remaining_P == 0 AND remaining_C == 0`
- **Numerator:** Agreements where all component balances are verified zero
- **Denominator:** Total agreements in included population
- **Date semantics:** As-of date, same as balance metrics
- **Included population:** All agreements with data_verified = true. Unknown/unverified data must NEVER become Lunas
- **Excluded / unclassified:** Unverified agreements shown as "Unknown". Empty collectibility must never become Lunas (legacy bug to avoid). LUNAS (money) ≠ Selesai (business lifecycle)
- **Decision ref:** DEC-007 (RESOLVED), DEC-008 (RESOLVED IN PART). Source: `docs/formula-specification.md` §3.1, §9.4
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §5)

### Collectibility Label — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** Five-label function: if not data_verified → "Unknown"; if remaining == 0 → "Lunas"; else apply month bands via active rule_version. Bands: 0–1 months late → Lancar, 2–6 → Kurang Lancar, 7–9 → Diragukan (Ragu-ragu), >9 → Bermasalah (Macet)
- **Numerator:** Agreements matching each band
- **Denominator:** Total agreements in included population (category sums must equal total — FR-11 requirement)
- **Date semantics:** As-of date. Month-count semantics (DP-2) still OPEN — bands are FINAL, counting algorithm is not
- **Included population:** All agreements with active or paid_off lifecycle
- **Excluded / unclassified:** Unknown collectibility shown in explicit "Unknown" bucket, never silently classified. Cancelled/draft excluded
- **Decision ref:** DEC-007 (RESOLVED — labels FINAL, DP-2 OPEN). Source: `docs/formula-specification.md` §9.2, §9.4; `docs/master-compilation.md` §5
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §5 — FINAL)

### Allocation Order (Admin-First, Oldest-Due-First) — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** Within one installment: `alloc_C = MIN(A, out_C)`, then `alloc_P = MIN(A - alloc_C, out_P)`, leftover to next installment. Across installments: oldest due date first (DP-6 DEFAULT_ACTIVE)
- **Numerator:** N/A (process rule, not an aggregate)
- **Denominator:** N/A
- **Date semantics:** Effective date of allocation
- **Included population:** All payment allocations against active agreements
- **Excluded / unclassified:** Reversed allocations excluded from capacity. ABT lots excluded until identified and allocated
- **Decision ref:** DEC-008 (RESOLVED IN PART). DP-1 (admin-first, DEFAULT_ACTIVE), DP-6 (oldest-first, DEFAULT_ACTIVE). Source: `docs/formula-specification.md` §6
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### Months Paid (Installments Fully Covered) — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `installments_fully_covered(as_of)` = count of installments where `outstanding_P == 0 AND outstanding_C == 0`. Integer
- **Numerator:** Installments with zero remaining on both components
- **Denominator:** N/A (count, not ratio)
- **Date semantics:** As-of date, same as balance metrics
- **Included population:** Installment schedule rows for active agreements
- **Excluded / unclassified:** Cancelled installments excluded
- **Decision ref:** DEC-008 (RESOLVED IN PART). Source: `docs/formula-specification.md` §3.2
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### Months Remaining — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `tenor_months - installments_fully_covered(as_of)`, floor at 0 for display only
- **Numerator:** N/A (derived counter)
- **Denominator:** N/A
- **Date semantics:** As-of date
- **Included population:** Same as Months Paid
- **Excluded / unclassified:** Same as Months Paid
- **Decision ref:** DEC-008 (RESOLVED IN PART). Source: `docs/formula-specification.md` §3.2
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### Late Fee — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `late_fee = 0` always. No late penalty, no grace-period fee. `other_charge` must not become a late fee
- **Numerator:** N/A (constant zero)
- **Denominator:** N/A
- **Date semantics:** N/A
- **Included population:** All agreements
- **Excluded / unclassified:** N/A
- **Decision ref:** DEC-008 (RESOLVED). Source: `docs/formula-specification.md` §1 rule 3; `docs/master-compilation.md` §2
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2)

### Excess Amount (True Excess) — v1

- **Status:** approved
- **Owner:** Kasir TJSL (Operator)
- **Formula / scope:** `excess_amount = payment_amount_left - partner_total_remaining(as_of)` where `partner_total_remaining = SUM(remaining over partner's active agreements)`. Excess = beyond total remaining debt (DP-7 DEFAULT_ACTIVE)
- **Numerator:** Payment amount remaining after covering all partner debt
- **Denominator:** N/A
- **Date semantics:** As-of date for partner_total_remaining
- **Included population:** Payments where allocated amount exceeds partner's total remaining across all active agreements
- **Excluded / unclassified:** ABT lots (unidentified) are NOT excess. Excess lots never leave the system (no refund). Reallocation to other partners creates explicit transfer records
- **Decision ref:** DEC-006 (RESOLVED IN PART), DEC-008 (RESOLVED IN PART). DP-7 (DEFAULT_ACTIVE), DP-8 (DEFAULT_ACTIVE — park lot, no auto-pick). Source: `docs/formula-specification.md` §7
- **Approved by:** Process owner, 2026-10-03 (via `docs/master-compilation.md` §2, §3)
