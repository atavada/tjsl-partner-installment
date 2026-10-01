# Phase A — Task Index

**Phase:** A — Prototype (synthetic data only)
**PRD reference:** §9
**Gate:** ~14 Pest tests listed in PRD §9 must pass before Phase B.

## Dependency order

Execute tasks in this order. Tasks at the same level can run in parallel if independent.

```
TASK-001 (Partner schema)
├── TASK-002 (Auth/RBAC) ──────────────────┐
│   ├── TASK-004 (Partner search) ◄────────┤
│   └── TASK-008 (Audit events)            │
├── TASK-003 (Agreement schema)            │
│   └── TASK-005 (Agreement timeline) ◄────┘
│                                          │
├── TASK-006 (Payment schema) ◄── TASK-003 │
│   └── TASK-007 (Payment staging) ◄───────┘
│
├── TASK-009 (Synthetic seed) ◄── TASK-001, TASK-003, TASK-006
└── TASK-010 (README) ◄── all above
```

## Task list

| ID | Task | Status | Depends on | Key gate tests covered |
|----|------|--------|------------|----------------------|
| TASK-001 | [Core domain schema (Partners, Aliases, VAs)](TASK-001-partner-schema.md) | `done` | none | leading-zero NO ID round-trip; distinct fields for NO ID/NIK/VA |
| TASK-002 | [Auth scaffold and RBAC](TASK-002-auth-rbac.md) | `done` | TASK-001 | unauthorized posting/access denied |
| TASK-003 | [Agreement schema, transitions, documents](TASK-003-agreement-schema.md) | `not started` | TASK-001 | cyclic addendum rejected; closed_by_rescheduling ≠ paid_off; status changes don't change balances |
| TASK-004 | [Partner and alias search](TASK-004-partner-search.md) | `not started` | TASK-001, TASK-002 | leading-zero exact match; same-name separate candidates |
| TASK-005 | [Agreement timeline page](TASK-005-agreement-timeline.md) | `not started` | TASK-003, TASK-004 | unverified balance renders `unverified` not zero; status independence |
| TASK-006 | [Payment schema (transactions, allocations, ABT)](TASK-006-payment-schema.md) | `not started` | TASK-001, TASK-003 | zero/negative rejected; duplicate rejected; over-allocation rejected |
| TASK-007 | [Payment staging and allocation proposal](TASK-007-payment-staging.md) | `not started` | TASK-002, TASK-004, TASK-006 | name-only stays unmatched; reversal preserves audit trail; server recomputes totals |
| TASK-008 | [Audit events system](TASK-008-audit-events.md) | `not started` | TASK-002 | reversal audit trail; unauthorized access logged |
| TASK-009 | [Synthetic seed data](TASK-009-synthetic-seed.md) | `done` | TASK-001, TASK-003, TASK-006 | — (exercises all gate tests via data) |
| TASK-010 | [README with assumptions and blocked decisions](TASK-010-readme.md) | `not started` | all above | — (documentation deliverable) |

## Decisions (from docs/decisions.md)

| Decision | Status | Affects tasks | Impact |
|----------|--------|---------------|--------|
| DEC-001 Agreement number uniqueness scope | **RESOLVED** | TASK-003, TASK-004 | Not unique; grouping key only. No uniqueness constraint. |
| DEC-002 Lifecycle states and transitions | PROPOSED | TASK-003, TASK-005 | Enum with proposed states; transitions throw `NotApprovedException` |
| DEC-003 Signing/document states | PROPOSED | TASK-003, TASK-005 | Five workflow states + signature summary; transitions blocked |
| DEC-004 Sensitive field unmask policy | PROPOSED | TASK-002, TASK-004 | Per-field permissions; all masked by default |
| DEC-005 Second reviewer mandatory? | PROPOSED | TASK-007 | Mandatory maker-checker default; relaxation needs approved policy |
| DEC-006 ABT disposition policy | PROPOSED | TASK-006 | Evidence/proposals only; execution blocked |
| DEC-007 Collectibility levels | PROPOSED | TASK-003 | Raw four-category labels (`lancar`/`kurang_lancar`/`bermasalah`/`unknown`); no invented five-level banking classification |
| DEC-008 Balance calculation rules | PROPOSED | TASK-005, TASK-006, TASK-007 | `balance()` returns `unverified`; ledger identity proposed for review |
| DEC-009 RBAC permission matrix | PROPOSED | TASK-002 | Action-level deny-by-default from Phase A, not coarse gates |
| DEC-010 Period derivation rule | PROPOSED | TASK-007 | Receipt month from receipt date; override stores reason, blocked |
| DEC-011 Bank reference uniqueness | PROPOSED | TASK-006 | Verified namespace-based uniqueness; source-row idempotency separate |

## Phase A gate test mapping

Every PRD §9 gate test maps to at least one task:

| Gate test | Primary task(s) |
|-----------|----------------|
| leading-zero NO ID round-trip and exact-match lookup | TASK-001, TASK-004 |
| same-name different-person returns separate candidates | TASK-004 |
| NO ID, NIK, VA, agreement number, row number as distinct fields | TASK-001, TASK-003 |
| cyclic addendum rejected | TASK-003 |
| name-only payment stays unmatched | TASK-007 |
| `closed_by_rescheduling` is not `paid_off` | TASK-003 |
| zero and negative payment rejected | TASK-006, TASK-007 |
| duplicate payment rejected; idempotent ingestion | TASK-006, TASK-007 |
| over-allocation rejected by DB-level and service checks | TASK-006, TASK-007 |
| reversal preserves original and audit trail | TASK-007, TASK-008 |
| unauthorized posting and unauthorized field access denied | TASK-002, TASK-008 |
| unverified balance renders `unverified`, not zero | TASK-005 |
| status changes (signing/lifecycle) do not change balances | TASK-003, TASK-005 |
