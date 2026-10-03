# Task: Replace CollectibilityStatus enum with five FINAL labels

**Phase:** Formula Implementation
**ID:** FIMPL-004
**Depends on:** —
**PRD reference:** §4 (Agreement — collectibility dimension)

## Decision

**DEC-007 — Collectibility classification levels — `RESOLVED` 2026-10-03 (labels and month-expressed bands FINAL; DP-2 OPEN)**

> "Owner confirmed FINAL five-label mapping with month-expressed bands. This
> supersedes the provisional four-label variant and the code's
> Current/Substandard/Loss/Unknown enum."
>
> | Late months | Label |
> |---|---|
> | 0 to 1 | Lancar |
> | 2 to 6 | Kurang Lancar |
> | 7 to 9 | Diragukan (Ragu-ragu) |
> | over 9 | Bermasalah (Macet) |
> | balance = 0 | LUNAS (override) |
>
> "Implementation rules:
> - Make labels and bands data-driven via a versioned rule table, with the
>   confirmed five-label mapping as the active version.
> - Current code `CollectibilityStatus` enum has Current/Substandard/Loss/Unknown
>   — needs Lancar, KurangLancar, Diragukan, Bermasalah, and Lunas override.
>   Never turn Unknown into Lunas.
> - Empty/unknown collectibility must never become Lunas (legacy bug to avoid).
> - The label function from `docs/formula-specification.md` §9.4 applies."
>
> — `docs/decisions.md` DEC-007, source: `docs/master-compilation.md` §5; `docs/formula-specification.md` §9

**Collectibility label function (formula-specification.md §9.4):**

> ```text
> label(as_of):
>   if NOT data_verified            -> "Unknown"          # never Lunas
>   if remaining(as_of) == 0        -> "Lunas"
>   m = late_months(as_of) per the resolved DP-2 month-count semantics
>   apply the FINAL five-label month band table from 9.2 via the active rule_version
> ```

**Metric ref:** Collectibility Label v1 (`docs/metric-definitions.md`)

## Current stub

- `app/Enums/CollectibilityStatus.php` — four cases: `Current` ('current'), `Substandard` ('substandard'), `Loss` ('loss'), `Unknown` ('unknown'). Labels: Lancar, Kurang Lancar, Bermasalah, Tidak Diketahui.
- Missing: `Diragukan` and `Lunas`.
- No `NotApprovedException` gate — enum was never exception-gated, just incomplete.
- `collectibility_status` column on `agreements` table: varchar(30), DEC-007 ref.

## Implementation

1. Replace `CollectibilityStatus` enum cases:
   - `Lancar = 'lancar'` (label: "Lancar")
   - `KurangLancar = 'kurang_lancar'` (label: "Kurang Lancar")
   - `Diragukan = 'diragukan'` (label: "Diragukan")
   - `Bermasalah = 'bermasalah'` (label: "Bermasalah")
   - `Lunas = 'lunas'` (label: "LUNAS")
   - `Unknown = 'unknown'` (label: "Tidak Diketahui")
2. Add `monthBand()` static method returning the month-band thresholds as data (not hardcoded if/else).
3. Add `fromLateMonths(int $lateMonths): self` static factory that applies the FINAL band table. Store bands in a versioned config/constant with `rule_version`.
4. Add `fromBalance(int $remainingBalance, bool $dataVerified, int $lateMonths): self` that implements the §9.4 label function:
   - `!$dataVerified` → `Unknown`
   - `$remainingBalance === 0` → `Lunas`
   - else → `fromLateMonths($lateMonths)`
5. **DP-2 is still OPEN.** The `$lateMonths` parameter is an input, not computed by this task. The counting algorithm that produces `$lateMonths` from installment data remains stubbed. This task only implements the label lookup given a month count.
6. Add migration or update to handle existing `collectibility_status` column values: `current` → `lancar`, `substandard` → `kurang_lancar`, `loss` → `bermasalah`. Add `diragukan` and `lunas` as valid values.
7. Update `Agreement` model cast and any Inertia props that reference collectibility.
8. Docblock cites `DEC-007`, `Collectibility Label v1`.

## Pest tests

- Assert `CollectibilityStatus::fromLateMonths(0)` returns `Lancar`.
- Assert `CollectibilityStatus::fromLateMonths(1)` returns `Lancar`.
- Assert `CollectibilityStatus::fromLateMonths(2)` returns `KurangLancar`.
- Assert `CollectibilityStatus::fromLateMonths(6)` returns `KurangLancar`.
- Assert `CollectibilityStatus::fromLateMonths(7)` returns `Diragukan`.
- Assert `CollectibilityStatus::fromLateMonths(9)` returns `Diragukan`.
- Assert `CollectibilityStatus::fromLateMonths(10)` returns `Bermasalah`.
- Assert `CollectibilityStatus::fromBalance(0, true, 5)` returns `Lunas`.
- Assert `CollectibilityStatus::fromBalance(100000, false, 0)` returns `Unknown`.
- Assert `CollectibilityStatus::fromBalance(100000, true, 3)` returns `KurangLancar`.
- Assert Unknown never becomes Lunas even with zero balance if `!$dataVerified`.

## Change log update

| Date | Task ID | Label | Summary | Decision ref |
|------|---------|-------|---------|-------------|
| TBD | FIMPL-004 | implemented | CollectibilityStatus enum: five FINAL labels + band lookup per DEC-007 | DEC-007 |

## Acceptance criteria

- [ ] Enum has six cases: Lancar, KurangLancar, Diragukan, Bermasalah, Lunas, Unknown
- [ ] `fromLateMonths()` applies FINAL band table as data-driven lookup
- [ ] `fromBalance()` implements §9.4 label function exactly
- [ ] Unknown never becomes Lunas (legacy bug guard)
- [ ] Existing data migrated: current→lancar, substandard→kurang_lancar, loss→bermasalah
- [ ] Docblock cites `DEC-007`, `Collectibility Label v1`
- [ ] DP-2 month-count algorithm remains stubbed (input parameter, not computed)
- [ ] Pest tests assert all band boundaries
- [ ] Change log updated

## Status

`pending`
