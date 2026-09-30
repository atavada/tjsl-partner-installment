# Metric definitions

Repository deliverable required by PRD §9, backing the `MetricDefinition`
entity (§4) and the dashboard requirement (FR-11). Every metric shown on
any dashboard or export must have a row here, versioned, before it ships.

**No metric in this file may be invented.** If a dashboard needs a number
and its exact formula, population, or date semantics isn't settled, add an
`OPEN` row to `docs/decisions.md` instead of guessing a formula here — the
dashboard shows `unverified` for that metric until it's resolved.

## Template

```
### <Metric name> — v<N>

- **Status:** draft | approved
- **Owner:** <role from PRD §3>
- **Formula / scope:** <exact definition>
- **Numerator:** <what's counted, and what's excluded>
- **Denominator:** <what's counted, and what's excluded>
- **Date semantics:** <as-of date meaning — transaction date? posting date? effective date?>
- **Included population:** <which agreements/partners/states count>
- **Excluded / unclassified:** <what falls outside, and where it's shown — PRD FR-11 requires an explicit "unclassified" bucket so category sums equal totals>
- **Decision ref:** <DEC-NNN if this formula depends on an OPEN/RESOLVED decision>
- **Approved by:** <name/role + date, once status is "approved">
```

## Definitions

<!-- No metrics are approved yet — Phase A ships schema and a dashboard
     scaffold, not live metric calculations (installment/balance math is
     gated behind Phase B policy approval per PRD §4's balance() interface
     and §9). Append metric entries here only once a formula is approved,
     using the template above. -->
