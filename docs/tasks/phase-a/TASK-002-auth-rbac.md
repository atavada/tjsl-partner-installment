# Task: Auth scaffold and RBAC with deny-by-default policies

**Phase:** A
**ID:** TASK-002
**Depends on:** TASK-001
**PRD reference:** §3 (Roles), §6 (auth), §8 (security/privacy), FR-06

## Goal

Scaffold authentication (Laravel Breeze/Fortify or equivalent) and role-based access control with the five PRD roles. Implement action-level, object-scoped deny-by-default policy checks at API level per DEC-009 proposal — not coarse read/write gates. Sensitive field masking infrastructure with per-field permissions (`nik.reveal`, `va.reveal`, etc.) per DEC-004 proposal; all masked by default.

## Scope

- Files likely touched:
  - `database/migrations/YYYY_MM_DD_HHMMSS_add_role_to_users_table.php` (or roles/permissions tables)
  - `app/Enums/Role.php`
  - `app/Models/User.php` (role relationship/attribute)
  - `app/Policies/*.php` (base policies with deny-by-default)
  - `app/Http/Middleware/EnsureRoleAuthorized.php`
  - `app/Providers/AuthServiceProvider.php`
  - `routes/web.php` (auth routes)
  - `resources/js/pages/Auth/*.tsx` (login page)
- Explicit non-goals:
  - Fine-grained permission matrix (PROPOSED in DEC-009, awaiting confirmation; implement action-level structure, real grants empty)
  - MFA/SSO (per IT policy, not Phase A)
  - Field-level masking rules (PROPOSED in DEC-004, awaiting confirmation; infrastructure built, all masked by default)

## Rules to follow (no invented values)

- DEC-009: RBAC matrix PROPOSED. Implement action-level deny-by-default policies from Phase A, not coarse read/write gates. See DEC-009 eligibility matrix. Real capability grants default empty; actions without explicit grant denied. Admin must not self-grant financial/sensitive authority.
- DEC-004: Unmask policy PROPOSED. Separate per-field permissions (`nik.reveal`, `phone.reveal`, `address.reveal`, `va.reveal`, `document.view`, `document.download`, `sensitive.export`). Everything masked by default; reveal attempts denied until grants confirmed. Log actor/purpose/target/time.
- Deny-by-default: any action without an explicit allow = denied (PRD §3).
- Admin privilege does not bypass financial approval (PRD FR-06).

## Acceptance criteria

- [ ] "unauthorized posting and unauthorized field access denied" — unauthenticated and unauthorized requests return 403; no implicit admin bypass for financial actions
- [ ] Five PRD roles exist as enum/config: Operator, Reconciliation reviewer, Process owner, Auditor, System admin
- [ ] Deny-by-default policy: accessing any protected resource without explicit permission returns 403
- [ ] Pest test written and passing (auth gates, role assignment, deny-by-default)
- [ ] No real/PII data used anywhere (code, tests, seed data)
- [ ] Change log entry added: `stubbed` (fine-grained matrix deferred per DEC-009)

## Status

`not started`
