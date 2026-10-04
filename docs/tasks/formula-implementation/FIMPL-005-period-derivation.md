# Task: Implement period derivation from receipt date

**Phase:** Formula Implementation
**ID:** FIMPL-005
**Depends on:** —
**PRD reference:** FR-03 (Payment capture), §4 (BankTransaction — receipt_month)

## Decision

**DEC-010 — Payment period derivation and override — `RESOLVED` 2026-10-03**

> "Owner confirmed: period derived from receipt date. Monthly history runs from
> loan start. A month with no payment shows Rp 0 and status 'Belum Bayar'. These
> Rp 0 rows are display only — never fabricate zero-value transaction records."
>
> "Still OPEN. History end rule (how far into the future to show), oldest-first
> display ordering, and the distinction between receipt month and liability month
> (see `docs/formula-specification.md` §4)."
>
> — `docs/decisions.md` DEC-010, source: `docs/master-compilation.md` §2; `docs/formula-specification.md` §4

**Formula specification §4 (schedule and due dates):**

> "Receipt month is not the liability month. Keep both: `receipt_date/month` and
> the installment(s) each allocation clears (see section 6)."

## Current stub

- `app/Http/Requests/StorePaymentRequest.php:110-118` — period override check throws `NotApprovedException::forPeriodOverride()` when override differs from derived month.
- `app/Exceptions/NotApprovedException.php:41-44` — `forPeriodOverride()` factory method.

## Implementation

1. **Period derivation is now approved.** Replace the `NotApprovedException` throw with actual derivation logic:
   - `receipt_month = Carbon::parse($receiptDate)->format('Y-m')`
   - Store derived `receipt_month` on `BankTransaction` (already exists as column).
   - Store `period` on `PaymentAllocation` as the same derived month (default).
2. **Period override remains partially OPEN.** The derivation itself is approved. The override authority and closed-period rules are still OPEN. Replace the current behavior:
   - If `period_override` matches `receipt_month`, accept silently (no override needed).
   - If `period_override` differs from `receipt_month`, still throw `NotApprovedException::forPeriodOverride()` — override policy is still OPEN.
   - Add docblock: "Period derivation: DEC-010 RESOLVED. Override authority: still OPEN."
3. Ensure `receipt_month` is computed server-side from `receipt_date`, never trusted from browser.
4. Update `PaymentStagingService::stage()` to populate `receipt_month` during transaction creation.

## Pest tests

- Assert `receipt_month` is derived as `YYYY-MM` from `receipt_date` for a payment.
- Assert `period_override` matching derived month is accepted without exception.
- Assert `period_override` differing from derived month throws `NotApprovedException::forPeriodOverride()` (override authority still OPEN).
- Assert `receipt_month` is server-computed, not taken from request input.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| 2026-10-04 | FIMPL-005 | implemented | Period derivation from receipt date per DEC-010; override authority still stubbed | DEC-010 |

Previous entry to update: TASK-006 and TASK-007 rows mentioning DEC-010 stub.

## Acceptance criteria

- [x] `receipt_month` derived server-side from `receipt_date` as `YYYY-MM`
- [x] Matching period override accepted silently
- [x] Differing period override still throws (override authority OPEN)
- [x] Docblock cites `DEC-010` with clear resolved/OPEN distinction
- [x] Pest tests assert derivation and override behavior
- [x] Change log updated

## Status

`implemented`
