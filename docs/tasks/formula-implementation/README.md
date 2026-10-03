# Formula Implementation Tasks

Tasks that replace Phase A stubs (`NotApprovedException` / `unverified`)
with real implementations based on **RESOLVED** decisions in
`docs/decisions.md` and approved metrics in `docs/metric-definitions.md`.

## Ordering

Tasks are ordered by dependency — later tasks may depend on earlier ones.

| ID | DEC | Summary | Depends on |
|----|-----|---------|------------|
| FIMPL-001 | DEC-005 | Remove second-review enforcement (cashier posts directly) | — |
| FIMPL-002 | DEC-009 | Update Role enum labels to confirmed business names | — |
| FIMPL-003 | DEC-004 | Implement resolved sensitive-field masking policy | FIMPL-002 |
| FIMPL-004 | DEC-007 | Replace CollectibilityStatus enum with five FINAL labels | — |
| FIMPL-005 | DEC-010 | Implement period derivation from receipt date | — |
| FIMPL-006 | DEC-008 | Implement balance formulas in BalanceService | FIMPL-005 |
| FIMPL-007 | DEC-008 | Implement allocation algorithm (admin-first, oldest-due-first) | FIMPL-006 |
| FIMPL-008 | DEC-006 | Refactor Overpayment model to four-concept ABT fund model | FIMPL-007 |

## Rules

- Each task replaces a specific stub with the exact approved formula/rule.
- Code comments and docstrings must cite the DEC-ID and metric version.
- Pest tests must assert actual computed values, not just `unverified`.
- Change-log entry updates from `stubbed` to `implemented`.
- Still-OPEN portions within a `RESOLVED IN PART` decision remain stubbed.
- Source evidence: `docs/master-compilation.md` and `docs/formula-specification.md`.
