# Data dictionary

Repository deliverable required by PRD §9. This is a **stub**: the entity
list and their known key rules are seeded from PRD §4 so nothing has to be
re-derived from scratch, but the field-level rows are filled in **as each
entity is actually built** in a Phase A task — not invented ahead of time.
A row with no migration yet stays `pending`.

Do not add a field here that doesn't exist in a migration. Do not add a
business rule here that isn't already in the PRD or a `RESOLVED` row in
`docs/decisions.md` — if you're not sure a rule is correct, it belongs in
`docs/decisions.md` as `OPEN`, not in this file.

## How to fill a table

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

- **Type** — the actual DB column type, not an application-level type.
- **PII?** — yes/no. Anything marked yes must be covered by the masking
  rule in PRD §3 ("sensitive fields masked by default").
- **Notes** — cite the PRD line or `decisions.md` ID that justifies
  anything non-obvious (why it's a string, why it's nullable, etc).

## Entities (from PRD §4)

Status legend: `pending` (no migration yet) · `draft` (migration exists,
not yet reviewed) · `verified` (migration + Pest test for its invariants
passing).

### Partner — `draft`
Official `partner_no_id` (nullable in staging only; unique once verified),
verification state, provenance. A display row number is never a NO ID.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| partner_no_id | varchar(50) | yes | §4 Partner | no | Raw string, leading zeros preserved. Nullable in staging. |
| partner_no_id_normalized | varchar(50) | yes | §2 | no | Trimmed + uppercased for lookup. UNIQUE (MySQL allows multiple NULLs). |
| nik | varchar(30) | yes | §4 Partner | yes | Raw NIK string, leading zeros preserved. |
| nik_normalized | varchar(30) | yes | §2 | yes | Trimmed for lookup. Indexed. |
| name | varchar(255) | no | §4 Partner | no | Display name. |
| phone | varchar(30) | yes | §4 Partner | yes | DEC-004: masked by default. |
| address | text | yes | §4 Partner | yes | DEC-004: masked by default. |
| business_type | varchar(100) | yes | §4 Partner | no | |
| region | varchar(100) | yes | §4 Partner | no | |
| verification_state | varchar(20) | no | §4 Partner | no | `unverified` (default), `pending`, `verified`. String, not enum (TiDB neutral). |
| provenance | varchar(255) | yes | §4 Partner | no | Data source origin. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### PartnerAlias — `draft`
Raw name, normalized search name, source, reviewer, state.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| partner_id | uuid (FK → partners) | no | §4 PartnerAlias | no | CASCADE on delete. |
| name_raw | varchar(255) | no | §4 PartnerAlias | no | Original name as received — never modified. |
| name_normalized | varchar(255) | no | §2 | no | Lowered + trimmed for search. Indexed. |
| source | varchar(255) | yes | §4 PartnerAlias | no | Where alias came from (workbook, manual_entry, import). |
| reviewer_id | bigint unsigned (FK → users) | yes | §4 PartnerAlias | no | Who reviewed. NULL on user delete. |
| state | varchar(20) | no | §4 PartnerAlias | no | `unreviewed` (default), `confirmed`, `rejected`. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### VirtualAccount — `draft`
Exact string, provider, partner/agreement link, validity window, evidence.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| partner_id | uuid (FK → partners) | no | §4 VirtualAccount | no | CASCADE on delete. |
| va_number | varchar(50) | no | §4 VirtualAccount | yes | Raw VA number, leading zeros preserved. DEC-004: masked by default. |
| va_number_normalized | varchar(50) | no | §2 | yes | Trimmed for lookup. Indexed. No global unique (PRD §4: no timeless uniqueness). |
| provider | varchar(100) | yes | §4 VirtualAccount | no | Bank/payment provider. Indexed. |
| valid_from | date | yes | §4 VirtualAccount | no | Validity window start. |
| valid_until | date | yes | §4 VirtualAccount | no | Validity window end. |
| evidence | text | yes | §4 VirtualAccount | no | Evidence/notes for VA assignment. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### Agreement — `draft`
Raw and normalized agreement number, partner, application/effective dates,
principal and charge components, lifecycle state, approved source.
Uniqueness scope: **RESOLVED** — DEC-001: grouping key (business group/batch per year), NOT unique.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| partner_id | uuid (FK → partners) | no | §4 Agreement | no | CASCADE on delete. |
| agreement_number | varchar(100) | no | §4 Agreement | no | Raw string, leading zeros preserved. DEC-001: grouping key, NOT unique. |
| agreement_number_normalized | varchar(100) | no | §2 | no | Uppercased + trimmed. Indexed, no unique index (DEC-001). |
| batch_year | varchar(10) | yes | DEC-001 | no | Business group / batch per year grouping. |
| business_group | varchar(100) | yes | DEC-001 | no | Business sector / group classification. |
| source_row_number | unsigned int | yes | §5 FR-08 | no | Distinct from NO ID and agreement number. |
| application_date | date | yes | §4 Agreement | no | Date of loan application. |
| contract_date | date | yes | §4 Agreement | no | Date of contract signing. |
| effective_date | date | yes | §4 Agreement | no | Start of active loan term. |
| maturity_date | date | yes | §4 Agreement | no | Scheduled maturity date. |
| principal_amount | unsigned bigint | no | §2, §4 | no | Integer IDR, non-negative, default 0. |
| interest_amount | unsigned bigint | no | §2, §4 | no | Integer IDR, non-negative, default 0. |
| admin_charge_amount | unsigned bigint | no | §2, §4 | no | Integer IDR, non-negative, default 0. |
| other_charge_amount | unsigned bigint | no | §2, §4 | no | Integer IDR, non-negative, default 0. |
| total_amount | unsigned bigint | no | §2, §4 | no | Sum of financial components, integer IDR. |
| lifecycle_status | varchar(30) | no | §4, DEC-002 | no | `draft` (default), `active`, `paid_off`, `closed_by_rescheduling`, `cancelled`, `unknown`. |
| legacy_lifecycle_status | varchar(100) | yes | DEC-002 | no | Raw label from legacy workbook / UI. |
| collectibility_status | varchar(30) | no | §4, DEC-007 | no | `unknown` (default), `current` (Lancar), `substandard` (Kurang Lancar), `loss` (Bermasalah). |
| legacy_collectibility_status | varchar(100) | yes | DEC-007 | no | Raw collectibility code / text from workbook. |
| signing_status | varchar(30) | no | §4, DEC-003 | no | `not_prepared` (default), `draft`, `awaiting_partner_signature`, `awaiting_company_signature`, `signed`, `unknown`. |
| signature_summary | varchar(20) | no | DEC-003 | no | `unknown` (default), `unsigned` (Belum TTD), `signed` (Sudah TTD). |
| legacy_signing_status | varchar(100) | yes | DEC-003 | no | Raw workbook mark ('x', '√', blank). |
| provenance | varchar(255) | yes | §4 Agreement | no | Origin sheet / source file. |
| approved_source | varchar(255) | yes | §4 Agreement | no | Process owner approval reference. |
| approved_by_id | bigint unsigned (FK → users) | yes | §4 Agreement | no | User who approved. |
| approved_at | timestamp | yes | §4 Agreement | no | Timestamp of approval. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### AgreementTransition — `draft`
Predecessor/successor, type (amendment, rescheduling, closure, reversal),
effective date, approved amounts, documents. Graph must be acyclic.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| predecessor_id | uuid (FK → agreements) | no | §4 AgreementTransition | no | Predecessor agreement in chain. CASCADE on delete. |
| successor_id | uuid (FK → agreements) | yes | §4 AgreementTransition | no | Successor agreement in chain. Nullable for terminal closure. |
| transition_type | varchar(50) | no | §4 AgreementTransition | no | `amendment`, `rescheduling`, `closure`, `reversal`. |
| effective_date | date | no | §4 AgreementTransition | no | Effective date of transition. |
| reason | text | yes | §4 AgreementTransition | no | Business rationale. |
| approved_principal_amount | unsigned bigint | yes | §4 | no | Integer IDR restructuring amount. |
| approved_interest_amount | unsigned bigint | yes | §4 | no | Integer IDR restructuring amount. |
| approved_admin_charge_amount | unsigned bigint | yes | §4 | no | Integer IDR restructuring amount. |
| approved_by_id | bigint unsigned (FK → users) | yes | §4 | no | Authorizing user. NULL on user delete. |
| approved_at | timestamp | yes | §4 | no | Timestamp of approval. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### InstallmentSchedule — `draft`
Due dates and components per agreement/policy version.
**Calculation blocked until policy approved** — schema only in Phase A.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| agreement_id | uuid (FK → agreements) | no | §4 InstallmentSchedule | no | Associated agreement. CASCADE on delete. |
| installment_number | unsigned int | no | §4 InstallmentSchedule | no | Installment sequence number (1..N). UNIQUE with agreement_id. |
| due_date | date | no | §4 InstallmentSchedule | no | Scheduled due date. |
| principal_due | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| interest_due | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| admin_charge_due | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| other_charge_due | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| total_due | unsigned bigint | no | §2, §4 | no | Sum of due components, integer IDR, default 0. |
| principal_paid | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| interest_paid | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| admin_charge_paid | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| other_charge_paid | unsigned bigint | no | §2, §4 | no | Integer IDR, default 0. |
| total_paid | unsigned bigint | no | §2, §4 | no | Sum of paid components, integer IDR, default 0. |
| status | varchar(30) | no | §4 | no | `pending`, `paid`, `partially_paid`, `overdue`, `cancelled`. |
| policy_version | varchar(50) | yes | DEC-008 | no | Calculation policy version reference (blocked on DEC-008). |
| is_calculated | boolean | no | DEC-008 | no | Schema-only marker. Calculation blocked. Default false. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### BankTransaction — `verified`
Immutable raw record: reference, datetime + zone, integer IDR amount,
payer/VA, source, fingerprint, state. Reference uniqueness is contextual.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| reference | varchar(255) | yes | §4, DEC-011 | no | Raw bank reference string. |
| reference_normalized | varchar(255) | yes | §2, DEC-011 | no | Uppercased + trimmed for lookup. |
| reference_namespace | varchar(100) | yes | DEC-011 | no | Verified bank/provider + account + type. |
| transaction_datetime | timestamp | no | §4, DEC-010 | no | Receipt timestamp. |
| timezone | varchar(50) | no | DEC-010 | no | Default `Asia/Jakarta`. |
| amount | unsigned bigint | no | §2, §4 | no | Integer IDR, non-negative raw deposit. |
| payer_name | varchar(255) | yes | §4, DEC-004 | yes | DEC-004: masked by default. |
| payer_va | varchar(50) | yes | §4, DEC-004 | yes | DEC-004: masked by default. |
| source | varchar(255) | yes | §4, FR-03 | no | Ingestion channel / import file. |
| source_row_identifier | varchar(255) | yes | §4 | no | Row identifier within source. |
| fingerprint | varchar(64) | yes | §4, FR-03 | no | SHA-256 hash for duplicate detection. Indexed. |
| idempotency_key | uuid | no | §2, FR-03 | no | Unique constraint prevents double-ingestion. |
| state | varchar(30) | no | §4, FR-03 | no | `draft`, `submitted`, `posted`, `reversed`. |
| receipt_month | varchar(7) | yes | DEC-010 | no | YYYY-MM derived from receipt date. |
| provenance | varchar(255) | yes | §4 | no | Data origin. |
| notes | text | yes | §4 | no | Audit notes. |
| recorded_by_id | bigint unsigned (FK → users) | yes | §4 | no | User who recorded the transaction. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### PaymentAllocation — `verified`
Transaction to agreement with principal/interest/administration/other
amounts, effective date, evidence, approver, version. Corrections are
compensating entries.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| bank_transaction_id | uuid (FK → bank_transactions) | no | §4 | no | Associated bank transaction. CASCADE on delete. |
| agreement_id | uuid (FK → agreements) | no | §4 | no | Target agreement. CASCADE on delete. |
| principal_amount | unsigned bigint | no | §2, §4 | no | Integer IDR component, default 0. |
| interest_amount | unsigned bigint | no | §2, §4 | no | Integer IDR component, default 0. |
| admin_charge_amount | unsigned bigint | no | §2, §4 | no | Integer IDR component, default 0. |
| other_charge_amount | unsigned bigint | no | §2, §4 | no | Integer IDR component, default 0. |
| total_amount | unsigned bigint | no | §2, §4 | no | Integer IDR sum of components. |
| effective_date | date | no | §4 | no | Allocation effective date. |
| period | varchar(7) | yes | DEC-010 | no | YYYY-MM accounting/installment period. |
| state | varchar(30) | no | §4, FR-03 | no | `draft`, `submitted`, `posted`, `reversed`. |
| evidence | text | yes | §4, FR-03 | no | Payment evidence document reference / notes. |
| idempotency_key | uuid | no | §2, FR-03 | no | Unique constraint prevents double-posting. |
| approved_by_id | bigint unsigned (FK → users) | yes | §4, FR-03 | no | Approving user. NULL on delete. |
| approved_at | timestamp | yes | §4, FR-03 | no | Timestamp of approval. |
| approved_source | varchar(255) | yes | §4 Invariant 5 | no | Source row identifier to prevent duplicate posting. |
| reversal_of_id | uuid (FK → payment_allocations) | yes | §4 Invariant 4 | no | Self-referencing FK for compensating entries. |
| reason | text | yes | §4 Invariant 4 | no | Required rationale for reversals/corrections. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### ReceivableAdjustment — `verified`
Opening balance, correction, write-off, transfer, reversal with reason,
approvals, evidence.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| agreement_id | uuid (FK → agreements) | no | §4 | no | Target agreement. CASCADE on delete. |
| adjustment_type | varchar(50) | no | §4 | no | `opening_balance`, `correction`, `write_off`, `transfer`, `reversal`. |
| principal_amount | bigint | no | §2, §4 | no | Signed integer IDR, default 0. |
| interest_amount | bigint | no | §2, §4 | no | Signed integer IDR, default 0. |
| admin_charge_amount | bigint | no | §2, §4 | no | Signed integer IDR, default 0. |
| other_charge_amount | bigint | no | §2, §4 | no | Signed integer IDR, default 0. |
| total_amount | bigint | no | §2, §4 | no | Signed integer IDR sum of components. |
| effective_date | date | no | §4 | no | Effective date of adjustment. |
| reason | text | yes | §4 Invariant 4 | no | Business rationale. |
| evidence | text | yes | §4 | no | Supporting documentation reference. |
| idempotency_key | uuid | no | §2 | no | Unique constraint. |
| state | varchar(30) | no | §4 | no | `draft`, `submitted`, `posted`, `reversed`. |
| approved_by_id | bigint unsigned (FK → users) | yes | §4 | no | Approving user. NULL on delete. |
| approved_at | timestamp | yes | §4 | no | Timestamp of approval. |
| reversal_of_id | uuid (FK → receivable_adjustments) | yes | §4 Invariant 4 | no | Self-referencing FK for compensating entries. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### Overpayment (ABT) — `verified`
Transaction link, nullable partner, unapplied amount, proposed
disposition, status, approval.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| bank_transaction_id | uuid (FK → bank_transactions) | no | §4 | no | Associated bank transaction. CASCADE on delete. |
| partner_id | uuid (FK → partners) | yes | §4 | no | Nullable for unidentified/non-partner deposits. |
| unapplied_amount | unsigned bigint | no | §2, §4 | no | Integer IDR unapplied portion. |
| proposed_disposition | varchar(50) | yes | DEC-006 | no | Proposed action (`offset`, `refund`). |
| disposition_status | varchar(50) | no | DEC-006 | no | `unresolved`, `verified_unapplied`, `disposition_proposed`, `disposition_approved`, `executed`. |
| evidence | text | yes | §4 | no | Supporting documentation reference. |
| idempotency_key | uuid | no | §2 | no | Unique constraint. |
| approved_by_id | bigint unsigned (FK → users) | yes | §4 | no | Approving user. NULL on delete. |
| approved_at | timestamp | yes | §4 | no | Timestamp of approval. |
| reason | text | yes | §4 | no | Business rationale for disposition. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### AgreementDocument — `draft`
Versioned private file ref, checksum, MIME, uploader, access log.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| id | uuid (PK) | no | §4 | no | HasUuids trait |
| agreement_id | uuid (FK → agreements) | no | §4 AgreementDocument | no | Associated agreement. CASCADE on delete. |
| transition_id | uuid (FK → agreement_transitions) | yes | §4 | no | Associated transition (e.g. addendum). Nullable. |
| file_path | varchar(500) | no | §4 AgreementDocument | no | File path on private storage disk. |
| file_name | varchar(255) | no | §4 AgreementDocument | no | Original client file name. |
| mime_type | varchar(100) | no | §4 AgreementDocument | no | MIME type (e.g. `application/pdf`). |
| file_size_bytes | unsigned bigint | no | §4 AgreementDocument | no | File size in bytes. |
| checksum_sha256 | varchar(64) | no | §4 AgreementDocument | no | SHA-256 hash of file content. |
| document_type | varchar(50) | no | §4 AgreementDocument | no | `contract`, `addendum`, `rescheduling_agreement`, `identity`, `other`. |
| document_version | unsigned int | no | §4 AgreementDocument | no | Document revision version. Default 1. |
| uploaded_by_id | bigint unsigned (FK → users) | yes | §4 AgreementDocument | no | Uploader user. NULL on user delete. |
| signing_status | varchar(30) | no | DEC-003 | no | Document workflow signing state. Default `not_prepared`. |
| signature_summary | varchar(20) | no | DEC-003 | no | `unsigned` (Belum TTD), `signed` (Sudah TTD), `unknown`. Default `unknown`. |
| notes | text | yes | §4 AgreementDocument | no | Document notes. |
| version | unsigned int | no | §2 | no | Optimistic concurrency. Default 1. |
| created_at | timestamp | yes | — | no | |
| updated_at | timestamp | yes | — | no | |

### SourceSnapshot / SourceRow — `pending`
File hash, as-of date, sheet, cell coordinates, raw values, formula text
vs cached value, hidden flag, parser version. Immutable.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### ReconciliationCase — `pending`
Candidate links, discrepancy type, evidence, decisions, status.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### AuditEvent — `pending`
Actor, time, target, action, delta, reason, correlation ID. Append-only;
sensitive data masked.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### MetricDefinition — `pending`
Versioned name, formula/scope, numerator/denominator, date semantics,
owner approval. See `docs/metric-definitions.md` for the actual defined
metrics (this table only covers the schema, not metric content).

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
