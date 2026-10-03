# Decisions log

Every `OPEN` item needs an owner before Phase B can begin (PRD §9, Phase B
gate). Do not delete resolved rows — mark them `RESOLVED` with the date and
the decision actually made, so the trail stays auditable.

## Summary

| ID | Question | Status | Owner |
|----|----------|--------|-------|
| DEC-001 | Agreement-number uniqueness scope | `RESOLVED` 2026-09-30 | Process owner |
| DEC-002 | Agreement lifecycle states and transitions | `RESOLVED` 2026-10-03 (labels; transitions OPEN) | Process owner |
| DEC-003 | Signing/document workflow states | `RESOLVED` 2026-10-03 (core; detail questions OPEN) | Process owner |
| DEC-004 | Sensitive-field unmasking policy | `RESOLVED` 2026-10-03 | Process owner |
| DEC-005 | Second review of unmatched deposits | `RESOLVED` 2026-10-03 | Process owner |
| DEC-006 | ABT and fund concepts | `RESOLVED IN PART` 2026-10-03 | Process owner |
| DEC-007 | Collectibility classification levels | `RESOLVED` 2026-10-03 (bands FINAL; DP-2 OPEN) | Process owner |
| DEC-008 | Balance calculation rules/formulas | `RESOLVED IN PART` 2026-10-03 | Process owner |
| DEC-009 | RBAC roles and permissions | `RESOLVED` 2026-10-03 | Process owner |
| DEC-010 | Payment period derivation and override | `RESOLVED` 2026-10-03 | Process owner |
| DEC-011 | Bank-reference uniqueness scope | `RESOLVED` 2026-10-03 | Process owner |

---

## DEC-001 — Agreement-number uniqueness

**Status:** `RESOLVED` — 2026-09-30
**Owner:** Process owner

**Decision.** Agreement numbers are not unique globally and are not unique per partner. They group partners by business group/batch per year. The unique identifiers for a partner are NO ID and NO VA.

**Original statement.** "Nomor perjanjian tidak unik global dan tidak unik per mitra. Nomor mengelompokkan mitra per kelompok usaha/batch per tahun. Identifier unik per mitra adalah NO ID dan NO VA."

**Observed supporting evidence.** UI-A shows identical agreement numbers for different partners. WB `PERJANJIAN!B3:B225` has 64 distinct nonempty numbers and 41 repeated-number groups. For example, rows 3–4 share a number but have two distinct VA values; rows 7–19 share another number across 13 distinct VAs. Blank and summary rows were excluded. WB `MITRA !B2:B1303` has 1,301 nonempty numbers, 1,194 distinct trimmed numbers and 94 repeated-number groups. `MITRA ` has a trailing space in its sheet name. The new-agreement form's uniqueness placeholder refers to NO ID. These observations support number reuse; the grouping meaning and partner identifiers are established by the process owner, not inferred from the workbook alone.

**Implementation implications.**
- Treat agreement number as a grouping key for business group/batch per year, not as an agreement or partner identifier.
- Do not enforce global agreement-number uniqueness or `UNIQUE(partner_id, agreement_number_normalized)`.
- Preserve raw numbers and source provenance.
- Number-only lookup returns all candidates in the group; repeated numbers, including for the same partner, are not by themselves duplicates.
- Use NO ID and NO VA for partner identification, partner-uniqueness checks and partner-level duplicate detection, not agreement number.
- Preserve both independently; the decision does not reduce them to a composite key that permits either identifier to repeat.
- If they point to different partners, stage the conflict for review rather than silently merge.
- Internal immutable partner/agreement UUIDs remain technical keys; repeated imports, distinct agreements and document versions must be distinguished using the partner identifiers and verified source-record evidence, not agreement-number equality alone.

**Remaining implementation detail (not a reopening).** Identifier normalization, missing/conflicting identifier handling and the exact business-group/batch/year field mapping still need defined rules. Preserve letters, punctuation and leading zeroes; do not invent transformations, generate missing identifiers or automatically merge conflicts. This decision alone does not authorize production migration or financial posting.

---

## DEC-002 — Agreement lifecycle states and transitions

**Status:** `RESOLVED` — 2026-10-03 (labels confirmed; transition graph OPEN)
**Owner:** Process owner

**Observed.** UI-A offers `Belum Dibuat`, `Draft`, `Menunggu TTD Mitra`, `Menunggu TTD Perusahaan`, `Sudah Ditandatangani`, `Aktif`, `Selesai`. These mix document preparation/signing and contract lifecycle. No transitions were submitted. WB `MITRA !U1:V1` has `Kategori Koleksi` and `Kondisi Pinjaman`, but numeric codes have no inspected policy legend. WB `PERJANJIAN` has signing marks, not a lifecycle-state definition. The UI's 24-month renewal description is not proof every agreement has the same term.

**Proposal.** Lifecycle: `draft`, `active`, `paid_off`, `closed_by_rescheduling`, `cancelled`, plus `unknown` for unresolved imports. Keep the seven raw labels separately and move preparation/signing into DEC-003.

| Proposed transition | Guard |
|---|---|
| draft → active | Verified contract/partner, effective date, approved opening amounts, required signatures and authorized activation |
| draft → cancelled | Authorized reason; no posted financial effect |
| active → paid_off | Verified zero components under approved policy, resolved exceptions and authorized closure |
| active → closed_by_rescheduling | Approved successor, effective date and component treatment; no cyclic link or double-counted debt |
| Closed state → reopening | Exceptional owner-approved correction with history and compensating entries, not ordinary editing |

`Selesai` must not automatically mean paid off. A signed PDF does not automatically activate debt. Expiry/renewal eligibility should be a separate dated flag until defined.

**Until approved.** Preserve raw statuses; operational transitions fail with `NotApprovedException`. Read/stage evidence remains possible; synthetic tests can exercise the proposed graph.

**Confirm.** State meanings, `Selesai`, cancellation/expiry, activation, payoff, rescheduling and reopening evidence.

**Resolved ruling (2026-10-03).** Owner confirmed seven business labels: Belum dibuat, Draft, Menunggu TTD Mitra, Menunggu TTD Perusahaan, Sudah Ditandatangani, Aktif, Selesai. The initial list at 14:32 had six; Selesai was added at 14:33. These labels mix document preparation/signing and contract lifecycle — this is the owner's chosen model. The transition graph (which state can move to which, and what triggers each transition) is NOT answered. The activation trigger and what Selesai means are NOT answered. Selesai must not equal paid off. Source: `docs/master-compilation.md` §2 (DEC-002 row).

**Implementation implications (resolved portion).**
- Preserve all seven business labels as user-facing status values.
- Keep the internal lifecycle states (draft, active, paid_off, closed_by_rescheduling, cancelled, unknown) as system states for programmatic logic. The seven business labels are the presentation/workflow layer.
- The signing-related labels (Menunggu TTD Mitra, Menunggu TTD Perusahaan, Sudah Ditandatangani) overlap with DEC-003 signing workflow. They are part of the single owner-chosen label set, not a separate dimension in the owner's model.
- `Selesai` is a business lifecycle state distinct from `paid_off` (LUNAS in money terms). An agreement can be Selesai without having zero balance, and vice versa.

**Still OPEN.** Transition rules between labels, activation trigger, what makes an agreement Selesai, cancellation/expiry behavior, reopening from closed states.

---

## DEC-003 — Signing/document workflow states

**Status:** `RESOLVED` — 2026-10-03 (core requirement; detail questions OPEN)
**Owner:** Process owner

**Observed.** UI-A separately offers `Belum TTD`/`Sudah TTD` and the seven manual labels. WB `PERJANJIAN!E3:E225` contains 223 record rows: 157 `x` and 66 checkmarks (`√`). Row 226 is a summary row with a blank signature, not a borrower. The live UI shows 224 renewal entries, 157 unsigned and 67 signed. A summary-row inclusion is a candidate explanation for the one-row/one-signature difference, but backend inclusion was not established. PDF upload/save behavior was not tested.

**Proposal.** Document workflow: `not_prepared → draft → awaiting_partner_signature → awaiting_company_signature → signed`, plus unknown imports. Waiting states can return to draft with rejection/correction reason and version history. Signed documents are immutable versions; an amendment starts a new version/workflow. Partner-first order is a proposal, not established policy.

Keep a separate version-specific signature summary: `belum_ttd`, `sudah_ttd`, `unknown`. Upload alone is not proof of signature. Verify all required signatures on the same version before verified `sudah_ttd`. Keep raw workbook marks; blank stays unknown. Signing must not change receivable or lifecycle automatically.

**Until approved.** Allow staging/version metadata under approved access, but block operational signing transitions. Do not equate `x`/checkmark/blank to verified states without an approved mapping.

**Confirm.** Required signatories/order, parallel signing if allowed, completion evidence, mark meanings, rejection/replacement rules and the record-versus-summary population discrepancy.

**Resolved ruling (2026-10-03).** Owner confirmed: upload agreement document; record partner TTD (sudah / belum). Which entity holds the authoritative TTD fact, and document version handling, remain undefined. The seven business labels from DEC-002 include the signing-related labels (Menunggu TTD Mitra, Menunggu TTD Perusahaan, Sudah Ditandatangani), so signing workflow is part of the unified label set rather than a separate dimension. Source: `docs/master-compilation.md` §2 (DEC-003 row).

**Still OPEN.** Authoritative TTD entity, version handling, required signatories/order, completion evidence, rejection rules.

---

## DEC-004 — Sensitive-field unmasking policy

**Status:** `RESOLVED` — 2026-10-03
**Owner:** Process owner

**Observed.** Administrator UI headers/controls expose sensitive identity/contact/VA fields. WB `MITRA !G1:I1` identifies NIK/name/address; `MONITORING BULANAN!E3` is phone; `PERJANJIAN!A2,I2` are VA/full address. This establishes data sensitivity, not who is entitled to reveal it. Other role sessions and server checks were not inspected.

**Proposal.** Reviewer and Process owner are eligible for explicit case/purpose/field grants, not blanket unmasking by role. Operator, Auditor and technical admin remain masked/default-denied unless separately granted a justified exception. Separate permissions: `nik.reveal`, `phone.reveal`, `address.reveal`, `va.reveal`, `document.view`, `document.download`, `sensitive.export`. Log actor, purpose, target and time without logging revealed values. Masking must apply to API payloads/client state, not only visual display. Private PDFs need access-checked retrieval. Technical-admin privileges do not confer financial/data access.

**Until approved.** No real reveal/download/export grants follow from this proposal. Demonstrate with synthetic data.

**Confirm.** Field-by-role/purpose matrix, assignment scope/duration, document/export rights and necessary Operator/Auditor exceptions.

**Resolved ruling (2026-10-03).** Owner confirmed: every role except viewer may see sensitive fields. This simplifies the proposed granular per-field/per-purpose grant model. The viewer role is the only one denied access to sensitive data (NIK, phone, address, VA, documents). Source: `docs/master-compilation.md` §2 (DEC-004 row).

**Implementation implications.** The existing Permission enum's granular sensitive-field permissions (NikReveal, PhoneReveal, etc.) remain useful for logging and future tightening, but the default grant for non-viewer roles replaces the proposed deny-by-default model. Viewer access must remain masked/denied.

---

## DEC-005 — Second review of unmatched deposits

**Status:** `RESOLVED` — 2026-10-03
**Owner:** Process owner

**Observed.** UI-H labels unidentified records as requiring manual verification; UI-B accepts non-partner evidence. Neither proves independent second review. The workbook supplies source rows, not an approval workflow. PRD FR-04 leaves mandatory use open.

**Proposal.** Default mandatory maker-checker approval before an unmatched deposit becomes a posted allocation or disposition. A reviewer proposes the match/evidence; a different authorized reviewer approves the current case version. No self-approval. Merely annotating/rejecting a candidate without financial effect can be audited without posting approval. Confidence scores do not approve matches.

Configurability belongs to a versioned owner-approved policy, not an Operator switch. Relaxation requires explicit scope, thresholds, evidence and eligible reviewers. A missing second reviewer leaves the case queued; changes invalidate prior approval.

**Until approved.** Financial posting remains blocked even if the proposed mandatory default is configured.

**Confirm.** Is dual review universal for unmatched financial applications? Exact exceptions/thresholds and eligible independent reviewers.

**Resolved ruling (2026-10-03).** Owner confirmed: no approval system for now. TJSL cashier inputs directly. Keep audit trail and reversal capability. Stub future approval workflow for later implementation. This REVERSES the proposed mandatory maker-checker default. The cashier has direct posting authority without a second reviewer, but every action must be audited and reversible. Source: `docs/master-compilation.md` §2 (DEC-005 row).

---

## DEC-006 — ABT and fund concepts

**Status:** `RESOLVED IN PART` — 2026-10-03
**Owner:** Process owner

> **Correction history:** The PRD and earlier DEC docs treated ABT as overpayment. This is a misleading conflation. ABT (Angsuran Belum Teridentifikasi) = money received whose owner (mitra) is not identified. This is NOT overpayment/excess.

**Observed.** UI-B shows ABT and a raw non-partner deposit form. WB `ABT!A2:H2` has ID, VA, name, geography, sector, period, amount, but no offset/refund approval field. Its 19 populated rows total Rp4,989,600; the live screen shows Rp8,048,600/27 entries. These are different populations/snapshots, not a reconciliation. Period values mix strings and Excel dates. The UI still says latest `Des-2023` while later-period rows exist.

**Proposal.** Separate verified excess receipts from unidentified deposits. Both remain unapplied until verified/approved. Suggested flow: `unresolved → verified_unapplied → disposition_proposed → disposition_approved → executed`. Rejection returns to `verified_unapplied` with reason.

- Offset only to a named verified agreement under approved consent/allocation policy, with atomic linked allocation and unapplied-balance reduction. No silent cross-partner/successor offset.
- Refund only to verified recipient/account with approved amount and independent review. Actual settlement/reference, not approval alone, proves execution.
- Leave unapplied while evidence/instructions are missing. No automatic forfeiture, revenue recognition, write-off or accrual.

Prevent consuming the same funds twice; failed settlements stay unresolved. Corrections use reversals.

**Until approved.** Capture evidence/proposals only; disposition execution returns `NotApprovedException`. No automatic debt reduction.

**Confirm.** Offset consent/priority, refund evidence/authority/limits/fees, unidentified/aged funds and execution roles.

**Resolved ruling (2026-10-03).** Owner confirmed ABT is NOT overpayment. Four distinct concepts must be kept separate: Source: `docs/master-compilation.md` §3; `docs/formula-specification.md` §8.

1. **Raw receipt** — immutable bank record, no owner needed
2. **ABT (Angsuran Belum Teridentifikasi)** — money received whose owner is not identified. Parks without reducing any receivable.
3. **Identified but unallocated** — owner is known but money not yet applied to specific agreement(s)
4. **True excess** — owner known, money exceeds that partner's total remaining debt

Rules confirmed:
- ABT flow: raw receipt → ABT lot → identification (actor, time, evidence) → allocation to agreement(s). Identify then allocate only.
- No return, no refund, no delete. Unmatched ABT stays queued permanently.
- No approval step and no second reviewer for now. Cashier posts directly; keep audit trail and reversal.
- Excess flows to other partners, not back. Target selection rule unanswered.
- Owner mentioned ABT for both partners and non-partners. Non-partner depositor implementation not defined.

**Current code state (to fix).** The `Overpayment` model conflates ABT with excess (labels receipt-minus-components as ABT inside `Overpayment`). `StorePaymentRequest` requires partner and agreement, so a truly unidentified deposit cannot be captured. No ABT-specific route exists. `Overpayment::executeDisposition()` describes offset/refund and throws. These must be corrected per the four-concept model. See `docs/formula-specification.md` §8 for the full ABT money flow.

**Still OPEN.** Excess target choice (DP-8), non-partner depositor workflow, offset consent/priority rules.

---

## DEC-007 — Collectibility classification levels

**Status:** `RESOLVED` — 2026-10-03 (labels and month-expressed bands FINAL; DP-2 month-count semantics OPEN)
**Owner:** Process owner

**Observed.** UI-C offers `Lunas`, `Lancar`, `Kurang Lancar`, `Bermasalah`. Live Data Mitra counts are 913/5/160/223, summing to 1,301 versus total 1,302. WB `PIUTANG!L2:L1302` has 894 Lunas, 6 Lancar, 200 Kurang Lancar, 201 Bermasalah. `PIUTANG_COBA` has the same counts after case-only normalization but includes one uppercase BERMASALAH. Monitoring also mixes case variants and has one missing category. WB `MITRA !U` uses 0/1/2/5 and missing codes without an inspected legend. No arrears thresholds were supplied.

**Proposal.** Preserve raw four-category labels with provenance/as-of and unknown. Do not adopt `current/special_mention/substandard/doubtful/loss`: this five-level scheme was not observed or approved. For independent risk classification propose `lancar`/`kurang_lancar`/`bermasalah`/`unknown`, criteria pending. Treat legacy `Lunas` as a reported settlement label, not proof of lifecycle `paid_off`. If staff require it in the display, define an approved reporting projection. Case normalization can support search but must not erase raw values.

**Until approved.** Import raw classifications, no automatic reassignment or invented 30/90/180-day rules. Missing is `unknown`, not healthy. Reconcile populations separately; do not use these counts as production targets.

**Confirm.** Labels/code mapping, criteria/schedule/overdue cutoffs, override authority, settled/rescheduled treatment and source precedence.

**Resolved ruling (2026-10-03).** Owner confirmed FINAL five-label mapping with month-expressed bands. This supersedes the provisional four-label variant and the code's Current/Substandard/Loss/Unknown enum. Source: `docs/master-compilation.md` §5; `docs/formula-specification.md` §9.

| Late months | Label |
|---|---|
| 0 to 1 | Lancar |
| 2 to 6 | Kurang Lancar |
| 7 to 9 | Diragukan (Ragu-ragu) |
| over 9 | Bermasalah (Macet) |
| balance = 0 | LUNAS (override) |

Implementation rules:
- Make labels and bands data-driven via a versioned rule table, with the confirmed five-label mapping as the active version.
- Current code `CollectibilityStatus` enum has Current/Substandard/Loss/Unknown — needs Lancar, KurangLancar, Diragukan, Bermasalah, and Lunas override. Never turn Unknown into Lunas.
- `Lunas` currently exists only as lifecycle `PaidOff`. LUNAS (collectibility, money = zero balance) is separate from agreement status Selesai (business lifecycle).
- Empty/unknown collectibility must never become Lunas (legacy bug to avoid).
- The label function from `docs/formula-specification.md` §9.4 applies.
- PRT 0010 day bands (30/180/270 calendar days) are recorded in the formula spec §9.3 for reference but the owner's month-expressed bands take precedence.

**Still OPEN (DP-2).** Exact month-count semantics: missed installment months vs elapsed months since oldest unpaid; partial/non-consecutive handling and boundaries. Bands are FINAL; counting algorithm is OPEN. See `docs/formula-specification.md` §9 and DP-2.

---

## DEC-008 — Balance calculation rules/formulas

**Status:** `RESOLVED IN PART` — 2026-10-03
**Owner:** Process owner

**Observed.** UI-P shows principal plus combined Bunga / Administrasi and separate remaining components; HTML minimum/step values do not prove policy. WB `PIUTANG!E1:R1` has multiple original/remaining/problematic components, not an accrual rule. No formula cells were found in PIUTANG or PIUTANG_COBA. WB `PERJANJIAN!K2:M2` explicitly says Pokok, Jasa admin, Total, whereas the screen calls an aggregate Bunga. Its formulas K226/L226/M226 are SUM totals only, not interest policy. Do not silently relabel administration as interest.

**Proposal.** Keep rates/accrual/fees/penalties unavailable until approved. Show source snapshot values as observed/unverified, not computed certified debt. Proposed ledger identity for review:

```text
component balance = approved opening component
                  + signed approved adjustments and approved accrued charges
                  - net posted allocations effective by the as-of date
net allocations = original allocations - linked allocation reversals
agreement total = sum of separately approved receivable components
```

Distinguish interest/admin/penalties. Do not count allocation reversals again as positive adjustments. Unmatched funds/ABT are separate, not automatically netted against debt. Negative balance is an exception, not silently clamped. Rescheduling must not double-count predecessor/successor amounts. Return policy/source version, as-of date, events and warnings. Fractional computation/rounding needs explicit policy even if storage uses integer IDR.

**Until approved.** `balance()` returns component `unverified` / `not_available`; missing is not zero. Synthetic arithmetic can test invariants, not establish rates.

**Confirm.** Authoritative source/components, rates, flat/effective/accrual basis, admin/penalties, schedule, allocation order, rounding, backdating, restructuring/reversals and Jasa admin versus UI Bunga mapping.

**Resolved ruling (2026-10-03).** Owner confirmed the following balance and formula rules: Source: `docs/master-compilation.md` §2, §4; `docs/formula-specification.md` §1–§7.

- **Tenor:** 2-year (24 months) default for new agreements. Store per agreement because legacy cohorts include 7 to 8 month rows.
- **No late penalty.** `late_fee = 0` always. `other_charge` must not become a late fee.
- **Allocation order:** Admin/bunga first, then pokok (principal). See `docs/formula-specification.md` §6 for the allocation algorithm.
- **Bunga and admin are manual inputs** by TJSL staff. No rate engine. No 3% flat rate, no 0.5%/month annuity. The interest-rate conflict is moot. Do not generate charges from cohort resemblance.
- **Excess goes to other partners**, not back. No return or refund of any money.
- **No return or refund** of any money.
- **Money is integer Rupiah (BIGINT).** No floats. Never use `max(x,0)` to hide a negative.

See `docs/formula-specification.md` for complete formula specification including: balance formulas (§3), schedule/due dates (§4), allocation algorithm (§6), excess handling (§7), ABT flow (§8), collectibility function (§9), and test checklist (§13).

**Still OPEN.** Formula-level decision points DP-1 through DP-10 (see Formula Decision Points appendix below and `docs/formula-specification.md` §12). `BalanceService` continues to return `unverified` until DPs are resolved and balance computation is implemented.

---

## DEC-009 — RBAC roles and permissions

**Status:** `RESOLVED` — 2026-10-03
**Owner:** Process owner

**Observed.** Only Administrator visibility was examined; controls are not proof of authorization. The workbook has no staff permission matrix. PRD proposes five roles and denies automatic financial authority to technical admin.

**Proposal.** Implement action-level, object-scoped deny-by-default policies from Phase A. The following is eligibility, not active permission; every allowed cell needs approval and an explicit grant. Scoped means assigned data/purpose. Approval excludes self-approval.

| Action | Operator | Reviewer | Owner | Auditor | Admin |
|---|---|---|---|---|---|
| Masked business view | Scoped | Scoped | Scoped | Scoped | Deny |
| Stage evidence/payment/agreement draft | Scoped | Scoped | Deny | Deny | Deny |
| Propose match/allocation/disposition | Scoped | Scoped | Deny | Deny | Deny |
| Approve allocation/reversal/ABT | Deny | Approved limits | Approved limits | Deny | Deny |
| Activate/close agreement | Deny | Recommend | Approve | Deny | Deny |
| Define financial/source policy | Deny | Recommend | Approve | Read | Configure approved policy |
| Sensitive reveal/PDF | Deny | DEC-004 grant | DEC-004 grant | Deny | Deny |
| Scoped export | Explicit grant | Explicit grant | Explicit grant | Explicit grant | Deny |
| Audit history | Own actions | Cases | Oversight | Audit scope | Technical logs |
| Users/config/backups | Deny | Deny | Approve business grants | Deny | Approved admin scope |
| Physical financial/audit delete | Deny | Deny | Deny | Deny | Deny in app |

Transactional service posting requires approved current evidence, not a generic write grant. Multiple roles cannot defeat independence. Apply policies to API, jobs, export and file retrieval. Admin must not self-grant financial/sensitive authority; maintenance is separately approved/logged.

**Until approved.** Real capability grants default empty; existing access requires separate grounding. Synthetic personas test denied as well as permitted paths. Do not defer granular checks behind coarse gates.

**Confirm.** Actual staff assignments/scopes, delegations/thresholds, export rights, approval powers and security administration process.

**Resolved ruling (2026-10-03).** Owner confirmed five named roles: Source: `docs/master-compilation.md` §2 (DEC-009 row).

| # | Confirmed Role (Business) | Internal Name (Code) | Job |
|---|---|---|---|
| 1 | Viewer | Auditor | Read-only access, reproduce as-of figures, scoped exports |
| 2 | Kasir TJSL | Operator | Search partners, record evidence-backed payments, manage agreement workflow |
| 3 | Kepala Sub Divisi | ReconciliationReviewer | Resolve identity/agreement matches, approve allocations and exceptions |
| 4 | Sekper / Kepala Divisi | ProcessOwner | Set source precedence, approve opening balances, policy versions, cutover |
| 5 | System Admin | SystemAdmin | Users, config, backups. No implicit authority to approve financial postings |

**Current release scope:** viewer, kasir TJSL, and system admin only. The two oversight roles (Kepala Sub Divisi, Sekper/Kepala Divisi) are reserved for later phases.

**Implementation note.** Current `Role.php` uses internal names (Operator, ReconciliationReviewer, etc.). The `label()` method should be updated to return the confirmed business names. The proposed granular permission matrix table from the PROPOSED section remains a reasonable implementation guideline.

---

## DEC-010 — Payment period derivation and override

**Status:** `RESOLVED` — 2026-10-03
**Owner:** Process owner

**Observed.** UI-P defaults to 30-09-2026, Sep, 2026; month/date synchronization was not tested. UI-H includes source labels 03.26 and 13.26 through 21.26, not valid calendar-month names. WB proves the distinction: `03.26!D3=2008-12-01`, `F3=2009-01-01`, `AB3=2009-12-01`; `13.26!H1=2013-05-01`; `21.26!N1=2021-06-01`. Month columns have separate Pokok/Bunga subheaders. Thus sheet labels are provenance/grouping labels, not safely March-2026 etc. Their full naming convention still needs confirmation. Workbook also contains `08.26 `, which was absent from the inspected live selector.

**Proposal.** Store immutable receipt date/time/precision/timezone; derive `receipt_month` YYYY-MM from receipt date in the approved reporting zone. Asia/Jakarta is a prototype proposal, not bank policy confirmation. Store raw `source_sheet_label` separately. Due-installment and accounting posting periods, if needed, are distinct fields, not rewrites of receipt facts. A date-only receipt stays date-only, not invented UTC midnight.

Default receipt month is a preview/server-checked derivation. Any proposed accounting/due-period override stores reason/evidence/reviewer; closed-period/backdating rules must be approved. Existing inconsistencies stay raw in staging, not silently corrected.

**Until approved.** Overrides/mismatches cannot post under an unapproved policy. Do not parse sheet labels as dates or use them as payment months in charts.

**Confirm.** Meaning of period, timezone/bank cutoff, override authority, closed periods, sheet-label convention and missing 08.26 UI option.

**Resolved ruling (2026-10-03).** Owner confirmed: period derived from receipt date. Monthly history runs from loan start. A month with no payment shows Rp 0 and status "Belum Bayar". These Rp 0 rows are display only — never fabricate zero-value transaction records. Source: `docs/master-compilation.md` §2 (DEC-010 row); `docs/formula-specification.md` §4.

**Still OPEN.** History end rule (how far into the future to show), oldest-first display ordering, and the distinction between receipt month and liability month (see `docs/formula-specification.md` §4).

---

## DEC-011 — Bank-reference uniqueness scope

**Status:** `RESOLVED` — 2026-10-03
**Owner:** Process owner

**Observed.** UI-P/UI-B have no explicit bank-reference input. WB `REKENING_GIRO` is an empty A1 sheet in this supplied copy; inspected payment/source headers do not establish a provider reference namespace. No bank statement/provider identifier guarantee was reviewed. Similar payment rows cannot prove duplicate transfers.

**Proposal.** Immutable transaction UUID; preserve raw reference. Define `reference_namespace` as verified bank/provider + receiving account/ledger + identifier type. For nonempty verified references, propose unique `(reference_namespace_id, reference_normalized)` only after the bank's guarantee is confirmed. Include a documented reuse window only if the provider actually reuses references. No guessed daily/yearly reset.

The supplied `(source, reference)` is safe only if source means stable financial namespace, not filename/import job/sheet. Otherwise the same transfer across two files escapes detection. Global reference uniqueness can wrongly merge accounts/providers.

Separate source-row idempotency from financial duplicate detection: stable snapshot hash + row identity prevents reprocessing that row irrespective of parser version; record parser version as provenance, not a way to bypass duplicate posting. Cross-file bank identity checks remain necessary. Missing reference is nullable, not empty or fabricated. NO ID and NO VA identify partners under DEC-001; neither is a unique bank-transaction reference. Multiple receipts may belong to the same partner/VA. Agreement number is a business-group/batch-per-year grouping key, not a transaction identifier. Amount/date/payer/VA fingerprints suggest transaction-duplicate candidates only; collisions/conflicting amounts require review, not silent merge.

**Until approved.** Stage evidence, no financial posting or guessed production unique constraint. Synthetic namespace cases can test overlap, retries and collisions.

**Confirm.** Official sources/accounts, identifier format/reuse guarantee, normalization and treatment of overlapping imports/reference-free deposits.

**Resolved ruling (2026-10-03).** Owner confirmed: VA Number is unique per partner (owner). The register interprets bank reference as unique within a partner scope. Reference scope per bank/account is an interpretation — confirm with bank documentation. Source: `docs/master-compilation.md` §2 (DEC-011 row).

**Implementation note.** Do not enforce UNIQUE(partner_id, VA) as a database constraint (per ERD review: one VA could identify several partners in edge cases). Instead, treat VA uniqueness per partner as a business validation rule that flags violations for review.

---

## Formula Decision Points (from `docs/formula-specification.md`)

These decision points govern formula-level implementation choices. Each has a stated default that may be built; if the owner overrides a default, update the status and default here. Full descriptions are in `docs/formula-specification.md` §12.

| DP | Question | Default until answered | Status |
|----|----------|------------------------|--------|
| DP-1 | Bunga and jasa admin: one charge or two? Priority in partial payment? | One component C, admin-first, priority stored as data | `DEFAULT_ACTIVE` |
| DP-2 | Exact month-count semantics: missed installment months vs elapsed months since oldest unpaid; partial/non-consecutive handling and boundaries? | Month-expressed bands FINAL. Counting algorithm OPEN | `OPEN` |
| DP-3 | First due date and installment numbering; fixed due day rule? | Store explicit `first_due_date`, do not infer | `DEFAULT_ACTIVE` |
| DP-4 | Monthly dues: entered/imported vs divided from totals? | Entered or imported schedule; division fallback with remainder on last installment | `DEFAULT_ACTIVE` |
| DP-5 | Cashier enters one amount or components? | One amount | `DEFAULT_ACTIVE` |
| DP-6 | Application order across installments; prepayment allowed? | Oldest due first | `DEFAULT_ACTIVE` |
| DP-7 | Excess = beyond total partner debt, or beyond current installment? | Beyond total remaining debt | `DEFAULT_ACTIVE` |
| DP-8 | Who picks excess target partner/agreement, by what rule? | Park the lot, no auto-pick | `DEFAULT_ACTIVE` |
| DP-9 | Rescheduled agreements: does earlier lateness carry over? | New agreement judged on its own schedule | `DEFAULT_ACTIVE` |
| DP-10 | Migration opening basis: contract amounts or outstanding at cutoff? | Not Phase A runtime; never both | `NOT_PHASE_A` |

**Note on `DEFAULT_ACTIVE`.** These defaults are proposals recommended for the build. They are implemented behind a `rule_version` field so they can change when the owner decides. `OPEN` means the default is insufficient — the counting algorithm must be explored and defined before the collectibility function can work correctly.
