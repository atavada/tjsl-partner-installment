# Task: README documenting assumptions and blocked decisions

**Phase:** A
**ID:** TASK-010
**Depends on:** TASK-001 through TASK-009 (write after all other Phase A tasks)
**PRD reference:** §9 (Phase A deliverable: "README documenting assumptions and blocked decisions")

## Goal

Write a project README covering: what this prototype does, tech stack, setup instructions, how to run tests, list of assumptions made during Phase A, explicit list of blocked decisions (referencing `docs/decisions.md`), what is stubbed vs implemented, and what Phase B requires before proceeding.

## Scope

- Files likely touched:
  - `README.md`
  - (References `docs/decisions.md`, `docs/data-dictionary.md`, `docs/PRD.md`)
- Explicit non-goals:
  - Deployment documentation (not Phase A)
  - User manual (not Phase A)

## Rules to follow (no invented values)

- Reference every OPEN decision from `docs/decisions.md`.
- Do not claim any financial rule is implemented if it's stubbed.
- Do not claim TiDB compatibility is verified unless tests actually ran on TiDB.
- Be explicit about what throws `NotApprovedException`.

## Acceptance criteria

- [ ] README includes setup instructions (composer install, npm install, migrate, seed)
- [ ] README lists all OPEN decisions from `docs/decisions.md` with brief descriptions
- [ ] README clearly states what is `implemented`, `stubbed`, and `blocked`
- [ ] README documents Phase B gate requirements (PRD §9)
- [ ] No real/PII data in examples or screenshots
- [ ] Change log entry added: `implemented`

## Status

`not started`
