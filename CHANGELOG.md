# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
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
