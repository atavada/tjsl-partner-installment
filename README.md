# Sistem Angsuran & Piutang Program Kemitraan TJSL

Prototype system for managing partner financing agreements, installment schedules, receivables, payment staging, and allocation tracking for the Tanggung Jawab Sosial dan Lingkungan (TJSL) partner program. Replaces legacy spreadsheet workbooks and R/Shiny workflows with a structured, verifiable, auditable web application.

> **CRITICAL PROTOTYPE NOTICE (PRD §0, §9):**  
> This software is a **Phase A prototype using synthetic data only**. It implements the structural domain schema, navigation, access control scaffolding, and payment staging workflows required for Phase A validation.  
> **Zero real workbook rows, real partner identities, or real PII are present.**  
> Real financial rules (interest/admin formulas, accrual basis, penalty rules, allocation ordering) and legacy data migration are **unapproved and blocked** pending formal Phase B sign-off.

---

## Tech Stack

- **Backend:** PHP 8.2+, Laravel 12.x
- **Frontend:** Inertia.js v2, React 19, TypeScript 5.7, Tailwind CSS v4, Vite 6
- **Database:** MySQL 8.x / TiDB compatible (UUID primary keys, string columns for code identifiers, database-neutral types, no DB-level enums)
- **Testing & Quality:** Pest PHP 3.8, PHPStan / Laravel Boost, ESLint, Prettier, Laravel Pint

---

## Financial Integrity & Core Architecture Rules

All system operations enforce strict financial and architectural invariants defined in [docs/PRD.md](docs/PRD.md) (§4):

1. **Server Authoritative:** The backend validates and computes all financial totals, state transitions, and access controls. Frontend components only display and preview data; client-submitted totals are never trusted.
2. **Exact Integer IDR:** All monetary amounts are stored and computed in integer Indonesian Rupiah (minor currency units). Floating-point arithmetic for currency is strictly barred.
3. **Identifier Preservation:** Code identifiers (`partner_no_id`, `nik`, `va_number`, `agreement_number`, `bank_reference`) are stored as strings preserving leading zeros. Raw inputs and normalized lookup values are stored in separate columns.
4. **Three Independent Status Dimensions (PRD §4 Invariant 8):** Every agreement maintains three mutually independent status dimensions:
   - **Contract Lifecycle:** `draft`, `active`, `paid_off`, `closed_by_rescheduling`, `cancelled`, `unknown`
   - **Collectibility Risk Rating:** `lancar`, `kurang_lancar`, `bermasalah`, `unknown`
   - **Document Signing Workflow:** `not_prepared`, `draft`, `awaiting_partner_signature`, `awaiting_company_signature`, `signed`, `unknown`  
   *Transition in one dimension never mutates another dimension or modifies financial ledger balances.*
5. **Acyclic Contract Transitions:** Predecessor/successor links between agreements (rescheduling, addenda) form an acyclic directed graph. Cyclic references are rejected. An agreement marked `closed_by_rescheduling` is distinct from `paid_off`.
6. **Append-Only Auditing:** Financial and state mutations create immutable records in `audit_events` via `CorrelationIdMiddleware`, tracking the actor, IP, timestamp, and masked attribute changes. Physical deletion of financial records is prohibited; corrections require dated compensating reversals.
7. **Synthetic Data Boundary:** All seeded partners, contracts, bank statements, and transactions are generated synthetically. Sensitive fields (NIK, phone number, address, VA) are masked by default across all views and logs.

---

## Assumptions Made During Phase A

During Phase A development, the following foundational assumptions were made based on PRD §4 and documented discussions:
- **Identifier Structure:** Partner ID (`partner_no_id`) and Virtual Account (`va_number`) are distinct, unique identifiers for partners. Agreement numbers are non-unique batch/year groupings ([DEC-001](docs/decisions.md)).
- **Unverified Balances:** Because interest, administration fee, and penalty calculation rules are pending formal business confirmation, outstanding balances cannot be computed or assumed. Any balance inquiry returns an explicit `unverified` status rather than a false or estimated zero.
- **Draft Agreements:** Draft agreements do not establish legally binding debt. They are excluded from active receivables calculations and partner liability summaries until activated.
- **Single Bank Account Context:** Phase A bank transaction capture models incoming statement rows with reference identifiers, timestamps, and amounts, leaving multi-institution clearinghouse rules to Phase B.
- **Cashier Direct Posting Authority (DEC-005):** Process owner confirmed no maker-checker approval system for now (resolved 2026-10-03); TJSL cashier inputs directly with full audit trail and reversal capability.

---

## Implementation Status (Implemented, Stubbed, Blocked)

### 1. Implemented Components
- **Core Domain Schema:**
  - `partners`, `partner_aliases`, `virtual_accounts` (UUID PKs, leading-zero preservation, audit timestamps)
  - `agreements`, `agreement_transitions`, `agreement_documents`, `installment_schedules`
  - `bank_transactions`, `payment_allocations`, `receivable_adjustments`, `overpayments`
  - `audit_events` (immutable audit log with correlation IDs)
- **Authentication & RBAC Scaffolding:**
  - Laravel Breeze + Inertia auth flow
  - 5 core domain roles (`Role` enum): `SystemAdmin`, `Operator`, `ReconciliationReviewer`, `ProcessOwner`, `Auditor`
  - Deny-by-default authorization policies (`BasePolicy`, `PartnerPolicy`, `AgreementPolicy`, `PaymentPolicy`)
  - Explicit rule denying System Administrators implicit financial posting authority
- **Partner Registry & Search:**
  - Exact match search by leading-zero `partner_no_id`
  - Search by partner alias / business name, virtual account, or agreement number
  - Multi-candidate disambiguation for identical business names with distinct IDs
  - Server-side pagination, PII masking, and verification status badges
- **Agreement Timeline & Detail Views:**
  - Visual agreement timeline showing contract lifecycle, signing status, and collectibility rating
  - Predecessor and successor agreement navigation with acyclic graph traversal
  - Document metadata tracking with SHA-256 checksums and private storage isolation
- **Payment Capture & Staging Workflow:**
  - Staging interface for incoming bank transactions with idempotency key enforcement
  - Rejection of zero or negative payment amounts
  - Automatic duplicate payment fingerprinting (`bank_reference`, `amount`, `transaction_date`)
  - Allocation proposal engine with over-allocation prevention
  - Unapplied payment / overpayment (ABT) bucket creation
  - Compensating payment reversal workflow with full audit trail
- **Audit Logging System:**
  - `AuditService` writing append-only rows to `audit_events`
  - `CorrelationIdMiddleware` propagating request tracing headers (`X-Correlation-ID`)
  - Automatic masking of sensitive fields (`nik`, `phone`, `address`, `va_number`) in audit payloads
  - Dedicated audit logging for authentication and authorization failures
- **Synthetic Seed Data:**
  - Seeders covering all 14 PRD §9 gate edge case scenarios

### 2. Stubbed Components
- **Fine-Grained Permission Matrix ([DEC-009](docs/decisions.md)):** Core roles exist with confirmed business labels per DEC-009 (resolved 2026-10-03), but granular capability grants are stubbed as empty arrays until staff capability mapping is approved.
- **Ledger Balance Calculation ([DEC-008](docs/decisions.md)):** `BalanceService::getBalance()` returns an explicit `unverified` status and `'unverified'` component strings. It never returns a simulated or zero balance.
- **Installment Schedule Formula Calculation ([DEC-008](docs/decisions.md)):** Installment schedule records exist in schema with `is_calculated = false`. Automated schedule generation is stubbed pending approved interest/admin accrual rules.
- **Sensitive Field Unmasking Grants ([DEC-004](docs/decisions.md)):** Unmasking permissions (`nik.reveal`, `phone.reveal`, `va.reveal`) are stubbed as permanently denied in Phase A.

### 3. Blocked Operations (Throwing `NotApprovedException`)
The following actions intentionally throw `App\Exceptions\NotApprovedException` to prevent unapproved financial or lifecycle state changes:
- **Agreement Lifecycle State Transitions ([DEC-002](docs/decisions.md)):** `AgreementTransitionService::transitionLifecycle()` throws `NotApprovedException::forLifecycleTransition()`.
- **Agreement Signing State Transitions ([DEC-003](docs/decisions.md)):** `AgreementTransitionService::transitionSigning()` throws `NotApprovedException::forSigningTransition()`.
- **Payment & Allocation Ledger Posting ([DEC-008](docs/decisions.md)):** `PaymentStagingService::post()` throws `NotApprovedException::forPaymentPosting()`.
- **Receivable Adjustment Posting ([DEC-008](docs/decisions.md)):** Manual balance adjustment posting throws `NotApprovedException::forReceivableAdjustmentPosting()`.
- **ABT / Overpayment Disposition Execution ([DEC-006](docs/decisions.md)):** `Overpayment::executeDisposition()` throws `NotApprovedException::forOverpaymentDisposition()`.
- **Payment Period Overrides ([DEC-010](docs/decisions.md)):** Overriding the derived transaction accounting period throws `NotApprovedException::forPeriodOverride()`.

---

## Architectural Decisions Log (`docs/decisions.md`)

Detailed documentation of all architectural and domain decisions is maintained in [docs/decisions.md](docs/decisions.md).

### Resolved Decisions
- **`DEC-001` (Agreement-Number Uniqueness Scope):** `RESOLVED` (2026-09-30). Agreement number is a non-unique batch/year grouping key. Unique partner identification relies on `partner_no_id` (`NO ID`) and `va_number` (`NO VA`).
- **`DEC-005` (Second Review of Unmatched Deposits):** `RESOLVED` (2026-10-03). Process owner confirmed no approval system for now. TJSL cashier inputs directly with full audit trail and reversal capability.
- **`DEC-009` (RBAC Roles and Permissions):** `RESOLVED` (2026-10-03). Process owner confirmed five business-facing role names (Viewer, Kasir TJSL, Kepala Sub Divisi, Sekper / Kepala Divisi, System Admin). Current release scope activates Viewer, Kasir TJSL, and System Admin.

### Open Decisions (Pending Process Owner Approval)
The following 8 decisions remain `PROPOSED` (OPEN) and block Phase B execution until approved by their designated owners:

| Decision ID | Title | Summary / Proposed Default | Owner |
|---|---|---|---|
| **[DEC-002](docs/decisions.md#dec-002-agreement-lifecycle-states-and-transition-guard-rules)** | Agreement Lifecycle States & Transitions | Formal definition of contract lifecycle states (`draft`, `active`, `paid_off`, `closed_by_rescheduling`, `cancelled`, `unknown`) and operational transition guards. | Process Owner |
| **[DEC-003](docs/decisions.md#dec-003-signing-and-document-workflow-states-signatory-ordering-and-mark-mapping)** | Signing & Document Workflow States | Definition of document states (`not_prepared`, `draft`, `awaiting_partner_signature`, `awaiting_company_signature`, `signed`, `unknown`), signatory sequence, and legacy mark mappings. | Process Owner |
| **[DEC-004](docs/decisions.md#dec-004-sensitive-field-unmasking-policy-role-permissions-and-access-logs)** | Sensitive-Field Unmasking Policy | Role-based policy for revealing masked PII (`nik`, `phone`, `address`, `va_number`), justification logs, and export controls. | Process Owner / System Admin |
| **[DEC-006](docs/decisions.md#dec-006-abt--overpayment-disposition-policy-and-cross-agreement-offset-rules)** | ABT / Overpayment Disposition Policy | Rules governing excess payment handling: cross-agreement offset precedence, partner refund approval criteria, and unidentified fund holding limits. | Process Owner |
| **[DEC-007](docs/decisions.md#dec-007-collectibility-classification-levels-arrears-thresholds-and-legacy-label-mapping)** | Collectibility Classification Levels | Formalization of 4 risk ratings (`lancar`, `kurang_lancar`, `bermasalah`, `unknown`), days-past-due thresholds, and mapping from legacy labels (`Lunas`, `Lancar`, `Kurang Lancar`, `Bermasalah`). | Process Owner |
| **[DEC-008](docs/decisions.md#dec-008-balance-calculation-rules-ledger-identity-formulas-interestadmin-distinctions-and-rounding-policy)** | Balance Calculation Rules & Formulas | Ledger identity formula, interest vs admin fee calculation rules (flat vs effective), rounding rules, and allocation precedence order. | Process Owner |
| **[DEC-010](docs/decisions.md#dec-010-payment-period-derivation-rules-timezone-cutoff-and-accounting-override-criteria)** | Payment Period Derivation Rules | Rule for deriving accounting period from transaction timestamp (`Asia/Jakarta`), end-of-month cutoff rules, and override governance. | Process Owner |
| **[DEC-011](docs/decisions.md#dec-011-bank-reference-uniqueness-scope-provider-namespaces-and-statement-deduplication)** | Bank-Reference Uniqueness Scope | Uniqueness constraints for bank transaction reference numbers across institutions and statement deduplication logic. | Process Owner |

---

## Phase B Gate Requirements (PRD §9)

Before development may begin on Phase B (real calculation logic, reconciliation workflows, legacy data migration), the following prerequisites must be formally signed off per PRD §9:

1. **Signed Decisions:** Formal resolution and sign-off on all open decisions (`DEC-002` through `DEC-011`).
2. **Reconciliation Tolerance Threshold:** Approved monetary variance threshold for ledger-to-bank statement reconciliation.
3. **Legacy Workbook Mapping Workshop:** Completed field mapping session covering legacy spreadsheet column coordinates, sheet hierarchies, and historical anomalies.
4. **Source Hierarchy & Cutoff Date:** Formally established authoritative hierarchy of legacy records and a fixed historical as-of cutoff date.
5. **Staff Role Matrix:** Confirmed staffing assignments mapped to granular RBAC capabilities.
6. **Approved Business Formulas:** Formally approved calculation specifications for principal amortization, interest accrual, administrative charges, penalty interest, and payment waterfall ordering.
7. **Data Handling & Unmasking Authorization:** Written legal and operational authorization for viewing and exporting partner PII.
8. **Anonymized Approved Evidence Fixtures:** Verified test fixtures derived from legacy records that have been completely scrubbed of live production PII.

---

## Local Setup & Installation

### Prerequisites
- **PHP:** 8.2 or higher (with `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- **Composer:** 2.7 or higher
- **Node.js:** 20.x or higher and **npm:** 10.x or higher
- **Database:** MySQL 8.0+ or TiDB cluster

### Step-by-Step Installation

```bash
# 1. Clone repository
git clone <repository-url>
cd partner-program-installment-app

# 2. Install PHP dependencies
composer install

# 3. Install frontend dependencies
npm install

# 4. Configure environment
cp .env.example .env
php artisan key:generate

# 5. Configure database credentials in .env:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=partner_program_installment_app
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# 6. Run database migrations
php artisan migrate

# 7. Seed synthetic data (includes roles, accounts, and 14 PRD §9 edge case fixtures)
php artisan db:seed
```

### Running the Application

```bash
# Option A: Run all services concurrently (Laravel server, queue listener, log tailing, Vite)
composer dev

# Option B: Run separate processes
php artisan serve
npm run dev
```

The application will be available at `http://localhost:8000`.

---

## Default Synthetic Test Accounts

The synthetic seeder generates default accounts representing the five core roles defined in PRD §3. All accounts share the password: `password`.

| Role | Email | Responsibilities & Permissions |
|---|---|---|
| **SystemAdmin** | `test@example.com` | User management and system administration. Bypasses viewing gates; financial posting forbidden. |
| **Operator** | `operator@example.test` | Operational data entry: partner view, agreement view, payment staging, document upload. |
| **ReconciliationReviewer** | `reviewer@example.test` | Independent review: partner view, agreement view, payment verification, allocation approval. |
| **Auditor** | `auditor@example.test` | Read-only oversight: partner view, agreement view, full immutable audit log inspection. |
| **ProcessOwner** | `process_owner@example.test` | Domain authority: agreement view, agreement activation, policy configuration. |

---

## Testing & Quality Verification

### Running the Test Suite
The project utilizes Pest PHP for automated testing, covering unit logic, feature workflows, and all 14 PRD §9 gate requirements:

```bash
# Run the complete test suite
./vendor/bin/pest

# Run specific feature test suites
./vendor/bin/pest tests/Feature/PartnerSearchTest.php
./vendor/bin/pest tests/Feature/AgreementTimelineTest.php
./vendor/bin/pest tests/Feature/PaymentStagingTest.php
./vendor/bin/pest tests/Feature/AuditEventTest.php
./vendor/bin/pest tests/Feature/SyntheticSeederTest.php
```

### Static Analysis, Linting, & Formatting

```bash
# TypeScript type checking
npm run types

# ESLint verification
npm run lint

# Code style inspection (Laravel Pint)
./vendor/bin/pint --test

# Asset build verification
npm run build
```

---

## PRD §9 Gate Edge Cases Verified

The test suite explicitly validates the 14 edge case requirements mandated by PRD §9:

1. **Leading-Zero Partner ID Preservation:** Round-trip persistence and exact lookup for partner IDs with leading zeros (e.g. `00142`) ([tests/Feature/PartnerSearchTest.php](tests/Feature/PartnerSearchTest.php)).
2. **Multi-Candidate Search Disambiguation:** Independent partner records returned when searching identical business names with different IDs ([tests/Feature/PartnerSearchTest.php](tests/Feature/PartnerSearchTest.php)).
3. **Distinct Identifier Storage:** Strict separation of `partner_no_id`, `nik`, `va_number`, and `agreement_number` columns ([tests/Feature/PartnerSearchTest.php](tests/Feature/PartnerSearchTest.php)).
4. **Cyclic Addendum Rejection:** Acyclic graph validation rejecting direct or indirect addendum loops ([tests/Feature/AgreementTimelineTest.php](tests/Feature/AgreementTimelineTest.php)).
5. **Unmatched Name-Only Payments:** Incoming payments with name-only references remain unallocated ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php)).
6. **Rescheduling Distinction:** Invariant that `closed_by_rescheduling` agreements are never treated as `paid_off` ([tests/Feature/AgreementTimelineTest.php](tests/Feature/AgreementTimelineTest.php)).
7. **Non-Positive Payment Rejection:** Zero and negative amounts rejected by database constraints and service validation ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php)).
8. **Duplicate Payment Ingestion Idempotency:** Duplicate bank statement transactions rejected based on fingerprint index ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php)).
9. **Over-Allocation Prevention:** Staging rejects allocation amounts exceeding payment or obligation limits ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php)).
10. **Compensating Reversal Integrity:** Reversals preserve original transaction rows and record full audit traces ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php), [tests/Feature/AuditEventTest.php](tests/Feature/AuditEventTest.php)).
11. **Unauthorized Posting Prevention:** Unprivileged roles and System Administrators denied posting authority ([tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php), [tests/Feature/AuditEventTest.php](tests/Feature/AuditEventTest.php)).
12. **Unverified Balance Integrity:** Incomplete balance calculations return explicit `unverified` status, never zero ([tests/Feature/AgreementTimelineTest.php](tests/Feature/AgreementTimelineTest.php), [tests/Feature/PaymentStagingTest.php](tests/Feature/PaymentStagingTest.php)).
13. **Status Dimension Independence:** Contract and signing status transitions cause zero side-effects on financial totals ([tests/Feature/AgreementTimelineTest.php](tests/Feature/AgreementTimelineTest.php)).
14. **Synthetic Domain Coverage:** Automated verification that synthetic seeders populate all domain models with valid relationships ([tests/Feature/SyntheticSeederTest.php](tests/Feature/SyntheticSeederTest.php)).

---

## Database Compatibility Notice (MySQL vs TiDB)

- **Schema Design (Verified):** The database schema avoids MySQL-specific features, vendor-specific stored procedures, and database-level enum types. All primary keys use RFC 4122 UUID strings, financial amounts use 64-bit integer columns, and constraints rely on standard ANSI foreign keys and unique indexes compatible with both MySQL 8.x and TiDB.
- **Runtime Isolation & Distributed Locking (Unverified):** Automated tests in Phase A execute against local MySQL/SQLite database drivers. TiDB-specific behaviors—including distributed transaction isolation, optimistic versus pessimistic conflict resolution under concurrency, and collation-specific index clustering—**have not yet been verified against a physical TiDB cluster**. Physical cluster verification is a Phase B deliverable.
