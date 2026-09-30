# PRD - TJSL partner installment and receivables system

**Date:** 29 September 2026
**Audience:** Claude Code working in the existing Laravel repository.
**Status:** Build-ready for an **isolated prototype with synthetic data only**. Financial rules, real-data migration and production use are **not approved**

---

## 0. How to use this document (read first)

1. Build Phase A (section 9) now. Everything else is blocked on staff decisions.
2. **Never guess a financial rule.** If code needs a formula, rounding rule, status transition or approval threshold that is not stated here, stop, add it to `/docs/decisions.md` as OPEN with an owner, and implement only an interface or stub that fails loudly (`NotApprovedException` or equivalent).
3. Inspect the repository before creating files. Existing `CLAUDE.md`, `.mcp.json`, `.claude/` settings, hooks, rules and agents must not be overwritten or recreated.
4. Keep a change log with three labels: **implemented**, **stubbed**, **blocked**.
5. Do not paste real workbook rows or personal data into any prompt, test, seed, log or screenshot.

## 1. Objective and hard boundaries

Replace the existing TJSL Shiny app and staff workbook workflow for partner-loan installments and receivables with a system where **every displayed amount traces to a partner, agreement, transaction, allocation, source and effective date**. It must let staff review messy data without silently turning it into official debt.

**Success measure:** a reviewer can reproduce an as-of receivable per agreement from approved opening balance, authorized changes and linked allocations; find unlinked transactions without booking them; and explain any variance against a named workbook snapshot.

**Hard boundaries**

- Do not infer payoff from a `LUNAS` label, a person from a name match, or move payments between agreements automatically.
- `closed_by_rescheduling` is never `paid_off`.
- Do not hard-code any legacy number (counts, totals, percentages) as a requirement or acceptance target.
- Synthetic data only: no real names, NIK, phones, VA numbers, bank references or contract PDFs anywhere.

## 2. Technical stack (reported by the project owner, not independently verified)

- PHP 8.2.22, Laravel 12.69.2, Composer 2.7.7, Node 22.21.1, npm 10.9.4.
- Inertia.js v2, React 19, TypeScript, Tailwind CSS v4.
- Database: **TiDB** via MySQL-compatible interface, dev database `installment_app`. A local MySQL 9 client/server may exist but is not the target.
- Tests: Pest 3.8 (setup snapshot reported 27 passing tests; this is not evidence any requirement here is implemented).
- Laravel Boost is installed; use `php artisan boost:mcp` tooling as configured.

**Architecture rules**

- Laravel owns validation, authorization (policies, Form Requests), state transitions, transactions, balances and metrics. React only renders and previews; the server recomputes every submitted total. Never trust IDs or totals from the browser.
- Domain rules live in a tested service layer, not in controllers or React.
- Money: integer rupiah (or exact fixed-point). No floats.
- Identifiers (partner NO ID, NIK, VA, agreement number, bank reference): stored as **strings**, leading zeroes preserved. Keep raw value and normalized lookup key in separate columns. Exact-match comparison must not be defeated by case-insensitive collation; verify on TiDB.
- Writes: idempotency keys, optimistic concurrency (version number), DB transactions and appropriate locking. Verify unique constraints, collation, isolation and locking **on TiDB**, not assumed from MySQL.
- Imports and exports run through queues. PDFs go in private storage with short-lived, access-checked downloads.
- Server-side paging everywhere (history is six-figure scale).
- Keep schema and queries DB-neutral where practical. **Do not promise Oracle portability**: Oracle is not a first-party Laravel 12 driver; `yajra/laravel-oci8` is third-party and would need version-compatibility and full migration/invariant testing if IT requires Oracle.
- Pin versions in manifests and lockfiles. Do not claim IT has approved them.
- Generate an OpenAPI spec (or typed equivalent) and test it.

## 3. Roles (scaffold only; real matrix is OPEN)

| Role                    | Job                                                                             |
| ----------------------- | ------------------------------------------------------------------------------- |
| Operator                | Search partners, record evidence-backed payments, manage agreement workflow     |
| Reconciliation reviewer | Resolve identity/agreement matches, approve allocations and exceptions          |
| Process owner           | Set source precedence, approve opening balances, policy versions, cutover       |
| Auditor                 | Read-only, reproduce as-of figures, scoped exports                              |
| System admin            | Users, config, backups. **No implicit authority to approve financial postings** |

Implement RBAC with **deny-by-default policy checks at API level**. Sensitive fields (NIK, phone, address, VA, documents) are masked by default; who may unmask is OPEN.

## 4. Domain model and invariants

Use UUIDs as internal keys. Entity names may change; relationships must not.

| Entity                         | Purpose / key rules                                                                                                                                                  |
| ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Partner`                      | Official `partner_no_id` (nullable in staging only; unique once verified), verification state, provenance. A display row number is never a NO ID.                    |
| `PartnerAlias`                 | Raw name, normalized search name, source, reviewer, state. Same name does not mean same person.                                                                      |
| `VirtualAccount`               | Exact string, provider, partner/agreement link, validity window, evidence. No assumption of global or timeless uniqueness.                                           |
| `Agreement`                    | Raw and normalized agreement number, partner, application/effective dates, principal and charge components, lifecycle state, approved source. Uniqueness scope OPEN. |
| `AgreementTransition`          | Predecessor/successor, type (amendment, rescheduling, closure, reversal), effective date, approved amounts, documents. **Graph must be acyclic.**                    |
| `InstallmentSchedule`          | Due dates and components per agreement/policy version. Calculation blocked until policy approved.                                                                    |
| `BankTransaction`              | Immutable raw record: reference, datetime + zone, integer IDR amount, payer/VA, source, fingerprint, state. Reference uniqueness is contextual.                      |
| `PaymentAllocation`            | Transaction to agreement with principal/interest/administration/other amounts, effective date, evidence, approver, version. Corrections are compensating entries.    |
| `ReceivableAdjustment`         | Opening balance, correction, write-off, transfer, reversal with reason, approvals, evidence. Never invent adjustments to force zero variance.                        |
| `Overpayment` (ABT)            | Transaction link, nullable partner, unapplied amount, proposed disposition, status, approval. Non-partner deposits stay unresolved until matched.                    |
| `AgreementDocument`            | Versioned private file ref, checksum, MIME, uploader, access log. Upload is not proof of signature.                                                                  |
| `SourceSnapshot` / `SourceRow` | File hash, as-of date, sheet, cell coordinates, raw values, formula text vs cached value, hidden flag, parser version. Immutable.                                    |
| `ReconciliationCase`           | Candidate links, discrepancy type, evidence, decisions, status. No implicit financial effect.                                                                        |
| `AuditEvent`                   | Actor, time, target, action, delta, reason, correlation ID. Append-only; sensitive data masked.                                                                      |
| `MetricDefinition`             | Versioned name, formula/scope, numerator/denominator, date semantics, owner approval.                                                                                |

**Invariants (enforced by DB constraints and transactions, not only forms)**

1. Raw deposits and component amounts are non-negative; posted payments are strictly positive.
2. Allocation components sum to the allocated portion; allocated + unapplied/ABT ≤ effective transaction amount.
3. A posting targets a **verified** agreement and an approved identity.
4. Corrections are dated reversals/adjustments with actor and reason. No physical delete of financial records in normal use.
5. The same approved source row is never posted twice.
6. Balances never mix agreements or unapproved snapshots.
7. A negative receivable is an explicit exception, never clamped to zero.
8. Three independent status dimensions: **lifecycle** (incl. `closed_by_rescheduling`, `paid_off`), **collectibility** (risk as of a date), **signing/document workflow**. Changing one never changes another or a balance.

**Balance interface (signature only; no formula until approved):**
`balance(agreementId, asOf, approvedPolicyVersion)` returns principal, each charge, total, included event IDs and warnings. If a definition or approval is missing, return `unverified` / `not available`, never a plausible zero.

## 5. Functional requirements

Every requirement needs: API-level authorization, audit/provenance, loading/empty/error states and accessible labels.

### P0: integrity and core workflows

- **FR-01 Partner registry.** Search by official NO ID (leading zeroes intact), alias/exact name, agreement number, permitted VA. Same-name hits show as separate candidates, never auto-merged. Staging records without NO ID cannot be posted to. Show verification/confidence badges (text, not color alone).
- **FR-02 Agreement timeline.** Show each agreement's dates, amounts, documents, statuses, predecessor/successor links and as-of components. Draft agreements create no debt; activation needs approval and source. Reject cyclic transitions and cross-partner links without exceptional approval.
- **FR-03 Payment capture.** Operator selects verified partner **and specific agreement**, enters receipt date, bank reference, payer/VA, evidence, and separate principal / interest / administration components. Form shows component sum and current balance **with as-of timestamp**. Server rejects zero/negative amounts, conflicting idempotency key, unauthorized posting, invalid date/period, over-allocation, duplicate fingerprint/reference. States: draft, submitted, posted, reversed. Period may be derived from date; override needs policy and reason. Overage becomes unapplied/ABT until approved. Browser `step=1000` is not a financial rule.
- **FR-04 Unmatched deposit review.** Separate queues for bank records and workbook rows; zero-value spreadsheet placeholders tagged `non-transaction`. Show candidates (NO ID, VA, agreement, alias) with rationale and competing candidates. Fuzzy name similarity only suggests. Reviewer confirms/rejects with evidence; second reviewer configurable (mandatory or not is OPEN). Mistaken allocations are reversible without erasing the original.
- **FR-05 Receivable detail.** Per agreement: principal, interest, administration, unapplied deposit, adjustments, classification, source/version, as-of date. Partner totals state which agreements/states are included. Every amount drills to events and source coordinates. Warn on conflicting components; never merge silently.
- **FR-06 Audit and authorization.** All creates, submissions, approvals, reversals, document accesses, exports and migration decisions emit audit events. Export needs explicit scope and is logged. Admin privilege does not bypass financial approval.

### P1: reconciliation and migration (staging only until approved)

- **FR-07 Workbook ingestion.** Import a **copy** with checksum, filename, as-of date, parser version, sheet inventory. Preserve raw values, formula text, cached values, hidden flags, coordinates, parse warnings. Provisional comparison source: `MASTER_All Piutang MB` (visible). Flag `MASTER_R1` and `121.20` (hidden) as historical candidates pending staff confirmation; do not delete. Sheet name `12.26` is not proof transactions run through 2026. Never replace `#REF!`, `#DIV/0!`, `#VALUE!` with zero. Re-importing an identical file is idempotent.
- **FR-08 Mapping and exceptions.** Versioned sheet/column mapping. NO ID, NIK, VA, agreement number and row number map to distinct fields. Detect: ID/name disagreement, missing IDs, repeated names, region spelling variants, formula errors, status/amount tension (e.g. `LUNAS` with positive amount), differing intermediate vs component balances. The `19.26` adjacent +1 ID offset is a **review fixture only**, never a rule to shift IDs. Region variants map to a staff-approved list without modifying raw values.
- **FR-09 Reconciliation workbench.** Compare ledger/contract/bank evidence with a named workbook snapshot per agreement and as-of date. Statuses: `unreviewed`, `needs evidence`, `resolved`, `approved`, `rejected`. Produces difference reports only; never creates corrective payments. Exportable signed-off exception package.
- **FR-10 Parallel run and cutover gate.** Read-only comparison against legacy output, recording mismatches, owners, resolutions. Cutover requires process-owner sign-off on identities, contract chains, opening balances, thresholds, backup/restore, permissions, rollback. Until then label imported balances **provisional**.

### P2: legacy coverage

- **FR-11 Dashboard and analysis.** Filters: as-of date, agreement cohort/year, sector, region, collectibility, scope. Cards, charts and trends share versioned `MetricDefinition`s and show numerator, denominator, included population and excluded counts. Show an explicit "unclassified" bucket so category sums equal totals. Distinguish partner count from agreement count.
- **FR-12 Agreement administration.** Separate controls for signing state, document workflow and lifecycle. Versioned PDF upload with checksum, MIME/size validation, malware scan, restricted access. Status changes never adjust balances. New agreements reference the partner registry rather than duplicating identity.
- **FR-13 ABT / non-partner deposits.** Register raw sender, amount, date/time, branch, note, source. Nullable partner. Unverified deposits never reduce debt. Disposition (offset/refund) needs approval per OPEN policy. Define "latest period" from included rows and test KPI/row consistency.
- **FR-14 Monitoring and exports.** Monthly monitoring is a separate dated snapshot with its own population, never merged into live KPIs without approved reconciliation. Exports (CSV/XLSX) need permission, scope, provisional labeling, audit; no unrestricted NIK/address extraction. Pivots sort months chronologically and flag missing months.

## 6. Screen map (legacy to replacement)

The legacy Shiny UI has ten menus. Save/delete/validation/export behavior was never tested; reproduce useful workflows, not unverified logic.

| Legacy menu                    | Replacement route (illustrative)            | Key changes                                                                            |
| ------------------------------ | ------------------------------------------- | -------------------------------------------------------------------------------------- |
| Dashboard, Analisis            | `/dashboard`                                | Shared metric definitions, as-of date, drilldown, unclassified bucket                  |
| Data Mitra, Data Piutang       | `/partners`, `/partners/:id`                | Official NO ID search, aliases, agreement-level balance cards, masked address          |
| Piutang per Wilayah            | `/dashboard` region view                    | Approved region taxonomy plus raw spelling and unmapped bucket                         |
| Status Perjanjian              | `/agreements/:id`                           | Pick partner then specific agreement; three status dimensions separate; versioned PDFs |
| Kelebihan Angsuran (ABT)       | `/abt`                                      | Raw deposits, nullable partner, unapplied balance, approved disposition                |
| Riwayat Angsuran               | `/payments`, `/reconciliation`              | Real transactions vs spreadsheet rows vs zero placeholders; unmatched queue            |
| Input Pembayaran               | `/payments/new`, `/payments/:id`            | Verified agreement, evidence, review, atomic post; reversal instead of Delete          |
| Monitoring Bulanan             | `/monitoring`                               | Dated snapshot, masked contacts, separate population                                   |
| (admin)                        | `/admin/metric-definitions`, `/imports/:id` | Metric versions, import staging                                                        |
| Ubah Username/Password, Logout | Laravel auth                                | Follow IT policy; legacy behavior not inspected                                        |

**Legacy form observations to carry over as _fields_, not rules:**

- Payment form: partner selector, auto-filled name (was editable in DOM), date `dd-mm-yyyy`, separate month/year, principal, combined `Bunga / Administrasi` (replacement should separate interest and administration if policy requires), note, recent-entries table with Delete.
- Agreement management: TTD update (`Belum TTD` / `Sudah TTD`); lifecycle/file update (`Belum Dibuat`, `Draft`, `Menunggu TTD Mitra`, `Menunggu TTD Perusahaan`, `Sudah Ditandatangani`, `Aktif`, `Selesai`); new-agreement form (identity, contact, business type, principal/interest, application and agreement dates, number, status, PDF). These seven labels and their transitions are **unapproved**.
- Non-partner ABT form: sender, amount, date, time, branch, note.

## 7. Primary flows

1. **Find partner:** search official ID; multiple/same-name results need evidence before selection; open agreement timeline; inspect balance source. Read-only.
2. **Record payment:** ingest evidence, check duplicate fingerprint/reference, pick verified partner and agreement, split components, preview, submit for required review, post atomically, issue receipt. No reliable match keeps it unapplied in the review queue.
3. **Restructure agreement:** attach approved addendum, set predecessor/successor and effective date, apply authorized component treatment, preview old/new balances, require approval, record both events. Old agreement shows `closed_by_rescheduling`, never cash payoff.
4. **Resolve workbook exception:** open raw cell and candidates, inspect evidence, decide identity/amount mapping, log rationale, second review if required. Original cells are never rewritten.
5. **Reverse posting:** authorized actor gives reason and evidence; compensating entry linked to original; amount reopens for reallocation; history stays visible.
6. **As-of review:** choose snapshot and filters, see metric version and coverage, drill to agreements/events.

## 8. Nonfunctional requirements

- **Security/privacy:** least-privilege RBAC, MFA/SSO per IT policy, TLS, encryption at rest, no full NIK/VA in routine logs, expiring document access, export limits, file content checks and malware scan, dependency and secret scanning. Keep `.env`, `CLAUDE.local.md`, `.claude/settings.local.json` out of version control; verify tracked state, since `.gitignore` does not untrack committed files.
- **Reliability:** transactional posting, immutable history, idempotent imports, retry-safe jobs, backups, restore drill, migration rollback. Approved as-of results change only via versioned correction.
- **Performance:** benchmark server-side search/paging on synthetic six-figure data. Latency and concurrency targets are set with staff, not invented.
- **Accessibility/localization:** keyboard-operable forms and tables, labeled errors, Indonesian UI and currency/date formats acceptable for staff-facing copy; store ISO dates with time zone; render Asia/Jakarta.
- **Observability:** structured redacted logs, health checks, alerts for failed import, invariant violations and backup failure. Security audit events are separate from diagnostics.
- **UI conventions:** every financial screen shows partner ID, agreement, as-of date, currency and source status. Badges: `verified`, `provisional`, `unmatched`, `needs review`. Show raw and normalized name/region side by side. Preserve unsaved drafts on error without logging secrets.

## 9. Implementation phases and gates

**Phase A: prototype (start now, synthetic data).** Domain schema and migrations, auth/RBAC scaffold, partner/alias search, agreement timeline, payment staging and allocation proposal, audit events, synthetic seed, README documenting assumptions and blocked decisions.

_Gate: passing Pest tests for:_

- leading-zero NO ID round-trip and exact-match lookup on TiDB
- same-name different-person returns separate candidates
- NO ID, NIK, VA, agreement number and row number stored as distinct fields
- cyclic addendum rejected
- name-only payment stays unmatched
- `closed_by_rescheduling` is not `paid_off`
- zero and negative payment rejected
- duplicate payment rejected; idempotent ingestion and identical-file re-import
- over-allocation rejected by DB-level and service checks
- reversal preserves original and audit trail
- unauthorized posting and unauthorized field access denied
- unverified balance renders `unverified`, not zero
- status changes (signing/lifecycle) do not change balances

**Phase B: staff policy and evidence.** Mapping workshop, source hierarchy and as-of date, role matrix, business rules, data-handling authority. Fixtures built from anonymized approved evidence. _Gate: signed decisions for section 10 and a reconciliation tolerance._

**Phase C: controlled migration and parallel run.** Stage source copy, work exceptions, approve opening balances, compare per agreement. Never auto-fix `19.26` IDs or status exceptions. _Gate: every in-scope agreement has verified identity/contract; material differences assigned or approved; totals reconcile under agreed rules; privacy and restore tests pass._

**Phase D: cutover.** Written owner approval, training, backup/rollback rehearsal, parallel-run report, restricted launch. No cutover on a good-looking dashboard alone.

**Repository deliverables:** `/docs/PRD.md`, `/docs/decisions.md` (every OPEN item with owner), `/docs/data-dictionary.md`, `/docs/metric-definitions.md`, migrations, generated API contract, synthetic seeder, Pest suite, change log.

_This PRD specifies a safer replacement. It does not reproduce unknown legacy backend logic and does not certify anyone's debt._
