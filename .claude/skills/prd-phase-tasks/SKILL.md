---
description: Break the PRD's currently-approved phase into small, individually verifiable task files under docs/tasks/. Refuses to guess undocumented financial rules — routes them to docs/decisions.md instead.
disable-model-invocation: true
argument-hint: "[phase letter, defaults to A] — do not pass B/C/D unless you know it isn't approved yet"
---

Break down PRD phase: $ARGUMENTS (default: A)

## 0. Read before doing anything

Read, in full:

- `/docs/PRD.md` (the PRD's own instructions in its §0 override anything
  that conflicts with the general defaults below)
- `/docs/decisions.md` if it exists (don't re-raise a question already
  logged there)
- `CLAUDE.md` and any `.claude/rules/*.md` already in the repo — do not
  duplicate or contradict them

## 1. Confirm the target phase

Default to **Phase A**. The PRD is explicit: only Phase A is build-ready now;
everything else is blocked on staff decisions (PRD §0, §9). If the argument
names a later phase (B, C, D):

- Stop and tell the user Phase A's own gate (the ~14 Pest tests in PRD §9)
  must pass and be reviewed first.
- If they explicitly confirm they understand it isn't approved and want the
  breakdown anyway (e.g. for planning purposes only), proceed but mark
  **every** resulting task `blocked` and do not let `/dw-run-task`-style
  execution start on them.

## 2. Decompose Phase A's deliverables into tasks

Phase A deliverables (PRD §9): domain schema and migrations, auth/RBAC
scaffold, partner/alias search, agreement timeline, payment staging and
allocation *proposal* (not posting — posting rules are gated), audit events,
synthetic seed data, and a README documenting assumptions and blocked
decisions.

For each deliverable area, create one file in `docs/tasks/phase-a/` using
`task-template.md` in this skill folder. Keep tasks small: one task should be
completable, tested, and reviewable as a single PR. Order them by actual
dependency (e.g. schema/migrations → RBAC → partner/alias search →
agreement timeline → payment staging → audit events → seed data), and write
that order into `docs/tasks/phase-a/README.md` as an index with a one-line
description of each task and its status.

## 3. Never fill a gap with a guess

While writing each task, if it needs a rule the PRD does not state
(a formula, rounding rule, status transition, approval threshold, uniqueness
scope, precedence order) — do not invent it, and do not silently pick "the
obvious" interpretation. Instead:

1. Create `/docs/decisions.md` from `decisions-template.md` in this skill
   folder if it doesn't exist yet.
2. Append a row: ID (`DEC-NNN`), the question, why it matters, a proposed
   default if you have a reasonable one to suggest, status `OPEN`, and an
   owner drawn from the PRD §3 role table (leave blank if unclear).
3. In the task file, reference the decision ID instead of a value, and mark
   the task's relevant acceptance criterion as "stub only — throws
   `NotApprovedException` (or equivalent) until `DEC-NNN` is resolved."

## 4. Every task file must include

- **Goal** — one or two sentences.
- **Depends on** — other task IDs, or "none".
- **PRD reference** — section and FR number(s).
- **Scope** — files likely touched; explicit non-goals.
- **Rules to follow** — any `DEC-NNN` IDs this task is blocked or informed by.
- **Acceptance criteria** — as a checklist, including:
  - The exact Phase A gate test bullet(s) from PRD §9 this task must satisfy
    (quote them, don't paraphrase into something looser).
  - A Pest test exists and passes.
  - No real/PII data anywhere in code, tests, or seed data.
  - A change log entry added, labeled `implemented`, `stubbed`, or `blocked`.
- **Status** — `not started` initially.

## 5. Stop before executing

This skill only plans. Do not write application code, run migrations, or
touch the database in this skill. When done:

- Show the full task index.
- List every Phase A deliverable you could **not** turn into a concrete task
  and why (usually because it depends on an OPEN decision) — this becomes
  part of the "blocked" section of the change log.
- Wait for the user to approve the task list, then work through tasks one at
  a time (plan mode → implement → test → `/pre-commit-check` → commit),
  never batching several tasks into one uninterrupted run.
