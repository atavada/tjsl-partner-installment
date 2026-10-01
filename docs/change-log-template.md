# Change log

One entry per unit of work (usually one task from `docs/tasks/phase-a/`).
Every entry must use exactly one label: `implemented`, `stubbed`, or
`blocked`. `blocked` entries must reference a decision ID from
`docs/decisions.md`.

| Date       | Task ID  | Label       | Summary                                                                 | Decision ref (if stubbed/blocked) |
| ---------- | -------- | ----------- | ----------------------------------------------------------------------- | --------------------------------- |
| 2026-09-30 | TASK-001 | implemented | Partners, PartnerAlias, VirtualAccount schema, models, factories, tests |                                   |
| 2026-09-30 | TASK-002 | stubbed     | Auth scaffold and RBAC with deny-by-default policies; fine-grained matrix deferred per DEC-009 | DEC-009, DEC-004                 |
| 2026-09-30 | TASK-003 | stubbed     | Agreement domain schema, acyclic transition graph, private document storage; lifecycle transitions and balance calculation deferred | DEC-002, DEC-003, DEC-008        |
| 2026-09-30 | TASK-004 | implemented | Partner search endpoint across NO ID, name/alias, agreement, VA with server-side pagination, masking, and verification badges |                                   |
