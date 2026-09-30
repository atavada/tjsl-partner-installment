---
paths:
  - "database/**/*.php"
  - "app/Models/**/*.php"
---
# TiDB caveats (read alongside database-mysql.md)

The dev database (`installment_app`) is **TiDB via a MySQL-compatible
interface**, not MySQL itself. A local MySQL 9 may also exist but is not the
target. Do not assume MySQL 8 behavior carries over — verify each of these on
TiDB before relying on it, and note the verification (with evidence, e.g. a
Pest test or a query result) in `/docs/decisions.md` if it changes a design
choice:

- **Collation / exact match.** Confirm whether the collation in use is
  case-sensitive for exact-match lookups on identifiers (NO ID, NIK, VA,
  agreement number). Do not assume `utf8mb4_unicode_ci`-style behavior.
- **Unique constraints.** Confirm uniqueness scope and enforcement timing
  match what the migration expects.
- **Locking and isolation.** TiDB's transaction model (optimistic by
  default in some modes) differs from InnoDB. Verify `SELECT ... FOR UPDATE`,
  row locks, and the isolation level actually used before depending on them
  for balance/allocation correctness.
- **Auto-increment / ID generation.** This project uses UUIDs as internal
  keys (PRD §4), so this mostly doesn't apply — but double-check any
  sequence-like behavior if it shows up (e.g. row numbers, import batch IDs).

If Boost's `database-schema` tool reports something inconsistent with these
notes, trust the tool's live output over this file and update this file.
