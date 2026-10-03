# Financial & data integrity (critical — always applies, no paths filter)

Source of truth: `/docs/PRD.md`, `/docs/decisions.md`, `/docs/metric-definitions.md`, and `/docs/formula-specification.md`.
This repository is a **synthetic-data prototype**. Only synthetic data is permitted.
Formulas and rules documented and `RESOLVED` in `/docs/decisions.md` and approved in
`/docs/metric-definitions.md` are approved to implement exactly as specified.
Do not implement Phase C/D behavior — real-data migration, production cutover, or live
integrations — even if asked, unless explicitly overridden and signed off.
Phase C/D remains strictly **not approved** regardless of this update.

## Approved rules vs. never guess

If code needs a formula, rounding rule, status transition, approval threshold,
uniqueness scope, or precedence order:

1. **Resolved rules are approved to implement.** If documented and `RESOLVED` in
   `/docs/decisions.md` or approved in `/docs/metric-definitions.md`, implement
   it exactly as specified. Always cite the DEC-ID (e.g. `DEC-007`, `DEC-008`) or
   metric version (e.g. `Remaining Principal v1`) in code comments or docstrings.
2. **Never guess an unapproved or open rule.** If not stated in `/docs/PRD.md`,
   `/docs/decisions.md`, or `/docs/metric-definitions.md`, OR if the item is still
   `OPEN` (or `RESOLVED IN PART` for open portions) in `/docs/decisions.md`:
   - **Stop.** Do not invent a plausible-looking value or default.
   - For new questions: append an entry to `/docs/decisions.md` with status `OPEN`,
     the question, why it matters, proposed default, and an owner from PRD §3.
   - Implement only an interface or stub that **fails loudly** — throw a dedicated
     exception (e.g. `NotApprovedException`) or return an explicit `unverified` /
     `not available` result. Never a silent zero or a default that looks real.
   - Log it in the change log as `blocked` or `stubbed`, referencing the decision ID.

## Data rules

- Synthetic data only: no real data anywhere — not in code, migrations, tests,
  seeders, logs, fixtures, commit messages, screenshots, or chat.
- No real-data migration: synthetic data only; real-data migration and ETL are
  gated behind Phase C/D approval.
- Money: integer minor units (IDR), never floats.
- Identifiers (`partner_no_id`, NIK, VA, agreement number): stored as
  strings, leading zeros preserved. Keep the raw value and a separate
  normalized lookup key in distinct columns. Do not rely on
  case-insensitive collation for exact match — verify actual collation
  behavior on **TiDB**, don't assume MySQL defaults.
- No physical delete of financial records. Corrections are dated
  reversal/compensating entries with actor + reason attached.
- Every write path (payment, allocation, adjustment, transition) uses a DB
  transaction, an idempotency key, and optimistic concurrency (version
  number). Verify locking and isolation behavior on TiDB rather than
  assuming it matches MySQL.
- The server recomputes and re-validates every total and every ID; never
  trust an amount, balance, or identifier sent from the browser.

## Process

- Maintain a change log entry per unit of work, labeled exactly one of:
  `implemented`, `stubbed`, `blocked`.
- Cite DEC-ID or metric version in code comments and docstrings when implementing
  approved rules.
- Before creating any file under `.claude/`, or `CLAUDE.md`, or `.mcp.json`:
  check whether it already exists. Never overwrite existing project
  configuration — this repo already has its own `.claude/` setup.
- If asked to work on Phase C or D functionality (real-data migration, cutover),
  point to the relevant Phase gate in PRD §9 and confirm it is signed off before
  proceeding.
