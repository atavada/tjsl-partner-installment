# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
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

### Fixed
