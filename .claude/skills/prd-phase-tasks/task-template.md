# Task: <short name>

**Phase:** A
**ID:** TASK-<NNN>
**Depends on:** <task IDs, or "none">
**PRD reference:** <section(s) / FR number(s)>

## Goal

<One or two sentences. What exists after this task that didn't before.>

## Scope

- Files likely touched: <paths>
- Explicit non-goals: <what this task deliberately does not do>

## Rules to follow (no invented values)

- <Any relevant `DEC-NNN` IDs from /docs/decisions.md, with a one-line
  reminder of what's still OPEN and what the stub does in the meantime.
  Write "none — this task has no undocumented rule dependencies" if true.>

## Acceptance criteria

- [ ] <Quote the exact Phase A gate test bullet from PRD §9, if this task
      maps to one>
- [ ] Pest test written and passing
- [ ] No real/PII data used anywhere (code, tests, seed data)
- [ ] Server re-validates/recomputes anything sent from the client (if
      applicable)
- [ ] Change log entry added: `implemented` / `stubbed` / `blocked`

## Status

`not started`
