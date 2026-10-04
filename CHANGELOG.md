# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Project root `README.md` documenting Phase A prototype assumptions, tech stack, financial integrity rules, component status matrix, decisions log, and Phase B gate requirements (TASK-010, labeled `implemented`)
- Comprehensive documentation of 10 OPEN decisions (`DEC-002` through `DEC-011`) and resolved `DEC-001`
- Explicit delineation of implemented, stubbed, and blocked behaviors (`NotApprovedException`)
- Clear local setup, synthetic accounts guide, test execution commands, and MySQL vs TiDB compatibility notice
- Synthetic database seeders (`UserSeeder`, `PartnerSeeder`, `AgreementSeeder`, `PaymentSeeder`, `AuditEventSeeder`, and updated `DatabaseSeeder`) exercising all Phase A gate test edge cases per PRD §9
- Seed data for leading-zero NO IDs, same-name different-person partners, same-alias partners, draft agreements (no debt), active agreements, closed_by_rescheduling transitions, payments in all states (draft, submitted, posted, reversed), overpayments/ABT, and append-only audit trails
- Comprehensive feature tests in `tests/Feature/SyntheticSeederTest.php`
- Payment capture and staging workflow (`PaymentController`, `StorePaymentRequest`, `PaymentStagingService`, `AllocationService`, `PaymentReversalService`)
- React pages for payments (`resources/js/pages/Payments/Index.tsx`, `resources/js/pages/Payments/Create.tsx`, `resources/js/pages/Payments/Show.tsx`)
- Payment TypeScript interfaces (`resources/js/types/payment.ts`)
- Invariants enforcement: zero/negative rejection, over-allocation check, duplicate fingerprint detection, idempotent re-submission, unapplied overpayment (ABT) creation
- Compensating reversal entry creation with audit trail and original preservation
- Explicit gating for posting (DEC-008), period override (DEC-010), and second reviewer (DEC-005) throwing `NotApprovedException`
- Comprehensive feature tests in `tests/Feature/PaymentStagingTest.php`
- Agreement timeline and detail views (`resources/js/pages/Agreements/Index.tsx`, `resources/js/pages/Agreements/Show.tsx`, and `resources/js/components/AgreementTimeline.tsx`)
- Display of three independent status dimensions per PRD §4 invariant 8 (contract lifecycle, collectibility risk, and signing/document workflow)
- `AgreementController` with `index` and `show` endpoints, scoped by partner
- `AgreementResource` and `AgreementTransitionResource` for JSON and Inertia responses
- `BalanceService` stub returning explicit `unverified` status per DEC-008, preventing false zero balances, and marking draft agreements as creating no debt (PRD FR-02)
- `AgreementPolicy` with deny-by-default authorization and `AgreementView` permission in `Permission` enum
- Navigation link from Partner Show page to Agreement Timeline
- Comprehensive feature tests in `tests/Feature/AgreementTimelineTest.php`
- Partner search endpoint (`PartnerSearchService`, `PartnerController`, `SearchPartnerRequest`, `PartnerResource`) supporting exact NO ID (leading zeros preserved), name/alias, agreement number, and VA lookups
- Server-side pagination with configurable page size
- Sensitive field masking (NIK, phone, address, VA) per DEC-004
- Verification badge text labels per PRD FR-01
- Partner search UI (`resources/js/pages/Partners/Index.tsx` and `resources/js/pages/Partners/Show.tsx`)
- Feature tests in `tests/Feature/PartnerSearchTest.php`

### Changed
- Implemented balance formulas in `BalanceService` per DEC-008 and docs/formula-specification.md §3: computed integer balances (`remaining = contract + adjustments - payments`), separately dated reversal handling, negative balance exception states without flooring to 0, LUNAS condition (`data_verified AND remaining == 0`), rule versioning (`DEC-008-v1`), and updated `InstallmentSchedule::calculateOutstanding()` per DEC-008 (FIMPL-006)
- Implemented period derivation from receipt date per DEC-010: server-side derivation of receipt_month (YYYY-MM) on BankTransaction and period on PaymentAllocation, silent acceptance of matching period overrides, continued enforcement of NotApprovedException::forPeriodOverride() for differing overrides pending authority resolution, and rejection of forged client receipt_month payloads (FIMPL-005)
- Implemented CollectibilityStatus enum five FINAL labels and data-driven band lookup per DEC-007 and formula-specification.md §9.4 with zero-balance LUNAS override and legacy unverified bug guard; migrated existing agreement rows and updated frontend timeline badge variants (FIMPL-004)
- Implemented resolved sensitive-field unmasking policy per DEC-004: non-viewer roles (Operator, ReconciliationReviewer, ProcessOwner, SystemAdmin) access sensitive data (NIK, phone, address, VA, documents) unmasked by default, while Viewer role (Auditor) remains strictly masked/denied; added `Permission::isSensitive()` check, updated `User::hasPermission`, updated frontend partner detail/index masking presentation, and expanded Pest test suite (FIMPL-003)
- Updated `Role` enum display labels (`Role::label()`) to confirmed Indonesian business names (`Kasir TJSL`, `Kepala Sub Divisi`, `Sekper / Kepala Divisi`, `Viewer`, `System Admin`) and added `businessName()` alias per DEC-009; added `User::$role_label` accessor and frontend TypeScript `ROLE_LABELS` mapping (FIMPL-002)
- Removed second-review enforcement (`PaymentStagingService::enforceSecondReview`) per DEC-005 ruling; cashier stages and submits payments directly without secondary approval while retaining full audit logging and reversible compensating entries (FIMPL-001)

### Fixed
