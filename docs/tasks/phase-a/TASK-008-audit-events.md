# Task: Audit events system

**Phase:** A
**ID:** TASK-008
**Depends on:** TASK-002
**PRD reference:** §4 (AuditEvent), FR-06, §8 (observability)

## Goal

Create append-only `audit_events` table and service. Every create, submission, approval, reversal, document access, and export emits an audit event with actor, time, target (polymorphic), action, delta (JSON), reason, and correlation ID. Sensitive data masked in event payloads. No physical delete. Wire audit emission into existing model observers or service layer hooks.

## Scope

- Files likely touched:
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_audit_events_table.php`
  - `app/Models/AuditEvent.php`
  - `app/Services/AuditService.php`
  - `app/Observers/AuditObserver.php` (or trait `Auditable`)
  - `app/Concerns/Auditable.php`
  - `docs/data-dictionary.md` (update AuditEvent section)
- Explicit non-goals:
  - Export permission/scope logic (Phase A scope is event recording only)
  - Real-time alerting (PRD §8 mentions it but no specific rules)
  - Separate security audit log stream (mentioned in §8 but not Phase A scope)

## Rules to follow (no invented values)

- Append-only: no update or delete on audit_events (PRD §4).
- Sensitive data masked in audit payloads (PRD §4 AuditEvent, §8).
- Admin privilege does not bypass financial approval (PRD FR-06).
- Correlation ID for linking related events across a request lifecycle.
- Actor is always the authenticated user; system actions use a system actor identifier.

## Acceptance criteria

- [x] "reversal preserves original and audit trail" — reversals emit audit events linking to original
- [x] "unauthorized posting and unauthorized field access denied" — authorization failures emit audit events
- [x] AuditEvent model prevents update and delete (override or exception)
- [x] All model creates/updates on auditable models emit events with actor, target, action, delta
- [x] Sensitive fields (NIK, VA, phone) masked in delta JSON
- [x] Correlation ID groups related events within a request
- [x] Pest test written and passing (event emission, immutability, masking)
- [x] No real/PII data used anywhere (code, tests, seed data)
- [x] Change log entry added: `implemented`

## Status

`completed`
