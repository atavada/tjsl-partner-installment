# Task: Synthetic seed data

**Phase:** A
**ID:** TASK-009
**Depends on:** TASK-001, TASK-003, TASK-006
**PRD reference:** §9 (Phase A deliverable: synthetic seed), §1 (synthetic data only), §2

## Goal

Create database seeders generating synthetic (fake, no real PII) data for all Phase A entities: partners with aliases, virtual accounts, agreements with transitions and documents, bank transactions, payment allocations (in various states), overpayments, and audit events. Data must exercise edge cases from Phase A gate tests: leading-zero NO IDs, same-name different partners, draft agreements, unmatched payments, reversed payments.

## Scope

- Files likely touched:
  - `database/seeders/DatabaseSeeder.php`
  - `database/seeders/PartnerSeeder.php`
  - `database/seeders/AgreementSeeder.php`
  - `database/seeders/PaymentSeeder.php`
  - `database/seeders/AuditEventSeeder.php`
  - `database/factories/PartnerFactory.php`
  - `database/factories/AgreementFactory.php`
  - `database/factories/BankTransactionFactory.php`
  - `database/factories/PaymentAllocationFactory.php`
  - (other factories as needed)
- Explicit non-goals:
  - Real/legacy data import
  - Six-figure scale benchmark data (PRD §8 mentions benchmarking but Phase A seed is for functional testing)

## Rules to follow (no invented values)

- No real data anywhere: no real names, NIK, phones, VA numbers, bank references, contract PDFs (PRD §1).
- Synthetic data must include edge cases for gate tests (PRD §9).
- Use Faker with Indonesian locale where appropriate for realistic-looking synthetic data.
- Leading zeros in NO IDs and agreement numbers must be present in seed data.
- Include at least: two partners with identical alias names, partners with/without NO ID, draft and active agreements, cyclic transition attempt data (for testing rejection).

## Acceptance criteria

- [ ] Seeder runs without error on clean database
- [ ] Seed data includes partners with leading-zero NO IDs
- [ ] Seed data includes same-name different-person partners
- [ ] Seed data includes draft agreements (no debt), active agreements, closed_by_rescheduling
- [ ] Seed data includes payment in each state (draft, submitted, posted, reversed)
- [ ] Seed data includes at least one overpayment/ABT record
- [ ] Pest test written and passing (seeder runs, expected records exist)
- [ ] No real/PII data used anywhere
- [ ] Change log entry added: `implemented`

## Status

`not started`
