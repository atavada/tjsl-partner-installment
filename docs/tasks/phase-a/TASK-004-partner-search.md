# Task: Partner and alias search endpoint

**Phase:** A
**ID:** TASK-004
**Depends on:** TASK-001, TASK-002
**PRD reference:** FR-01, §7 flow 1 (Find partner)

## Goal

Implement server-side partner search by official NO ID (exact match, leading zeros), alias/exact name, agreement number, and permitted VA. Per DEC-001 (RESOLVED): agreement-number search returns all partners in the group (agreement numbers are grouping keys, not unique identifiers). Same-name hits return as separate candidates, never auto-merged. Server-side pagination. Verification/confidence badges as text labels.

## Scope

- Files likely touched:
  - `app/Http/Controllers/PartnerController.php`
  - `app/Http/Requests/SearchPartnerRequest.php`
  - `app/Services/PartnerSearchService.php`
  - `app/Http/Resources/PartnerResource.php`
  - `routes/web.php`
  - `resources/js/pages/Partners/Index.tsx`
  - `resources/js/pages/Partners/Show.tsx`
- Explicit non-goals:
  - Auto-merging duplicate partners
  - Fuzzy matching that auto-resolves identity (fuzzy only suggests per FR-04)
  - Unmasking sensitive fields (blocked on DEC-004)

## Rules to follow (no invented values)

- DEC-004: Sensitive fields masked by default. Search results show masked NIK/VA unless unmask policy resolved.
- Same name ≠ same person (PRD FR-01, §1 hard boundaries).
- Leading zeros preserved in NO ID search and display (PRD §2, §4).
- Server-side paging everywhere (PRD §2).
- Never auto-merge same-name hits (PRD FR-01).

## Acceptance criteria

- [x] "leading-zero NO ID round-trip and exact-match lookup on TiDB" — searching "007" returns partner with NO ID "007", not "7"
- [x] "same-name different-person returns separate candidates" — two partners with identical alias names appear as distinct results
- [x] Search by NO ID, alias name, agreement number, and VA all functional
- [x] Server-side pagination with configurable page size
- [x] Verification badge shown as text (not color alone) per PRD FR-01
- [x] Pest test written and passing
- [x] No real/PII data used anywhere (code, tests, seed data)
- [x] Server re-validates/recomputes anything sent from the client
- [x] Change log entry added: `implemented`

## Status

`done`
