# Task: Payment staging and allocation proposal

**Phase:** A
**ID:** TASK-007
**Depends on:** TASK-002, TASK-004, TASK-006
**PRD reference:** FR-03 (Payment capture), FR-04 (Unmatched deposit review), §7 flow 2 (Record payment)

## Goal

Build payment capture workflow: operator selects verified partner + specific agreement, enters receipt details and component amounts. Server validates everything (zero/negative rejection, duplicate detection, over-allocation check, idempotency). States: draft → submitted → posted → reversed. Allocation is a **proposal** only — posting rules are gated. Overage becomes unapplied/ABT. Reversal creates compensating entry.

## Scope

- Files likely touched:
  - `app/Http/Controllers/PaymentController.php`
  - `app/Http/Requests/StorePaymentRequest.php`
  - `app/Services/PaymentStagingService.php`
  - `app/Services/AllocationService.php`
  - `app/Services/PaymentReversalService.php`
  - `app/Http/Resources/PaymentResource.php`
  - `app/Exceptions/NotApprovedException.php`
  - `routes/web.php`
  - `resources/js/pages/Payments/Create.tsx`
  - `resources/js/pages/Payments/Show.tsx`
  - `resources/js/pages/Payments/Index.tsx`
- Explicit non-goals:
  - Actual posting to receivable ledger (blocked on balance rules DEC-008)
  - ABT disposition workflow (blocked on DEC-006)
  - Unmatched deposit resolution queue UI (separate concern)
  - Period derivation logic (blocked on DEC-010, stub only)

## Rules to follow (no invented values)

- DEC-005: Second reviewer requirement OPEN. Build configurable review step, default mandatory, but enforcement throws `NotApprovedException`.
- DEC-008: Balance formula OPEN. Show current balance as `unverified` with timestamp.
- DEC-010: Period derivation OPEN. Default to year-month of receipt date; override stores reason but throws `NotApprovedException`.
- Server recomputes every submitted total; never trust browser amounts (PRD §2).
- Posting targets verified agreement and approved identity only (PRD §4 invariant 3).
- `browser step=1000` is not a financial rule (PRD FR-03).
- Name-only payment stays unmatched (PRD §9 gate).
- Idempotency key required on all writes (PRD §2).
- DB transaction wraps all multi-table writes (PRD §2).

## Acceptance criteria

- [ ] "zero and negative payment rejected" — server rejects zero/negative component amounts with validation error
- [ ] "duplicate payment rejected; idempotent ingestion and identical-file re-import" — same idempotency key returns existing record, duplicate fingerprint blocked
- [ ] "over-allocation rejected by DB-level and service checks" — sum of allocation components cannot exceed transaction amount
- [ ] "name-only payment stays unmatched" — payment without verified partner+agreement ID stays in unmatched state
- [ ] "reversal preserves original and audit trail" — reversed payment creates compensating entry, original remains visible
- [ ] Form shows component sum and current balance with as-of timestamp
- [ ] Server recomputes all totals regardless of client-submitted values
- [ ] Pest test written and passing
- [ ] No real/PII data used anywhere (code, tests, seed data)
- [ ] Server re-validates/recomputes anything sent from the client
- [ ] Change log entry added: `implemented` (staging) / `stubbed` (posting, period derivation)

## Status

`not started`
