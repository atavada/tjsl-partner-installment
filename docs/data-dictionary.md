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

### Partner — `pending`
Official `partner_no_id` (nullable in staging only; unique once verified),
verification state, provenance. A display row number is never a NO ID.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|
| | | | §4 Partner | | |

### PartnerAlias — `pending`
Raw name, normalized search name, source, reviewer, state.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### VirtualAccount — `pending`
Exact string, provider, partner/agreement link, validity window, evidence.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### Agreement — `pending`
Raw and normalized agreement number, partner, application/effective dates,
principal and charge components, lifecycle state, approved source.
Uniqueness scope: **OPEN** — see `docs/decisions.md`.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### AgreementTransition — `pending`
Predecessor/successor, type (amendment, rescheduling, closure, reversal),
effective date, approved amounts, documents. Graph must be acyclic.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### InstallmentSchedule — `pending`
Due dates and components per agreement/policy version.
**Calculation blocked until policy approved** — schema only in Phase A.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### BankTransaction — `pending`
Immutable raw record: reference, datetime + zone, integer IDR amount,
payer/VA, source, fingerprint, state. Reference uniqueness is contextual.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### PaymentAllocation — `pending`
Transaction to agreement with principal/interest/administration/other
amounts, effective date, evidence, approver, version. Corrections are
compensating entries.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### ReceivableAdjustment — `pending`
Opening balance, correction, write-off, transfer, reversal with reason,
approvals, evidence.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### Overpayment (ABT) — `pending`
Transaction link, nullable partner, unapplied amount, proposed
disposition, status, approval.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

### AgreementDocument — `pending`
Versioned private file ref, checksum, MIME, uploader, access log.

| Field | Type | Nullable | Source (PRD §) | PII? | Notes |
|-------|------|----------|-----------------|------|-------|

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
