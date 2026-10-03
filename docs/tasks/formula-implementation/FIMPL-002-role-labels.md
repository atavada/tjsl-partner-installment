# Task: Update Role enum labels to confirmed business names

**Phase:** Formula Implementation
**ID:** FIMPL-002
**Depends on:** —
**PRD reference:** §3 (Roles)

## Decision

**DEC-009 — RBAC roles and permissions — `RESOLVED` 2026-10-03**

> "Owner confirmed five named roles:"
>
> | # | Confirmed Role (Business) | Internal Name (Code) | Job |
> |---|---|---|---|
> | 1 | Viewer | Auditor | Read-only access, reproduce as-of figures, scoped exports |
> | 2 | Kasir TJSL | Operator | Search partners, record evidence-backed payments, manage agreement workflow |
> | 3 | Kepala Sub Divisi | ReconciliationReviewer | Resolve identity/agreement matches, approve allocations and exceptions |
> | 4 | Sekper / Kepala Divisi | ProcessOwner | Set source precedence, approve opening balances, policy versions, cutover |
> | 5 | System Admin | SystemAdmin | Users, config, backups. No implicit authority to approve financial postings |
>
> "Current release scope: viewer, kasir TJSL, and system admin only."
>
> — `docs/decisions.md` DEC-009, source: `docs/master-compilation.md` §2

## Current stub

- `app/Enums/Role.php:15-23` — `label()` method returns English names: `'Operator'`, `'Reconciliation Reviewer'`, `'Process Owner'`, `'Auditor'`, `'System Admin'`.
- No `NotApprovedException` gate — this was never exception-gated, just outdated labels.

## Implementation

1. Update `Role::label()` to return confirmed Indonesian business names:
   - `Operator` → `'Kasir TJSL'`
   - `ReconciliationReviewer` → `'Kepala Sub Divisi'`
   - `ProcessOwner` → `'Sekper / Kepala Divisi'`
   - `Auditor` → `'Viewer'`
   - `SystemAdmin` → `'System Admin'`
2. Add a `businessName()` method as alias for `label()` (or rename, if no callers depend on the old names).
3. Add docblock citing `DEC-009`.
4. Update any frontend TypeScript types/constants that display role labels.
5. Verify the `EnsureRoleAuthorized` middleware and `RbacTest` still pass with the new labels.

## Pest tests

- Assert each `Role::label()` returns the confirmed business name.
- Assert `Role::Auditor->label()` returns `'Viewer'`, not `'Auditor'`.
- Assert `Role::Operator->label()` returns `'Kasir TJSL'`.
- Existing RBAC tests continue passing (role values unchanged, only display labels change).

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-002 | implemented | Role enum labels updated to confirmed business names per DEC-009 | DEC-009 |

Previous entry to update: TASK-002 row mentioning DEC-009 stub.

## Acceptance criteria

- [ ] `Role::label()` returns confirmed Indonesian business names
- [ ] Docblock on `Role` enum cites `DEC-009`
- [ ] Frontend role display updated if applicable
- [ ] Existing RBAC tests pass
- [ ] New Pest test asserts each role's confirmed label
- [ ] Change log updated

## Status

`pending`
