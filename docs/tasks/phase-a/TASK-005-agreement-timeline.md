# Task: Agreement timeline page

**Phase:** A
**ID:** TASK-005
**Depends on:** TASK-003, TASK-004
**PRD reference:** FR-02, §7 flow 1 (Find partner → open agreement timeline)

## Goal

Build the agreement timeline view showing each agreement's dates, amounts, documents, statuses, predecessor/successor links, and as-of component display. Draft agreements create no debt. Balance display shows `unverified` (per DEC-008). Three status dimensions displayed independently.

## Scope

- Files likely touched:
  - `app/Http/Controllers/AgreementController.php`
  - `app/Http/Resources/AgreementResource.php`
  - `app/Http/Resources/AgreementTransitionResource.php`
  - `app/Services/BalanceService.php` (stub returning `unverified`)
  - `routes/web.php`
  - `resources/js/pages/Agreements/Show.tsx`
  - `resources/js/pages/Agreements/Index.tsx`
  - `resources/js/components/AgreementTimeline.tsx`
- Explicit non-goals:
  - Balance calculation (stub only, DEC-008)
  - Agreement creation/editing workflow (TASK-003 handles schema)
  - Document upload UI (can be separate task)

## Rules to follow (no invented values)

- DEC-002: Lifecycle states shown but transitions not enforced (OPEN).
- DEC-003: Signing states shown but transitions not enforced (OPEN).
- DEC-008: Balance returns `unverified` / `not available`, never a plausible zero.
- Draft agreements create no debt (PRD FR-02).
- Activation needs approval and source (PRD FR-02).
- Three status dimensions displayed independently (PRD §4 invariant 8).

## Acceptance criteria

- [x] "unverified balance renders `unverified`, not zero" — balance display shows explicit `unverified` label, never zero
- [x] "status changes (signing/lifecycle) do not change balances" — UI shows three status dimensions independently
- [x] Agreement timeline shows dates, amounts, documents, predecessor/successor links
- [x] Draft agreements clearly labeled, not showing debt figures
- [x] Pest test written and passing (Inertia page renders, balance stub returns unverified)
- [x] No real/PII data used anywhere (code, tests, seed data)
- [x] Change log entry added: `stubbed` (balance calculation deferred)

## Status

`done`
