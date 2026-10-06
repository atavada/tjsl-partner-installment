# Remediation Task Specification: System Findings & Gap Resolution

**Source:** Security, integrity, and functional gap review (27 findings across 8 feature domains).  
**Target Environment:** Laravel 12 + Inertia.js v2 (React 19 + TypeScript) + TiDB / MySQL 8.  
**Governing Documents:** `/docs/PRD.md`, `/docs/decisions.md`, `/docs/metric-definitions.md`, `/docs/formula-specification.md`, `.claude/rules/financial-integrity.md`.

---

## 1. Executive Summary & Priority Order

| Sequence | Task ID | Subject | Severity | Findings Covered | Gate / Phase Dependency |
|---|---|---|---|---|---|
| **01** | `TASK-REM-001` | Mask API VA Exposing & Fix VA Search Oracle | Critical | #1, #6, #22 (part) | Immediate (Security) |
| **02** | `TASK-REM-002` | Enforce Deny-by-Default Authorization on Fund Lot / ABT Routes | Critical | #2 | Immediate (Security) |
| **03** | `TASK-REM-003` | Wire Payment Posting Lifecycle & Decommission DEC-008 Banner | Critical | #3, #21 | Phase A Core Flow |
| **04** | `TASK-REM-004` | Enforce DB Check Constraints, Guard Negative Components, & Fix Audit Test | Critical | #4, #5, #24, #25 | Financial Integrity |
| **05** | `TASK-REM-005` | Align RBAC Roles, Remove Stale Approval Permission, & Add Selesai Status | High | #19, #20 | Governance & Workflow |
| **06** | `TASK-REM-006` | Implement Schedule Generator & Dynamic Collectibility Derivation | High | #17, #18 | Calculation Engine |
| **07** | `TASK-REM-007` | Implement ABT Allocation, Partner-Level Debt Excess, & FundTransfer UI | High | #14 | Fund Model (DEC-006) |
| **08** | `TASK-REM-008` | Secure Agreement Administration, Document Upload, & Restructuring Flow | High | #12, #13, #27 | Contract Administration |
| **09** | `TASK-REM-009` | Implement Financial Dashboard, Metric Definitions Registry, & Drill-down | High | #15, #23 | Reporting & Analytics |
| **10** | `TASK-REM-010` | Stage P1 Scope: Workbook Ingestion, Reconciliation Workbench, & Parallel Run | High / Med | #7, #8, #9, #10, #11, #26 | Gated on Phase B Approval |

---

## 2. Detailed Task Specifications

### TASK-REM-001: Fix API VA Leaks and Masking Search Oracles
- **ID:** `TASK-REM-001`
- **Priority:** Critical (P0)
- **Feature Domain:** H (Access & Security) + E (ABT) + A (Partners)
- **Findings Addressed:** #1 (Crit), #6 (Crit), #22 (Med)
- **PRD & Normative References:** PRD §3, PRD §8, DEC-004 (Sensitive field masking), DEC-009.
- **Problem Statement:**
  1. `app/Http/Resources/PaymentResource.php:48` exposes `'payer_va_raw' => $this->payer_va` unconditionally to JSON responses. Client-side masking in `MaskingService.tsx` is bypassed via direct API inspection.
  2. `FundLotController::index` lines 50–54 allows substring `LIKE %search%` querying against raw `payer_va` in `BankTransaction`. Users without `va.reveal` permission can brute-force/enumerate masked VA numbers through search hit presence.
  3. `PartnerSearchService::applyNameFilter` performs unescaped `LIKE %query%` without sanitizing `%` and `_` metacharacters. Furthermore, VA lookup is not gated behind permission checks before executing.
- **Required Implementation:**
  1. Modify `PaymentResource.php`: Remove `payer_va_raw` field entirely. Ensure `payer_va` outputs `MaskingService::maskVa($this->payer_va)` unless `$request->user()?->can(Permission::VaReveal->value)` is explicitly satisfied.
  2. Update TypeScript definition `resources/js/types/payment.ts`: Remove `payer_va_raw` from `PaymentData`.
  3. Update `FundLotController::index`: Remove `payer_va` search from unauthorized users. If search query is numeric/VA-like, require `Permission::VaReveal` or restrict search to exact match on normalized VA where the user possesses authorized scope.
  4. Update `PartnerSearchService.php`:
     - Escape wildcard characters (`%`, `_`, `\`) in search terms before interpolating into SQL `LIKE` clauses.
     - Enforce `Permission::VaReveal` or partner viewing authorization within VA filter matching.
- **Database / TiDB Considerations:**
  - Verify exact match collation behavior on TiDB for `va_number_normalized`.
- **Test Coverage (Pest):**
  - Feature test: Non-authorized user (Viewer/Auditor) receives masked `payer_va` in JSON endpoint `payments.show` and cannot see `payer_va_raw`.
  - Feature test: Authorized role (Kasir TJSL / Operator) with `Permission::VaReveal` receives unmasked VA.
  - Feature test: Search in `FundLotController` with partial VA characters yields zero oracle hits for unprivileged users.
  - Unit test: `PartnerSearchService` handles input containing `%` and `_` as literal strings, not wildcards.
- **Acceptance Criteria:**
  - [x] No raw unmasked VA in any JSON payload without `Permission::VaReveal`.
  - [x] TypeScript types updated to remove `payer_va_raw`.
  - [x] VA search oracle in `FundLotController` eliminated.
  - [x] SQL `LIKE` wildcard injection in `PartnerSearchService` escaped.

---

### TASK-REM-002: Enforce Deny-by-Default Authorization on Fund Lot & ABT Routes
- **ID:** `TASK-REM-002`
- **Priority:** Critical (P0)
- **Feature Domain:** E (ABT & Reconciliation) + H (Access & RBAC)
- **Findings Addressed:** #2 (Crit)
- **PRD & Normative References:** PRD §3, PRD §8, DEC-006, DEC-009, FIMPL-008.
- **Problem Statement:**
  - In `routes/web.php:32–36`, routes `/abt`, `/fund-lots`, `/fund-lots/{fundLot}`, and `/fund-lots/{fundLot}/identify` are protected only by the base `auth` middleware.
  - `IdentifyAbtLotRequest::authorize()` returns `$this->user() !== null` without checking permissions.
  - Any authenticated user, including read-only `Viewer` or `Auditor`, can execute state transitions via `POST /fund-lots/{fundLot}/identify` and mutate fund lots.
- **Required Implementation:**
  1. Create `FundLotPolicy.php`:
     - `viewAny`: requires `Permission::PartnerView` or `Permission::PaymentStage`.
     - `view`: requires `Permission::PartnerView` or `Permission::PaymentStage`.
     - `createAbt`: requires role `Operator` (`Kasir TJSL`) or `Permission::PaymentStage`.
     - `identify`: requires role `Operator` (`Kasir TJSL`) or `Permission::PaymentStage`.
  2. Update `routes/web.php` or `FundLotController.php`: Add `Gate::authorize(...)` calls to `index`, `show`, `storeAbt`, and `identify`.
  3. Update `IdentifyAbtLotRequest::authorize()`: Check `$this->user()?->can('identify', $this->route('fundLot'))`.
- **Test Coverage (Pest):**
  - Feature test: `Viewer` / `Auditor` requesting `POST /fund-lots/{fundLot}/identify` receives HTTP 403 Forbidden.
  - Feature test: `Operator` (`Kasir TJSL`) successfully identifies ABT lot.
  - Feature test: Unauthenticated user receives HTTP 401 / redirect to login.
- **Acceptance Criteria:**
  - [x] All fund lot and ABT endpoints enforced with granular policy gates.
  - [x] `IdentifyAbtLotRequest::authorize()` rejects non-operators with 403.
  - [x] Integration tests verify RBAC matrix on `/abt` and `/fund-lots/*`.

---

### TASK-REM-003: Wire Payment Posting Lifecycle & Decommission DEC-008 Banner
- **ID:** `TASK-REM-003`
- **Priority:** Critical (P0)
- **Feature Domain:** D (Payments)
- **Findings Addressed:** #3 (Crit), #21 (Med)
- **PRD & Normative References:** PRD FR-03, Flow 2, DEC-005, DEC-008 §6, FIMPL-007.
- **Problem Statement:**
  - `PaymentStagingService::post()` and `PaymentStagingService::submit()` exist but have no corresponding controller actions or routes.
  - Web payments remain in `draft` state indefinitely; receivable balances never update upon payment creation.
  - `resources/js/pages/Payments/Show.tsx:100–108` displays a static banner "Pembukuan Saldo Ditangguhkan (DEC-008)", indicating balance posting is blocked despite FIMPL-007 having implemented the allocation engine.
  - `StorePaymentRequest` requires `idempotency_key`, `partner_id`, and `agreement_id` in the request body while they are also present in route parameters, introducing payload mismatch risks.
- **Required Implementation:**
  1. Add Controller Action & Route:
     - Add `PaymentController::post(Request $request, Partner $partner, Agreement $agreement, BankTransaction $payment, PaymentAllocation $allocation)`.
     - Route: `POST partners/{partner}/agreements/{agreement}/payments/{payment}/allocations/{allocation}/post` named `payments.post`.
     - Authorize using `Gate::authorize('post', $allocation)`.
     - Invoke `PaymentStagingService::post($allocation, $request->user())`.
  2. UI Updates (`resources/js/pages/Payments/Show.tsx`):
     - Remove the "Pembukuan Saldo Ditangguhkan (DEC-008)" warning banner for posted transactions.
     - Add an actionable "Posting Pembukuan" button for draft/submitted allocations visible to authorized cashiers.
     - Display balance evaluation timestamp (`as-of timestamp`) and post-allocation status.
  3. Clean Request Contract (`StorePaymentRequest.php`):
     - Make `partner_id` and `agreement_id` optional in body when resolved from route parameters, or validate consistency against route bindings.
     - Ensure server recomputes totals and does not trust client amounts.
- **Database / TiDB Considerations:**
  - Ensure `post()` runs inside a DB transaction with row-level locks on `agreements` and `installment_schedules` (`SELECT ... FOR UPDATE`).
- **Test Coverage (Pest):**
  - Feature test: Cashier can stage a payment and immediately post it via `POST .../post`.
  - Feature test: Posting reduces schedule outstanding balances and updates agreement balance.
  - Feature test: Idempotent re-posting returns error or existing posted record without double-crediting.
  - Inertia test: `Show.tsx` renders updated balances and removes the provisional posting banner.
- **Acceptance Criteria:**
  - [x] Route `payments.post` implemented and protected by `Permission::PaymentPost`.
  - [x] Posting atomically executes `AllocationService::allocate()` and transitions state to `posted`.
  - [x] "Pembukuan Saldo Ditangguhkan" banner removed.
  - [x] Request contract cleaned of redundant/conflicting route parameters.

---

### TASK-REM-004: Enforce DB Check Constraints, Guard Negative Components, & Fix Audit Fixtures
- **ID:** `TASK-REM-004`
- **Priority:** Critical (P0)
- **Feature Domain:** D (Payments & Ledger Integrity) + H (Audit)
- **Findings Addressed:** #4 (Crit), #5 (Crit), #24 (Med), #25 (Med)
- **PRD & Normative References:** PRD §4 (Invariants 1–7), PRD FR-03 (Pasal 19), DEC-008.
- **Problem Statement:**
  - Invariants 1–2 (non-negative financial amounts, positive payment amount, `allocated + unapplied <= amount`) are enforced in application PHP code only. Database migrations lack `CHECK` constraints or triggers.
  - In `database/seeders/AgreementSeeder.php:108–112`, installment 1 has overpaid interest (150k paid vs 100k due) and admin (50k paid vs 8.3k due). When calculating outstanding amounts, `interest_due - interest_paid` goes negative. While `AllocationService` clamps `outMap` with `max(0, ...)`, historical overpayments cause the next payment to bypass admin/interest and allocate 100% to principal without audit explanation.
  - `tests/Feature/AuditEventTest.php:397–402` fails because it expects `NotApprovedException` when posting, but `PaymentStagingService::post()` now executes real allocations.
  - Payment reversal flow (`payments.reverse`) needs verification that reversed funds reopen for reallocation and reason/evidence are strictly captured.
- **Required Implementation:**
  1. Add Database Migration for Check Constraints:
     - Add `CHECK` constraints on `bank_transactions` (`amount > 0`).
     - Add `CHECK` constraints on `payment_allocations` (`total_amount > 0`, `principal_amount >= 0`, `interest_amount >= 0`, `admin_charge_amount >= 0`, `other_charge_amount >= 0`).
     - Add `CHECK` constraints on `installment_schedules` (`principal_due >= 0`, `interest_due >= 0`, `admin_charge_due >= 0`, `principal_paid >= 0`, `interest_paid >= 0`, `admin_charge_paid >= 0`).
     - Test compatibility with TiDB (TiDB 5.3+ parses and enforces CHECK constraints).
  2. Fix Seeder & Guard Component Balances:
     - Correct `AgreementSeeder.php`: Set realistic synthetic paid amounts not exceeding component dues, or move excess component payments into an explicit `FundLot` / `Overpayment` record.
     - In `AllocationService` and `BalanceService`, explicitly detect and report corrupted or overpaid schedule rows rather than silently swallowing negative numbers.
  3. Fix `AuditEventTest.php`:
     - Update lines 376–412: Test unauthorized posting rejection via `AuthorizationException` (403) when user lacks `Permission::PaymentPost`, asserting the `unauthorized_posting_attempt` audit event.
  4. Verify Reversal Lifecycle:
     - Ensure `PaymentReversalService` creates compensating allocation with inverse amounts, reopens schedule balances, logs actor and reason, and prevents double-reversals.
- **Test Coverage (Pest):**
  - Database test: Direct raw SQL insert with negative amounts or over-allocation violates DB constraint.
  - Unit test: Overpaid component scenario in schedule triggers alert or excess lot allocation.
  - Feature test: `tests/Feature/AuditEventTest.php` passes with zero failures.
  - Feature test: Reversal workflow marks allocation `reversed`, spawns compensating record, and resets schedule paid balances.
- **Acceptance Criteria:**
  - [x] Migration adds MySQL/TiDB `CHECK` constraints for all positive/non-negative invariant columns.
  - [x] `AgreementSeeder` corrected to valid financial state.
  - [x] `AuditEventTest` passes cleanly.
  - [x] Reversal contract verified with audit reason and evidence tracking.

---

### TASK-REM-005: Align RBAC Roles, Remove Stale Approval Permission, & Add Selesai Status
- **ID:** `TASK-REM-005`
- **Priority:** High (P1)
- **Feature Domain:** H (Access / Roles) + B (Agreements)
- **Findings Addressed:** #19 (High), #20 (High)
- **PRD & Normative References:** PRD §3, PRD §6, DEC-002 (Lifecycle labels), DEC-005, DEC-009 (RBAC matrix).
- **Problem Statement:**
  - `app/Enums/Role.php` maps `Auditor` to business label `'Viewer'`, but `Role::Auditor` still exists as internal enum name. A distinct read-only `Viewer` role should be formalized.
  - `app/Enums/Permission.php` still contains `case AllocationApprove = 'allocation.approve'`. Under DEC-005 and FIMPL-001, mandatory second review was removed; cashiers post directly. Leaving `AllocationApprove` creates confusion and implies an active approval hierarchy.
  - System Admin role must be verified to have no bypass on financial ledger integrity (cannot directly alter balances or skip posting validation).
  - `app/Enums/AgreementLifecycleStatus.php` contains 6 states (`Draft`, `Active`, `PaidOff`, `ClosedByRescheduling`, `Cancelled`, `Unknown`), but lacks the explicit confirmed business state `Selesai`. In DEC-002, `Selesai` is a distinct contract lifecycle conclusion and does not necessarily equal financial `Lunas` (`PaidOff`).
- **Required Implementation:**
  1. Refactor `Role` Enum:
     - Clarify internal role keys: `Kasir` (`operator`), `KepalaSubDivisi` (`reconciliation_reviewer`), `Sekper` (`process_owner`), `Viewer` (`viewer` / `auditor`), `SystemAdmin` (`system_admin`).
     - Remove `Permission::AllocationApprove` from `Permission.php`, database seeders, policies, and test fixtures.
  2. Update `AgreementLifecycleStatus.php`:
     - Add `case Completed = 'completed'` with label `'Selesai'`.
     - Explicitly distinguish `Completed` (`Selesai` - contractual expiry/completion) from `PaidOff` (`Lunas` - zero remaining balance with verified data per DEC-008).
     - Update migration enum definitions if lifecycle status is column-constrained.
  3. System Admin Safeguards:
     - Verify in `AgreementPolicy`, `PaymentPolicy`, and `FundLotPolicy` that `SystemAdmin` is forbidden from posting payments or overriding financial allocations without proper cashier role assignments.
- **Test Coverage (Pest):**
  - Unit test: `AgreementLifecycleStatus` exposes all 7 confirmed business labels.
  - Unit test: `Role` enum and permission registry contains no `AllocationApprove`.
  - Feature test: `SystemAdmin` cannot post financial transactions without cashier permissions.
- **Acceptance Criteria:**
  - [x] `AllocationApprove` excised from entire codebase.
  - [x] `Selesai` implemented as discrete lifecycle status separate from `Lunas`.
  - [x] RBAC policies verified for technical admin non-bypass.

---

### TASK-REM-006: Implement Installment Schedule Generator & Dynamic Collectibility Derivation
- **ID:** `TASK-REM-006`
- **Priority:** High (P1)
- **Feature Domain:** C (Schedules & Collectibility)
- **Findings Addressed:** #17 (High), #18 (High)
- **PRD & Normative References:** PRD §6, `docs/formula-specification.md` §3 (Schedule generation), §9 (Collectibility), DEC-007.
- **Problem Statement:**
  - `InstallmentSchedule` rows are only generated statically via seeders (`addMonths()`). Agreements lack structured attributes for `tenor_months`, `loan_start_date`, and `first_due_date`.
  - Rounding error: Seeder divides 20,000,000 / 12 as `1,666,667` across all 12 installments, totaling `20,000,004` (Rp4 discrepancy). Remainder is not adjusted on the final installment.
  - Collectibility status is currently a stored column in `Agreement`, not dynamically derived as-of evaluation date from schedule delinquency and verified balance.
- **Required Implementation:**
  1. Agreement Tenor & Schedule Fields:
     - Add migration: `tenor_months`, `loan_start_date`, `first_due_date`, `interest_rate_percent` to `agreements` table.
  2. Create `ScheduleGeneratorService.php`:
     - Implement schedule generation using EDATE calendar math (preserving day of month per formula specification §3).
     - Implement integer rounding with remainder placed on final installment:
       `principal_per_month = floor(principal / tenor)`.
       `final_month_principal = principal - (principal_per_month * (tenor - 1))`.
       Total matches original principal down to Rp0 difference.
  3. Create `CollectibilityCalculationService.php`:
     - Calculate late months dynamically based on oldest unpaid installment due date relative to evaluation date `as_of`.
     - Map late months to DEC-007 bands:
       - 0–1 month: `Lancar`
       - 2–6 months: `Kurang Lancar`
       - 7–9 months: `Diragukan`
       - >9 months: `Bermasalah`
     - Balance condition: If `remaining_balance == 0` and `data_verified == true`, status is `Lunas`. If `data_verified == false`, status is `Unknown`.
- **Test Coverage (Pest):**
  - Unit test: Tenor 12 on Rp20,000,000 creates 11 installments of Rp1,666,666 and 1 installment of Rp1,666,674, summing exactly to Rp20,000,000.
  - Unit test: EDATE logic handles end-of-month dates (Jan 31 -> Feb 28/29 -> Mar 31).
  - Feature test: As-of date evaluation derives correct collectibility band over timeline progression.
- **Acceptance Criteria:**
  - [x] Schedule generator generates exact integer schedules with remainder adjustment on final installment.
  - [x] Agreement model carries tenor, start date, and first due date.
  - [x] Collectibility dynamically computed as-of date per DEC-007 bands.

---

### TASK-REM-007: ABT Allocation to Agreement, Partner-Level Debt Excess, & FundTransfer UI
- **ID:** `TASK-REM-007`
- **Priority:** High (P1)
- **Feature Domain:** E (ABT & Funds)
- **Findings Addressed:** #14 (High)
- **PRD & Normative References:** PRD FR-04, FR-13, DEC-006, `docs/formula-specification.md` §8 (Four-concept fund model).
- **Problem Statement:**
  - `FundTransfer` model and migration exist, but there is no controller, route, or UI to allocate an identified ABT lot (`identified_unallocated`) to an agreement.
  - Excess calculation currently assesses individual agreement balance instead of checking the partner's total debt across all agreements (violating DP-7 / DEC-006).
  - Cashiers have no interface to redirect excess funds or apply parked ABT funds to open agreements.
- **Required Implementation:**
  1. Service Logic (`FundTransferService.php`):
     - Implement `allocateAbtToAgreement(FundLot $lot, Agreement $agreement, int $amount, User $actor)`:
       - Validates lot is `IdentifiedUnallocated`.
       - Atomically executes `PaymentStagingService::post()` using the ABT lot as financial source.
       - Records a `FundTransfer` entry linking the `FundLot` and resulting `PaymentAllocation`.
       - Deducts allocated amount from lot; if balance is zero, marks lot `Allocated`.
     - Implement partner-level debt excess check: Check sum of balances across all active agreements of the partner before declaring remaining amount as true excess.
  2. Controller & Routes:
     - `POST /fund-lots/{fundLot}/allocate`: maps to `FundLotController::allocateToAgreement`.
     - Request validation: `partner_id` must match lot's identified partner; `agreement_id` must belong to partner; `amount <= fund_lot.amount`.
  3. Frontend UI (`resources/js/pages/FundLots/Show.tsx`):
     - Provide an allocation dialog allowing the user to select an active agreement belonging to the identified partner.
     - Show partner total remaining debt across agreements.
- **Test Coverage (Pest):**
  - Feature test: Allocate Rp1,000,000 from an identified ABT lot to Agreement 1; asserts schedule balance decremented and lot balance decremented.
  - Unit test: Excess amount created only when payment exceeds partner's aggregate debt across all agreements.
  - Feature test: Unauthorized user cannot allocate ABT funds.
- **Acceptance Criteria:**
  - [x] Identified ABT lots can be allocated to specific agreements via UI and API.
  - [x] `FundTransfer` records auditable money movement.
  - [x] Excess computed per partner total debt, not single agreement.

---

### TASK-REM-008: Secure Agreement Administration, Document Upload, & Restructuring Flow
- **ID:** `TASK-REM-008`
- **Priority:** High (P1)
- **Feature Domain:** B (Agreements) + H (Security & Audit)
- **Findings Addressed:** #12 (High), #13 (High), #27 (Med)
- **PRD & Normative References:** PRD FR-02, FR-12, Flow 3, DEC-002, DEC-003, PRD §8.
- **Problem Statement:**
  - `AgreementDocumentService` exists (private disk, sha256 checksums), but has no controller, routes, or upload UI.
  - Upload lacks MIME validation, maximum size enforcement, and virus/malware inspection hooks.
  - Document downloads in `DocumentService` return raw contents without time-expiring temporary URLs or access audit logging.
  - Agreement restructuring (Flow 3, addendum/successor links) has backend cycle rejection in `AgreementTransitionService`, but no UI for initiating restructures, reviewing old-vs-new balance comparisons, or obtaining required manager approvals.
- **Required Implementation:**
  1. Agreement Document Routes & Validation:
     - Add routes for document upload and secure download under `agreements/{agreement}/documents`.
     - Request validation: `file` must be `application/pdf`, max size `10MB`.
     - Implement expiring signed URLs or audited streaming response in controller (`AuditService::logDocumentAccess`).
  2. New Agreement & Restructure UI:
     - Add `agreements/create` page for drafting new agreements.
     - Add `agreements/{agreement}/restructure` flow:
       - Displays existing balance and terms side-by-side with proposed successor agreement.
       - Validates absence of cyclical transitions via `AgreementTransitionService::validateNoCycle`.
       - Requires explicit approval notes and authorization check.
- **Test Coverage (Pest):**
  - Feature test: Non-PDF document upload rejected with HTTP 422.
  - Feature test: Document download emits `document_downloaded` audit event.
  - Feature test: Restructuring agreement creates successor and closes predecessor with `closed_by_rescheduling` status without mutating previous balance records.
- **Acceptance Criteria:**
  - [x] PDF contract upload functional with strict MIME and size validation.
  - [x] Document access/download logged in audit trail with temporary expiring URLs.
  - [x] Restructure workflow operational with cycle prevention and side-by-side comparison.

---

### TASK-REM-009: Financial Dashboard, Metric Definitions Registry, & Receivable Drill-down
- **ID:** `TASK-REM-009`
- **Priority:** High (P1)
- **Feature Domain:** G (Dashboard & Analytics) + A (Partners)
- **Findings Addressed:** #15 (High), #23 (Med)
- **PRD & Normative References:** PRD FR-05, FR-11, FR-14, `/docs/metric-definitions.md`.
- **Problem Statement:**
  - `routes/web.php:15–17` routes `/dashboard` to an empty static Inertia page.
  - No `MetricDefinition` database model, migration, or admin configuration exists.
  - Dashboard lacks critical financial filters: evaluation as-of date, cohort, region/branch, and collectibility bands.
  - Partner receivable detail view (`FR-05`) lacks drill-down to source transaction coordinates, adjustment notes, and agreement rollups.
- **Required Implementation:**
  1. Metric Registry Migration & Model:
     - Create `metric_definitions` table: `id`, `code`, `name`, `version`, `formula_expression`, `is_active`, `approved_at`, `approved_by`.
     - Seed confirmed metrics: `Remaining Principal v1`, `Allocation Order v1`, `Collectibility Label v1`.
  2. Dashboard Backend & UI (`resources/js/pages/Dashboard.tsx`):
     - Create `DashboardController`: aggregates active agreements, total principal outstanding, total payments collected this period, unallocated ABT total, and count of partners by collectibility band.
     - Provide date selector for `as_of` evaluation.
     - Distinguish partner count from agreement count.
  3. Receivable Detail Drill-Down (`resources/js/pages/Partners/Show.tsx`):
     - Display itemized agreement receivable breakdown with source payment references, adjustments, and verified status flags.
- **Test Coverage (Pest):**
  - Feature test: Dashboard loads and computes aggregate metrics accurately against synthetic dataset.
  - Feature test: Filtering dashboard by `as_of` date evaluates historical schedule states accurately.
- **Acceptance Criteria:**
  - [x] `MetricDefinition` model and table seeded with versioned metrics.
  - [x] Dashboard displays live aggregated portfolio metrics with as-of date filtering.
  - [x] Partner receivable detail displays clear breakdown across all agreements.

---

### TASK-REM-010: P1 Scope Staging — Reconciliation Workbench, Ingestion, Mapping, & Parallel Run
- **ID:** `TASK-REM-010`
- **Priority:** Medium (P1 — Staged)
- **Feature Domain:** F (Workbook Ingestion & Reconciliation) + G (Monitoring & Export) + E (Bank Reconciliation)
- **Findings Addressed:** #7 (High), #8 (High), #9 (High), #10 (High), #11 (High), #16 (High), #26 (Med)
- **PRD & Normative References:** PRD §8, PRD §9 (Phase B Gates), FR-04, FR-07, FR-08, FR-09, FR-10, FR-14.
- **Gate Status:** **GATED ON PHASE B APPROVAL**. Synthetic-data prototypes must not ingest real customer production workbooks until Phase B criteria are signed off.
- **Scope Outline:**
  1. `ReconciliationCase` & Bank Record Ingestion (`FR-04`, `FR-07`):
     - Models: `SourceSnapshot`, `SourceRow`, `ReconciliationCase`.
     - Background queues for processing large bank mutasi files with SHA-256 idempotency.
     - Candidate matching algorithm (VA exact match, name fuzzy match, amount match) presenting candidate scores with rationale.
  2. Workbook Exception Workbench (`FR-08`, `FR-09`):
     - Detect discrepancies between legacy Excel workbook formulas and ledger computed totals (e.g. LUNAS marking with non-zero balance).
     - Exception package generation with audit review trail.
  3. Parallel Run Verification Engine (`FR-10`):
     - Daily dual-run comparison logging variances between legacy spreadsheet calculations and new application ledger.
  4. Monitoring & Export Service (`FR-14`):
     - Queued export jobs producing password-protected or signed CSV/XLSX extracts.
     - Explicit check for `Permission::SensitiveExport`.
- **Implementation Constraint:**
  - Keep architectural interfaces and migration schemas ready in stubs; do not connect live banking feeds or real customer PII until explicit sign-off per PRD §9.
- **Acceptance Criteria:**
  - [x] Architecture design and database schemas drafted for reconciliation cases, source rows, parallel run discrepancies, and export jobs.
  - [x] Candidate matching algorithm implemented with confidence scoring and rationale (exact VA, partner ID, amount/date proximity, fuzzy name).
  - [x] Workbook exception workbench implemented detecting formula errors, LUNAS with positive balance anomalies, and missing partner IDs.
  - [x] Parallel run discrepancy engine operational for daily dual-run variance comparison between legacy sheets and ledger.
  - [x] Monitoring export service and queued export jobs gated on `Permission::SensitiveExport` with audit event logging.
  - [x] Phase B gate prerequisites verified and guarded via `PhaseBGateService` and `NotApprovedException::forPhaseBGate()` before activating ingestion pipelines.
  - [x] Bank mutasi and workbook ingestion background jobs implemented with SHA-256 idempotency.

---

## 3. Implementation Verification & Traceability Matrix

| Finding # | Category | Assigned Task | Target Output / Deliverable | Status |
|---|---|---|---|---|
| #1 | Security / API | `TASK-REM-001` | `PaymentResource.php`, TypeScript types | Resolved (commit `ffbda1e`) |
| #2 | Security / RBAC | `TASK-REM-002` | `FundLotPolicy.php`, `IdentifyAbtLotRequest.php` | Resolved |
| #3 | Core Flow / Payments | `TASK-REM-003` | `PaymentController::post`, UI Posting Action | Resolved (commit `95ed962`) |
| #4 | Ledger Integrity | `TASK-REM-004` | MySQL/TiDB CHECK constraint migration | Resolved (commit `89ef535`) |
| #5 | Ledger Integrity | `TASK-REM-004` | `AgreementSeeder.php`, `AllocationService.php` | Resolved (commit `89ef535`) |
| #6 | Security / Search | `TASK-REM-001` | `FundLotController::index` query scope | Resolved (commit `ffbda1e`) |
| #7 | Reconciliation | `TASK-REM-010` | `ReconciliationCase` schema (Phase B) | Resolved (commit `aa01eaf`) |
| #8 | Ingestion | `TASK-REM-010` | `SourceSnapshot` / `SourceRow` (Phase B) | Resolved (commit `aa01eaf`) |
| #9 | Ingestion | `TASK-REM-010` | Mapping & Exception engine (Phase B) | Resolved (commit `aa01eaf`) |
| #10 | Reconciliation | `TASK-REM-010` | Reconciliation workbench (Phase B) | Resolved (commit `aa01eaf`) |
| #11 | Parallel Run | `TASK-REM-010` | Parallel run discrepancy logger (Phase B) | Resolved (commit `aa01eaf`) |
| #12 | Agreement Admin | `TASK-REM-008` | Document upload controller & validation | Resolved (commit `2b41310`) |
| #13 | Restructure | `TASK-REM-008` | Restructure UI & approval flow | Resolved (commit `2b41310`) |
| #14 | ABT & Funds | `TASK-REM-007` | `FundTransferService`, ABT allocation modal | Resolved (commit `df8a044`) |
| #15 | Dashboard | `TASK-REM-009` | `DashboardController`, Metric registry | Resolved (commit `898513c`) |
| #16 | Monitoring & Export | `TASK-REM-010` | Export jobs & SensitiveExport gate | Resolved (commit `aa01eaf`) |
| #17 | Schedules | `TASK-REM-006` | `ScheduleGeneratorService` (EDATE + rounding) | Resolved (commit `757ce07`) |
| #18 | Collectibility | `TASK-REM-006` | `CollectibilityCalculationService` (DEC-007) | Resolved (commit `757ce07`) |
| #19 | RBAC | `TASK-REM-005` | Drop `AllocationApprove`, align Viewer role | Resolved (commit `d2d0c52`) |
| #20 | Workflow | `TASK-REM-005` | `AgreementLifecycleStatus::Completed` (Selesai)| Resolved (commit `d2d0c52`) |
| #21 | Payments | `TASK-REM-003` | Clean `StorePaymentRequest` contract | Resolved (commit `95ed962`) |
| #22 | Partner Search | `TASK-REM-001` | Escape LIKE wildcards in `PartnerSearchService`| Resolved (commit `ffbda1e`) |
| #23 | Receivable Detail | `TASK-REM-009` | Agreement rollups & ledger source coordinates | Resolved (commit `898513c`) |
| #24 | Audit | `TASK-REM-004` | Fix `tests/Feature/AuditEventTest.php` | Resolved (commit `89ef535`) |
| #25 | Payments | `TASK-REM-004` | Validate `PaymentReversalService` test suite | Resolved (commit `89ef535`) |
| #26 | Ingestion Queues | `TASK-REM-010` | Queue worker & idempotent jobs (Phase B) | Resolved (commit `aa01eaf`) |
| #27 | Security | `TASK-REM-008` | Expiring signed URLs for document access | Resolved (commit `2b41310`) |
