# Financial & data integrity (critical — always applies, no paths filter)

Source of truth: `/docs/PRD.md` and `/docs/decisions.md`. This repository is a
**synthetic-data prototype**. Only **Phase A** (PRD §9) is approved to build.
Do not implement Phase B/C/D behavior — migration, cutover, or real financial
rules — even if asked, unless the person explicitly overrides this in the
conversation and understands it is not yet approved.

## Never guess a financial rule

If code needs a formula, rounding rule, status transition, approval threshold,
uniqueness scope, or precedence order that `/docs/PRD.md` does not state:

1. **Stop.** Do not invent a plausible-looking value or default.
2. Append an entry to `/docs/decisions.md` (create it from
   `docs/decisions-template.md` if it doesn't exist yet): status `OPEN`, the
   question, why it matters, a proposed default if you have one, and an
   owner drawn from the PRD §3 role table (leave unassigned if unclear).
3. Implement only an interface or stub for that behavior that **fails loudly**
   — throw a dedicated exception (e.g. `NotApprovedException`) or return an
   explicit `unverified` / `not available` result. Never a silent zero or a
   default that looks like a real answer.
4. Log it in the change log as `blocked`, referencing the decision ID.

## Data rules

- Money: integer minor units (IDR), never floats.
- Identifiers (`partner_no_id`, NIK, VA, agreement number): stored as
  strings, leading zeros preserved. Keep the raw value and a separate
  normalized lookup key in distinct columns. Do not rely on
  case-insensitive collation for exact match — verify actual collation
  behavior on **TiDB**, don't assume MySQL defaults.
- No real data anywhere — not in code, migrations, tests, seeders, logs,
  fixtures, commit messages, screenshots, or this chat. Synthetic data only.
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
- Before creating any file under `.claude/`, or `CLAUDE.md`, or `.mcp.json`:
  check whether it already exists. Never overwrite existing project
  configuration — this repo already has its own `.claude/` setup.
- If asked to work on Phase B, C, or D functionality (real rule
  implementation, real-data migration, cutover), point to the relevant
  Phase gate in PRD §9 and confirm it is signed off before proceeding.
