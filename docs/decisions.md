# Decisions log

Every `OPEN` item needs an owner before Phase B can begin (PRD §9, Phase B
gate). Do not delete resolved rows — mark them `RESOLVED` with the date and
the decision actually made, so the trail stays auditable.

## Summary

| ID | Question | Status | Owner |
|----|----------|--------|-------|
| DEC-001 | Agreement-number uniqueness scope | `RESOLVED` 2026-09-30 | Process owner |
| DEC-002 | Agreement lifecycle states and transitions | `PROPOSED` | Process owner |
| DEC-003 | Signing/document workflow states | `PROPOSED` | Process owner |
| DEC-004 | Sensitive-field unmasking policy | `PROPOSED` | Process owner / System admin |
| DEC-005 | Second review of unmatched deposits | `PROPOSED` | Process owner |
| DEC-006 | ABT/overpayment disposition policy | `PROPOSED` | Process owner |
| DEC-007 | Collectibility classification levels | `PROPOSED` | Process owner |
| DEC-008 | Balance calculation rules/formulas | `PROPOSED` | Process owner |
| DEC-009 | Granular RBAC permission matrix | `PROPOSED` | Process owner / System admin |
| DEC-010 | Payment period derivation and override | `PROPOSED` | Process owner |
| DEC-011 | Bank-reference uniqueness scope | `PROPOSED` | Process owner |

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

**Status:** `PROPOSED` — awaiting process-owner confirmation
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

---

## DEC-003 — Signing/document workflow states

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-A separately offers `Belum TTD`/`Sudah TTD` and the seven manual labels. WB `PERJANJIAN!E3:E225` contains 223 record rows: 157 `x` and 66 checkmarks (`√`). Row 226 is a summary row with a blank signature, not a borrower. The live UI shows 224 renewal entries, 157 unsigned and 67 signed. A summary-row inclusion is a candidate explanation for the one-row/one-signature difference, but backend inclusion was not established. PDF upload/save behavior was not tested.

**Proposal.** Document workflow: `not_prepared → draft → awaiting_partner_signature → awaiting_company_signature → signed`, plus unknown imports. Waiting states can return to draft with rejection/correction reason and version history. Signed documents are immutable versions; an amendment starts a new version/workflow. Partner-first order is a proposal, not established policy.

Keep a separate version-specific signature summary: `belum_ttd`, `sudah_ttd`, `unknown`. Upload alone is not proof of signature. Verify all required signatures on the same version before verified `sudah_ttd`. Keep raw workbook marks; blank stays unknown. Signing must not change receivable or lifecycle automatically.

**Until approved.** Allow staging/version metadata under approved access, but block operational signing transitions. Do not equate `x`/checkmark/blank to verified states without an approved mapping.

**Confirm.** Required signatories/order, parallel signing if allowed, completion evidence, mark meanings, rejection/replacement rules and the record-versus-summary population discrepancy.

---

## DEC-004 — Sensitive-field unmasking policy

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owners:** Process owner / System admin

**Observed.** Administrator UI headers/controls expose sensitive identity/contact/VA fields. WB `MITRA !G1:I1` identifies NIK/name/address; `MONITORING BULANAN!E3` is phone; `PERJANJIAN!A2,I2` are VA/full address. This establishes data sensitivity, not who is entitled to reveal it. Other role sessions and server checks were not inspected.

**Proposal.** Reviewer and Process owner are eligible for explicit case/purpose/field grants, not blanket unmasking by role. Operator, Auditor and technical admin remain masked/default-denied unless separately granted a justified exception. Separate permissions: `nik.reveal`, `phone.reveal`, `address.reveal`, `va.reveal`, `document.view`, `document.download`, `sensitive.export`. Log actor, purpose, target and time without logging revealed values. Masking must apply to API payloads/client state, not only visual display. Private PDFs need access-checked retrieval. Technical-admin privileges do not confer financial/data access.

**Until approved.** No real reveal/download/export grants follow from this proposal. Demonstrate with synthetic data.

**Confirm.** Field-by-role/purpose matrix, assignment scope/duration, document/export rights and necessary Operator/Auditor exceptions.

---

## DEC-005 — Second review of unmatched deposits

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-H labels unidentified records as requiring manual verification; UI-B accepts non-partner evidence. Neither proves independent second review. The workbook supplies source rows, not an approval workflow. PRD FR-04 leaves mandatory use open.

**Proposal.** Default mandatory maker-checker approval before an unmatched deposit becomes a posted allocation or disposition. A reviewer proposes the match/evidence; a different authorized reviewer approves the current case version. No self-approval. Merely annotating/rejecting a candidate without financial effect can be audited without posting approval. Confidence scores do not approve matches.

Configurability belongs to a versioned owner-approved policy, not an Operator switch. Relaxation requires explicit scope, thresholds, evidence and eligible reviewers. A missing second reviewer leaves the case queued; changes invalidate prior approval.

**Until approved.** Financial posting remains blocked even if the proposed mandatory default is configured.

**Confirm.** Is dual review universal for unmatched financial applications? Exact exceptions/thresholds and eligible independent reviewers.

---

## DEC-006 — ABT/overpayment disposition policy

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-B shows ABT and a raw non-partner deposit form. WB `ABT!A2:H2` has ID, VA, name, geography, sector, period, amount, but no offset/refund approval field. Its 19 populated rows total Rp4,989,600; the live screen shows Rp8,048,600/27 entries. These are different populations/snapshots, not a reconciliation. Period values mix strings and Excel dates. The UI still says latest `Des-2023` while later-period rows exist.

**Proposal.** Separate verified excess receipts from unidentified deposits. Both remain unapplied until verified/approved. Suggested flow: `unresolved → verified_unapplied → disposition_proposed → disposition_approved → executed`. Rejection returns to `verified_unapplied` with reason.

- Offset only to a named verified agreement under approved consent/allocation policy, with atomic linked allocation and unapplied-balance reduction. No silent cross-partner/successor offset.
- Refund only to verified recipient/account with approved amount and independent review. Actual settlement/reference, not approval alone, proves execution.
- Leave unapplied while evidence/instructions are missing. No automatic forfeiture, revenue recognition, write-off or accrual.

Prevent consuming the same funds twice; failed settlements stay unresolved. Corrections use reversals.

**Until approved.** Capture evidence/proposals only; disposition execution returns `NotApprovedException`. No automatic debt reduction.

**Confirm.** Offset consent/priority, refund evidence/authority/limits/fees, unidentified/aged funds and execution roles.

---

## DEC-007 — Collectibility classification levels

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-C offers `Lunas`, `Lancar`, `Kurang Lancar`, `Bermasalah`. Live Data Mitra counts are 913/5/160/223, summing to 1,301 versus total 1,302. WB `PIUTANG!L2:L1302` has 894 Lunas, 6 Lancar, 200 Kurang Lancar, 201 Bermasalah. `PIUTANG_COBA` has the same counts after case-only normalization but includes one uppercase BERMASALAH. Monitoring also mixes case variants and has one missing category. WB `MITRA !U` uses 0/1/2/5 and missing codes without an inspected legend. No arrears thresholds were supplied.

**Proposal.** Preserve raw four-category labels with provenance/as-of and unknown. Do not adopt `current/special_mention/substandard/doubtful/loss`: this five-level scheme was not observed or approved. For independent risk classification propose `lancar`/`kurang_lancar`/`bermasalah`/`unknown`, criteria pending. Treat legacy `Lunas` as a reported settlement label, not proof of lifecycle `paid_off`. If staff require it in the display, define an approved reporting projection. Case normalization can support search but must not erase raw values.

**Until approved.** Import raw classifications, no automatic reassignment or invented 30/90/180-day rules. Missing is `unknown`, not healthy. Reconcile populations separately; do not use these counts as production targets.

**Confirm.** Labels/code mapping, criteria/schedule/overdue cutoffs, override authority, settled/rescheduled treatment and source precedence.

---

## DEC-008 — Balance calculation rules/formulas

**Status:** `PROPOSED` — awaiting process-owner confirmation
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

---

## DEC-009 — Granular RBAC permission matrix

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owners:** Process owner / System admin

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

---

## DEC-010 — Payment period derivation and override

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-P defaults to 30-09-2026, Sep, 2026; month/date synchronization was not tested. UI-H includes source labels 03.26 and 13.26 through 21.26, not valid calendar-month names. WB proves the distinction: `03.26!D3=2008-12-01`, `F3=2009-01-01`, `AB3=2009-12-01`; `13.26!H1=2013-05-01`; `21.26!N1=2021-06-01`. Month columns have separate Pokok/Bunga subheaders. Thus sheet labels are provenance/grouping labels, not safely March-2026 etc. Their full naming convention still needs confirmation. Workbook also contains `08.26 `, which was absent from the inspected live selector.

**Proposal.** Store immutable receipt date/time/precision/timezone; derive `receipt_month` YYYY-MM from receipt date in the approved reporting zone. Asia/Jakarta is a prototype proposal, not bank policy confirmation. Store raw `source_sheet_label` separately. Due-installment and accounting posting periods, if needed, are distinct fields, not rewrites of receipt facts. A date-only receipt stays date-only, not invented UTC midnight.

Default receipt month is a preview/server-checked derivation. Any proposed accounting/due-period override stores reason/evidence/reviewer; closed-period/backdating rules must be approved. Existing inconsistencies stay raw in staging, not silently corrected.

**Until approved.** Overrides/mismatches cannot post under an unapproved policy. Do not parse sheet labels as dates or use them as payment months in charts.

**Confirm.** Meaning of period, timezone/bank cutoff, override authority, closed periods, sheet-label convention and missing 08.26 UI option.

---

## DEC-011 — Bank-reference uniqueness scope

**Status:** `PROPOSED` — awaiting process-owner confirmation
**Owner:** Process owner

**Observed.** UI-P/UI-B have no explicit bank-reference input. WB `REKENING_GIRO` is an empty A1 sheet in this supplied copy; inspected payment/source headers do not establish a provider reference namespace. No bank statement/provider identifier guarantee was reviewed. Similar payment rows cannot prove duplicate transfers.

**Proposal.** Immutable transaction UUID; preserve raw reference. Define `reference_namespace` as verified bank/provider + receiving account/ledger + identifier type. For nonempty verified references, propose unique `(reference_namespace_id, reference_normalized)` only after the bank's guarantee is confirmed. Include a documented reuse window only if the provider actually reuses references. No guessed daily/yearly reset.

The supplied `(source, reference)` is safe only if source means stable financial namespace, not filename/import job/sheet. Otherwise the same transfer across two files escapes detection. Global reference uniqueness can wrongly merge accounts/providers.

Separate source-row idempotency from financial duplicate detection: stable snapshot hash + row identity prevents reprocessing that row irrespective of parser version; record parser version as provenance, not a way to bypass duplicate posting. Cross-file bank identity checks remain necessary. Missing reference is nullable, not empty or fabricated. NO ID and NO VA identify partners under DEC-001; neither is a unique bank-transaction reference. Multiple receipts may belong to the same partner/VA. Agreement number is a business-group/batch-per-year grouping key, not a transaction identifier. Amount/date/payer/VA fingerprints suggest transaction-duplicate candidates only; collisions/conflicting amounts require review, not silent merge.

**Until approved.** Stage evidence, no financial posting or guessed production unique constraint. Synthetic namespace cases can test overlap, retries and collisions.

**Confirm.** Official sources/accounts, identifier format/reuse guarantee, normalization and treatment of overlapping imports/reference-free deposits.
