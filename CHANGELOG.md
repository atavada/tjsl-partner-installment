# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Partner search endpoint (`PartnerSearchService`, `PartnerController`, `SearchPartnerRequest`, `PartnerResource`) supporting exact NO ID (leading zeros preserved), name/alias, agreement number, and VA lookups
- Server-side pagination with configurable page size
- Sensitive field masking (NIK, phone, address, VA) per DEC-004
- Verification badge text labels per PRD FR-01
- Partner search UI (`resources/js/pages/Partners/Index.tsx` and `resources/js/pages/Partners/Show.tsx`)
- Feature tests in `tests/Feature/PartnerSearchTest.php`

### Changed

### Fixed
