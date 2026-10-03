# Task: Remove second-review enforcement (cashier posts directly)

**Phase:** Formula Implementation
**ID:** FIMPL-001
**Depends on:** —
**PRD reference:** FR-03 (Payment capture), FR-04 (Unmatched deposit review)

## Decision

**DEC-005 — Second review of unmatched deposits — `RESOLVED` 2026-10-03**

> "Owner confirmed: no approval system for now. TJSL cashier inputs directly.
> Keep audit trail and reversal capability. Stub future approval workflow for
> later implementation. This REVERSES the proposed mandatory maker-checker
> default. The cashier has direct posting authority without a second reviewer,
> but every action must be audited and reversible."
>
> — `docs/decisions.md` DEC-005, source: `docs/master-compilation.md` §2

## Current stub

- `app/Services/PaymentStagingService.php:207-210` — `enforceSecondReview()` method throws `NotApprovedException::forSecondReview()`.
- `app/Exceptions/NotApprovedException.php:46-49` — `forSecondReview()` factory method.

## Implementation

1. **Remove** the `enforceSecondReview()` method from `PaymentStagingService`. The ruling says no approval system for now.
2. **Remove** any call sites that invoke `enforceSecondReview()`.
3. **Keep** `NotApprovedException::forSecondReview()` factory method but add a docblock noting DEC-005 resolved: "Retained for future approval workflow; currently not called per DEC-005 ruling."
4. Ensure the `post()` method on `PaymentStagingService` remains blocked by DEC-008 (balance formulas), NOT by DEC-005. The posting stub is a separate concern.
5. Verify audit trail: every `stage()`, `submit()`, and reversal action logs via `AuditService`. No new audit gaps.

## Pest tests

- Test that staging a payment with a single cashier user succeeds without requiring a second reviewer.
- Test that the audit event is created for every payment action (stage, submit, reverse) by the cashier.
- Remove any existing test that asserts `NotApprovedException` from `enforceSecondReview()`.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-001 | implemented | Removed second-review enforcement; cashier posts directly per DEC-005 | DEC-005 |

Previous entry to update: TASK-007 row mentioning DEC-005 stub.

## Acceptance criteria

- [ ] `enforceSecondReview()` removed from `PaymentStagingService`
- [ ] No code path throws `NotApprovedException::forSecondReview()`
- [ ] Audit trail covers all payment actions by single cashier
- [ ] Pest tests pass asserting cashier can stage/submit without second reviewer
- [ ] Code comments cite `DEC-005` where the second-review was previously required
- [ ] Change log updated

## Status

`pending`
