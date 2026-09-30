# Task: Agreement schema, transitions, and document storage

**Phase:** A
**ID:** TASK-003
**Depends on:** TASK-001
**PRD reference:** §4 (Agreement, AgreementTransition, AgreementDocument, InstallmentSchedule), FR-02

## Goal

Create `agreements`, `agreement_transitions`, `agreement_documents`, and `installment_schedules` tables. Enforce acyclic transition graph. Agreement numbers stored as raw + normalized strings — per DEC-001 (RESOLVED) they are grouping keys (business group/batch per year), NOT unique identifiers. No uniqueness constraint on agreement number. InstallmentSchedule is schema-only (calculation blocked on DEC-008). Document storage uses private disk with checksum.

## Scope

- Files likely touched:
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_agreements_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_agreement_transitions_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_agreement_documents_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_installment_schedules_table.php`
  - `app/Models/Agreement.php`
  - `app/Models/AgreementTransition.php`
  - `app/Models/AgreementDocument.php`
  - `app/Models/InstallmentSchedule.php`
  - `app/Services/AgreementTransitionService.php` (cycle detection)
  - `docs/data-dictionary.md` (update relevant sections)
- Explicit non-goals:
  - Agreement timeline UI (that's TASK-005)
  - Balance calculation (blocked on DEC-008)
  - Lifecycle state transitions (blocked on DEC-002, stub only)

## Rules to follow (no invented values)

- DEC-001: **RESOLVED.** Agreement numbers are NOT unique globally or per partner — they group partners by business group/batch per year. Do NOT enforce `UNIQUE(partner_id, agreement_number_normalized)`. Agreement number is a grouping key, not an identifier. Use NO ID and NO VA for partner identification.
- DEC-002: Lifecycle states PROPOSED. Enum: `draft`, `active`, `paid_off`, `closed_by_rescheduling`, `cancelled`, `unknown`. Preserve raw legacy labels separately. State transition validation throws `NotApprovedException` until confirmed.
- DEC-003: Signing states PROPOSED. Document workflow: `not_prepared`, `draft`, `awaiting_partner_signature`, `awaiting_company_signature`, `signed`, `unknown`. Separate version-specific signature summary: `belum_ttd`, `sudah_ttd`, `unknown`. Transitions throw `NotApprovedException` until confirmed.
- DEC-008: Balance formula OPEN. `InstallmentSchedule` schema exists but no calculation logic.
- `closed_by_rescheduling` is never `paid_off` (PRD §1 hard boundary, §4 invariant).
- Three independent status dimensions: lifecycle, collectibility, signing (PRD §4 invariant 8).
- Transition graph must be acyclic (PRD §4 AgreementTransition).
- Principal and charge components stored as integer IDR (PRD §2).

## Acceptance criteria

- [ ] "cyclic addendum rejected" — service rejects transition that would create a cycle in predecessor/successor graph
- [ ] "`closed_by_rescheduling` is not `paid_off`" — model/service prevents treating rescheduling closure as payoff
- [ ] "status changes (signing/lifecycle) do not change balances" — changing signing or lifecycle state has no side effect on financial amounts
- [ ] "NO ID, NIK, VA, agreement number and row number stored as distinct fields" — agreement_number is its own string column, distinct from partner identifiers
- [ ] Pest test written and passing (cycle detection, state independence, document checksum)
- [ ] No real/PII data used anywhere (code, tests, seed data)
- [ ] Change log entry added: `stubbed` (lifecycle transitions, balance calc deferred)

## Status

`not started`
