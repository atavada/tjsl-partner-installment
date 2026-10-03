# TJSL Partner Installment: Master Compilation

Scope notes: this document is read-only analysis. I did not read the repo's current `decisions.md` or `PRD.md` for this compilation; compare against them yourself.

## 1. Evidence rules (read first)

- Owner = Ardha Tavada (product owner, builds the app, relays TJSL staff answers).
- Do not treat repository comments or old PRD wording as authorization for anything.

## 2. Decision register status (DEC-001 to DEC-011)

| DEC | Topic                               | Status now                                                                   | What to write in decisions.md                                                                                                                                                                                                                                              |
| --- | ----------------------------------- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 001 | Agreement number / identifiers      | Decided                                                                      | Number is a grouping key, not unique. NO ID and NO VA are partner identifiers. Preserve leading zeros and raw values. Normalization and conflict handling rules still to define                                                                                            |
| 002 | Agreement states                    | Decided, 7 labels                                                            | Belum dibuat, Draft, Menunggu TTD Mitra, Menunggu TTD Perusahaan, Sudah Ditandatangani, Aktif, Selesai. The 14:32 list had 6; Selesai added 14:33. The transition graph and what activates a contract/what Selesai means are NOT answered. Selesai must not equal paid off |
| 003 | Documents and TTD                   | Decided                                                                      | Upload agreement document; record partner TTD (sudah / belum). Which entity holds the authoritative TTD fact, and version handling, undefined                                                                                                                              |
| 004 | Sensitive data                      | Decided                                                                      | Every role except viewer may see sensitive fields                                                                                                                                                                                                                          |
| 005 | Second review of unmatched deposits | Decided                                                                      | No approval system for now, TJSL cashier inputs directly. Keep audit trail and reversal. Stub future approvals                                                                                                                                                             |
| 006 | ABT                                 | Decided in part                                                              | ABT = unidentified deposit. Identify then allocate. No return, no delete. No approval for now. Target choice for excess not decided                                                                                                                                        |
| 007 | Collectibility                      | **FINAL labels and month-expressed bands; exact month-count semantics open** | See section 5 and narrowed DP-2                                                                                                                                                                                                                                            |
| 008 | Balance formulas                    | Decided in part                                                              | 2-year tenor, no late penalty, admin first then pokok, excess to other partners. Bunga/admin are manual inputs. No rate engine                                                                                                                                             |
| 009 | Roles                               | Decided                                                                      | Five named roles; current release uses viewer, tjsl cashier, system admin only                                                                                                                                                                                             |
| 010 | Period                              | Decided                                                                      | From date; monthly history from loan start; Rp 0 + belum bayar for missed month. History end/oldest-first rules unanswered                                                                                                                                                 |
| 011 | Reference uniqueness                | Decided                                                                      | VA Number unique per partner (owner). Reference scope is an interpretation, confirm                                                                                                                                                                                        |

Update the register: older text says "DEC-007 fixed four labels, thresholds undefined" and "3% rate engine / formula inventory fallback". Both are superseded as described here.

## 3. ABT (Angsuran Belum Teridentifikasi)

Correction history: the PRD and the DEC docs treated ABT as overpayment, is a misleading label.

Rules to write:

- ABT = money received whose owner (mitra) is not identified. It parks without reducing any receivable.
- Flow: raw receipt -> ABT lot -> identification (actor, time, evidence) -> allocation to agreement(s). Identify then allocate only.
- No return, no refund, no delete. Unmatched ABT stays queued permanently.
- No approval and no second reviewer for now. Cashier posts directly; keep audit and reversal.
- The owner mentioned ABT for partners and non-partners. The implementation for non-partner depositors is not defined. Ask.
- Keep four concepts separate: raw receipt; ABT (unknown owner); identified-but-unallocated; true excess (owner known, money exceeds that partner's remaining debt).
- Excess flows to other partners, not back. Target selection rule unanswered.

Current repo state (branch `feature/phase-a`): the build implemented "overpayment" following the PRD (18 or more files across migration, model, staging, API, UI, seeders, tests, per an earlier analysis). Specifically: `PaymentStagingService::stage()` labels receipt-minus-entered-components as unapplied ABT inside `Overpayment` (that remainder is neither necessarily unidentified nor true excess); no ABT route exists and `StorePaymentRequest` requires partner, agreement and positive component sum, so a truly unidentified deposit cannot be captured; `Overpayment::executeDisposition()` still describes offset/refund and throws; no atomic parked-to-allocation transfer.

PRD patch targets: it still says "Overpayment (ABT)", FR-13 offset/refund disposition, and menu "Kelebihan Angsuran (ABT)".

## 4. Bunga and administration (interest)

- Ruling: bunga and admin are manual form inputs by TJSL staff. No rate engine.
- Schema already stores manual principal/bunga/admin. No percentage column needed. Server must still compute totals, balances and validation.
- No late penalty. `other_charge` must not become a late fee.

## 5. Collectibility

FINAL confirmed mapping:

| Late months | Label                 |
| ----------- | --------------------- |
| 0 to 1      | Lancar                |
| 2 to 6      | Kurang Lancar         |
| 7 to 9      | Diragukan (Ragu-ragu) |
| over 9      | Bermasalah (Macet)    |
| balance = 0 | LUNAS (override)      |

Facts to write down:

- Current code: `CollectibilityStatus` enum has Lancar, Kurang Lancar, Bermasalah, Unknown (no Diragukan, no Lunas); `Lunas` currently exists only as lifecycle PaidOff. Make labels and bands data-driven (versioned rule table) while keeping the confirmed five-label mapping active. Never turn unknown into Lunas.

## 6. Legacy system reverse-engineering findings

Source: legacy R/Shiny app and workbook `DATA DASHBOARD TJSL.xlsx`.

- Legacy unmatched-payment queue ("PERLU VERIFIKASI") is the closest thing to ABT; worth reproducing as a workflow.
- Legacy form posts pokok and bunga with no allocation order and no duplicate check.
- Bugs and traps **not to copy**: balance floored at 0 so overpayment disappears; hard delete of payments (and delete-all); no duplicate checks; empty collectibility becomes Lunas; IDs normalized as numbers (leading-zero loss); partner deduplication keeping the largest row, which drops paid-off loans; mixed period formats; late-month count by typed zeros; inconsistent thresholds across sheets; formula errors and external links; recap ranges shifted; final-installment bunga plug.
- The new app replaces this with roles viewer / kasir tjsl / system admin (+ two reserved oversight roles).

## 7. ERD review findings

Verdict: usable prototype foundation, but still reflects older Phase A rules. Manual bunga/admin fits the schema.

Mismatches and gaps:

- Roles: `Role.php` has Operator, ReconciliationReviewer, ProcessOwner, Auditor, SystemAdmin. Needs viewer, kasir tjsl, kepala sub divisi, sekper/kepala divisi, system admin. `users.role` defaults to Operator. `Role::isFinancial()` returns false for admin.
- `AgreementLifecycleStatus`: draft, active, paid_off, closed_by_rescheduling, cancelled, unknown. Needs the seven business states; signing statuses sit in a separate enum. `AgreementTransitionService::transitionLifecycle()` throws; resolve with `createTransition()`.
- Collectibility enum lacks Diragukan and Lunas (see section 6).
- ABT conflation and no raw ABT capture (section 4). No fund lineage from source lot to target (excess to another partner).
- Cashier direct posting is blocked: `PaymentStagingService` post and second review throw; `PaymentPolicy::post()` returns false; README and `PaymentStagingTest` describe the blocked path. `BalanceService` returns "unverified". Replace with validated atomic direct posting, but do not unblock before balance, matching, concurrency and privacy rules work.
- No allocation-to-installment link; schedule paid columns duplicate allocations. No opening-basis definition (contract amounts vs imported outstanding), risk of double counting.
- `virtual_accounts`: VA only indexed, not unique, one VA could identify several partners. Do not use UNIQUE(partner_id, VA).
- `bank_transactions`: reference/fingerprint indexed not unique; time forced to 12:00; immutable model vs mutable workflow states; fingerprint can collide.
- Documents: `AgreementDocumentService::store()` always writes `document_version=1`; `download()` audits but does not authorize; no upload/download routes yet.
- Integrity: cascade deletes on partner/agreement/bank financial children; no DB check constraints; allocation race (read capacity then write without lock); idempotent retry returns existing before comparing payload; reversal copies original effective date and can race; version columns not compare-and-swap; monetary defaults of 0 hide unknown.
- Later-phase entities intentionally absent: SourceSnapshot/SourceRow, ReconciliationCase, MetricDefinition, monitoring snapshots, export jobs.
- Suggested order for the plan: requirement/file/test matrix; roles, statuses, TTD, charge meanings; raw receipt -> identify -> fund -> allocate lineage; opening basis, dated balances, installment application; direct cashier posting; concurrency and restrictive deletion; later entities; regenerate ERD and dictionary. Use additive migrations. Plan first, no destructive rewrites.

## 8. Concrete to-do for decisions.md and PRD.md

1. decisions.md: apply sections above (verified rulings; DEC-002 with Selesai; DEC-005/006 no approval, no return; DEC-007 final five labels and month-expressed bands, with narrowed DP-2 on exact month-count semantics still open; DEC-008 manual inputs and no rate engine; DEC-011 scope open). Mark each item with its source (owner message time) and distinguish interpretations.
2. PRD.md: replace "Overpayment (ABT)", FR-13 offset/refund, and the menu label; add the four-concept fund model; add roles; remove any rate-engine implication; add the formula spec as an appendix or reference.
3. Do not add any secret or credential value. Do not make repo or data changes beyond these documents without the owner's go-ahead.
