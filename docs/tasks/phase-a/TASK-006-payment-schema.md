# Task: Payment schema — BankTransaction, PaymentAllocation, Overpayment

**Phase:** A
**ID:** TASK-006
**Depends on:** TASK-001, TASK-003
**PRD reference:** §4 (BankTransaction, PaymentAllocation, ReceivableAdjustment, Overpayment), FR-03, FR-04

## Goal

Create `bank_transactions`, `payment_allocations`, `receivable_adjustments`, and `overpayments` tables. BankTransaction is immutable. PaymentAllocation links transaction to agreement with separate component amounts. All money as integer IDR. Idempotency keys and version columns. Fingerprint-based duplicate detection.

## Scope

- Files likely touched:
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_bank_transactions_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_payment_allocations_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_receivable_adjustments_table.php`
  - `database/migrations/YYYY_MM_DD_HHMMSS_create_overpayments_table.php`
  - `app/Models/BankTransaction.php`
  - `app/Models/PaymentAllocation.php`
  - `app/Models/ReceivableAdjustment.php`
  - `app/Models/Overpayment.php`
  - `app/Enums/PaymentState.php` (draft, submitted, posted, reversed)
  - `docs/data-dictionary.md` (update relevant sections)
- Explicit non-goals:
  - Payment capture UI (that's TASK-007)
  - Allocation proposal logic (that's TASK-007)
  - ABT disposition (blocked on DEC-006)
  - Balance formula (blocked on DEC-008)

## Rules to follow (no invented values)

- DEC-006: ABT/overpayment disposition OPEN. Schema stores proposed disposition; actual application throws `NotApprovedException`.
- DEC-008: No balance formula. Allocation amounts stored but receivable computation stubbed.
- DEC-010: Period derivation OPEN. Column exists; derivation logic stubbed.
- DEC-011: Bank reference uniqueness scope OPEN. Default unique per (source, reference).
- Raw deposits and component amounts non-negative; posted payments strictly positive (PRD §4 invariant 1).
- Allocation components sum to allocated portion; allocated + unapplied ≤ transaction amount (PRD §4 invariant 2).
- Corrections are compensating entries, no physical delete (PRD §4 invariant 4).
- Same approved source row never posted twice (PRD §4 invariant 5).
- Money: integer IDR (PRD §2).
- Idempotency keys and version columns (PRD §2).

## Acceptance criteria

- [ ] "zero and negative payment rejected" — DB constraint and model validation reject zero/negative amounts on posted payments
- [ ] "duplicate payment rejected; idempotent ingestion and identical-file re-import" — idempotency key and fingerprint prevent double-posting
- [ ] "over-allocation rejected by DB-level and service checks" — allocation cannot exceed transaction amount
- [ ] All money columns integer type, no floats
- [ ] BankTransaction model is immutable (no update/delete)
- [ ] Pest test written and passing (constraints, immutability, duplicate rejection)
- [ ] No real/PII data used anywhere (code, tests, seed data)
- [ ] Change log entry added: `implemented` (schema) / `stubbed` (disposition, balance)

## Status

`not started`
