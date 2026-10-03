# Task: Implement resolved sensitive-field masking policy

**Phase:** Formula Implementation
**ID:** FIMPL-003
**Depends on:** FIMPL-002
**PRD reference:** §3 (Roles), §4 (Partner — sensitive fields)

## Decision

**DEC-004 — Sensitive-field unmasking policy — `RESOLVED` 2026-10-03**

> "Owner confirmed: every role except viewer may see sensitive fields. This
> simplifies the proposed granular per-field/per-purpose grant model. The viewer
> role is the only one denied access to sensitive data (NIK, phone, address, VA,
> documents)."
>
> "The existing Permission enum's granular sensitive-field permissions (NikReveal,
> PhoneReveal, etc.) remain useful for logging and future tightening, but the
> default grant for non-viewer roles replaces the proposed deny-by-default model.
> Viewer access must remain masked/denied."
>
> — `docs/decisions.md` DEC-004, source: `docs/master-compilation.md` §2

## Current stub

- `app/Services/MaskingService.php` — masks sensitive fields by default for all roles.
- `app/Enums/Permission.php` — granular permissions (`NikReveal`, `PhoneReveal`, etc.) exist but deny-by-default.
- TASK-002 change log: "Auth scaffold and RBAC with deny-by-default policies; fine-grained matrix deferred per DEC-009" with DEC-004 ref.
- No `NotApprovedException` gate — masking is a permission check, not an exception stub.

## Implementation

1. Update masking policy: non-viewer roles (Operator, ReconciliationReviewer, ProcessOwner, SystemAdmin) see sensitive fields unmasked by default.
2. Viewer role (`Auditor` in code) remains masked/denied.
3. Keep granular Permission enum values for audit logging — they still fire when sensitive data is accessed, but the check for non-viewer roles returns `true`.
4. Update `MaskingService` (or wherever reveal checks happen) to check `$user->role !== Role::Auditor` instead of requiring explicit per-field grants.
5. Add docblock citing `DEC-004`.

## Pest tests

- Assert Operator user can see unmasked NIK, phone, address, VA fields.
- Assert Viewer (Auditor role) user sees masked/denied sensitive fields.
- Assert SystemAdmin can see sensitive fields (no implicit financial authority, but sensitive data is permitted per DEC-004).
- Existing masking tests updated to reflect the new policy.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-003 | implemented | Sensitive-field masking: viewer-only deny per DEC-004 | DEC-004 |

Previous entry to update: TASK-002 row mentioning DEC-004 stub.

## Acceptance criteria

- [ ] Non-viewer roles see sensitive fields unmasked
- [ ] Viewer role remains masked/denied
- [ ] Granular Permission enum retained for logging
- [ ] Docblock on masking logic cites `DEC-004`
- [ ] Pest tests assert role-based masking behavior
- [ ] Change log updated

## Status

`pending`
