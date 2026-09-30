# Task: Core domain schema and migrations (Partners, Aliases, VirtualAccounts)

**Phase:** A
**ID:** TASK-001
**Depends on:** none
**PRD reference:** §4 (Partner, PartnerAlias, VirtualAccount entities), §2 (architecture rules)

## Goal

Create the foundational domain schema: `partners`, `partner_aliases`, and `virtual_accounts` tables with UUID primary keys, string-typed identifiers preserving leading zeros, raw + normalized lookup columns, and verification state tracking. Establish base model classes with `$fillable`, traits, and relationships.

## Scope

- Files likely touched:
    - `database/migrations/YYYY_MM_DD_HHMMSS_create_partners_table.php`
    - `database/migrations/YYYY_MM_DD_HHMMSS_create_partner_aliases_table.php`
    - `database/migrations/YYYY_MM_DD_HHMMSS_create_virtual_accounts_table.php`
    - `app/Models/Partner.php`
    - `app/Models/PartnerAlias.php`
    - `app/Models/VirtualAccount.php`
    - `app/Concerns/HasUuid.php` (or similar trait)
    - `docs/data-dictionary.md` (update Partner, PartnerAlias, VirtualAccount sections)
- Explicit non-goals:
    - No controller/route/UI work
    - No search endpoint (that's TASK-004)
    - No RBAC enforcement (that's TASK-002)

## Rules to follow (no invented values)

- DEC-004: Who may unmask sensitive fields — NIK, phone, VA stored but masking enforcement deferred to TASK-002/TASK-004. Schema stores raw values; access control is separate.
- Money columns: integer (IDR), never float (PRD §2).
- Identifiers (`partner_no_id`, NIK, VA): string columns with separate raw and normalized columns (PRD §2).
- UUIDs as internal primary keys (PRD §4).
- `partner_no_id` nullable in staging, unique once verified (PRD §4 Partner).
- Version column for optimistic concurrency (PRD §2).

## Acceptance criteria

- [x] "leading-zero NO ID round-trip and exact-match lookup on TiDB" — partner_no_id stores and retrieves leading zeros; exact match query works
- [x] "NO ID, NIK, VA, agreement number and row number stored as distinct fields" — partner_no_id and NIK on Partner, VA on VirtualAccount are separate string columns
- [x] Pest test written and passing (model creation, leading-zero preservation, unique constraints)
- [x] No real/PII data used anywhere (code, tests, seed data)
- [x] Change log entry added: `implemented` / `stubbed` / `blocked`

## Status

`done`
