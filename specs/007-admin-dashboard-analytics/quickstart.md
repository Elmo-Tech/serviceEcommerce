# Quickstart: Admin Dashboard Analytics

## Purpose

This guide validates Feature 007 end to end after implementation. It focuses on
the dashboard endpoint, the `completedAt` Order amendment, permissions, and the
required query/index checks.

## Prerequisites

- MySQL testing database is available and isolated from development/production
- environment configuration boots the app successfully
- permission seeders can run cleanly
- the `orders` table includes `completed_at`
- legacy completed orders, if any, have been backfilled according to the
  approved migration strategy

## Setup

First verify the runtime gate:

```bash
php -v
composer check-platform-reqs
```

Record the verified runtime result before implementation or signoff.

Run the normal database reset and seed flow for testing or local verification.

```bash
php artisan migrate:fresh --seed
```

If verifying only tests, ensure the testing environment points to the dedicated
MySQL test database before running the suite.

## Core validation scenarios

### 1. Authorization and envelope

Prove:

- unauthenticated request returns `401 UNAUTHENTICATED`
- active admin without `dashboard.view` returns `403 FORBIDDEN`
- authorized admin receives `200 OK`
- success uses the shared envelope and exact `data` keys

Suggested command:

```bash
php artisan test --filter=DashboardAuthorization
```

### 2. Default periods and UTC boundaries

Prove:

- no query parameters means `orders.period = today`
- no date pair means `salesPeriod` is the current UTC month
- API calendar dates are inclusive while SQL predicates use `>= startAt` and `< exclusiveEndAt`
- Saturday-to-Friday week resolution is correct
- leap-year/custom 366-day logic is correct

Suggested command:

```bash
php artisan test --filter=DashboardDateRange
```

Explicitly verify:

- `filter[ordersPeriod]=custom` without both date filters is invalid
- only `filter[dateFrom]` is invalid
- only `filter[dateTo]` is invalid
- reversed dates are invalid
- exactly 366 inclusive days is valid
- 367 inclusive days is invalid
- a non-custom period plus a valid date pair is valid and applies the pair only
  to sales and collected-sales period metrics

### 3. Financial aggregates

Prove:

- `sales` excludes currently cancelled orders
- `collectedSales` uses only current completed status and `completed_at`
- `uncollectedSales` uses zero-floor semantics and has no `period` key
- null totals and null paid amounts contribute zero safely
- overpayment contributes fully to collected sales and never makes uncollected
  negative

Suggested command:

```bash
php artisan test --filter=DashboardFinancials
```

### 4. Exact query validation and status-filtered order count

Prove:

- omitted status counts all statuses including cancelled
- `filter[status]=0` through `filter[status]=4` are valid
- `filter[status]=0` is never treated as empty or false
- `filter[status]=03`, `filter[status]=3.0`, `filter[status]=+3`,
  `filter[status]=true`, `filter[status]=cancelled`,
  `filter[status][]=3`, and repeated
  `filter[status]=1&filter[status]=2` are invalid
- `filter[ordersPeriod][]=today`, empty values, whitespace-only values,
  unknown nested filter keys, and unknown top-level query keys are invalid
- financial sections and performance are unchanged when only `status` changes

Suggested command:

```bash
php artisan test --filter=DashboardOrderCount
```

### 4A. Query-filter pipeline

Prove:

- valid input is normalized into `DashboardFilterData`
- `CurrentStatusQueryFilter` is applied only to the order-count query
- date-range query filters use half-open UTC boundaries
- financial and performance builders receive `NonCancelledQueryFilter`
- controller and Resource contain no direct filtering clauses
- raw query-shape guarding rejects repeated nested filter members before PHP
  normalization

Suggested command:

```bash
php artisan test --filter=DashboardQueryFilters
```

### 5. Six-month performance chart

Prove:

- exactly six points are returned
- points are chronological and calendar-month based
- missing months are zero-filled
- cancelled orders do not contribute
- `Accept-Language: ar` and `Accept-Language: en` localize labels only

Suggested command:

```bash
php artisan test --filter=DashboardPerformance
```

### 6. Completion timestamp amendment

Prove:

- first transition to completed sets `completed_at`
- later `completed -> cancelled` preserves it
- current cancelled status excludes the order from collected sales
- `completedAt` is exposed in both Admin Order index and Admin Order show
- create, update, status-change, payment, and item mutation requests reject
  `completedAt`
- backfill is idempotent and does not rewrite `updated_at`

Suggested command:

```bash
php artisan test --filter=CompletedAt
```

### 7. Legacy migration and backfill rehearsal

Prove the real migration path rather than relying only on `migrate:fresh`:

1. Start from the pre-Feature-007 Order schema.
2. Insert a completed legacy Order with a known `updated_at`.
3. Run the new migration/backfill.
4. Verify `completed_at` equals the original `updated_at`.
5. Verify `updated_at` remains unchanged.
6. Re-run the idempotent backfill path and verify no row changes.
7. Verify no currently completed legacy Order remains with null
   `completed_at`.

Suggested command:

```bash
php artisan test --filter=CompletedAtMigrationBackfill
```

### 8. Query budget and query plans

Prove:

- the analytics layer uses at most five Order-data SELECT statements; shared authentication and permission queries are measured separately
- query count does not scale with order volume
- `EXPLAIN` plans are recorded for representative sales, collected-sales,
  order-count, and performance queries
- no duplicate equivalent index is introduced over existing Order indexes

Suggested verification:

```bash
php artisan test --filter=DashboardQueryPerformance
```

Then inspect the implementation notes or test fixtures that capture the chosen
`EXPLAIN` plan expectations for the final query design.

### 9. Feature 005 contract synchronization

Prove all affected artifacts agree:

- Feature 005 spec, plan, tasks, and OpenAPI document `completedAt`
- Admin Order index and show responses expose ISO-8601 UTC `completedAt|null`
- every mutation contract rejects the field
- Postman examples are updated
- no new Order route was introduced by Feature 007

## Quality gates

Run the full affected verification set before moving to tasks completion or
implementation signoff.

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Expected outcome

Feature 007 is ready when:

- the dashboard endpoint returns the exact approved contract
- all required dashboard and Order `completedAt` regression tests pass on MySQL
- query-budget and `EXPLAIN` verification pass
- Pint and PHPStan/Larastan pass
- Feature 005 read contracts/tests affected by `completedAt` are synchronized

## Implementation verification record — 2026-08-02

- Runtime gate: passed on the repository-installed PHP and locked Composer dependencies.
- Feature 007 database, request-shape, endpoint, contract, migration, concurrency, query-budget, and query-plan scenarios: passed on the configured MySQL test database.
- Full regression suite: 259 tests passed with 2023 assertions.
- Query budget: three Order-data SELECT statements for the complete dashboard projection, unchanged after increasing row volume.
- Formatting: `vendor/bin/pint --test` passed.
- Static analysis: `vendor/bin/phpstan analyse --no-progress` passed with zero errors.
- Index decision: no new index; see `checklists/index-decision.md`.
- No environment-specific contract exception or weakened verification was required.

## Contract references

- API contract: [contracts/openapi.yaml](./contracts/openapi.yaml)
- Data model: [data-model.md](./data-model.md)
- Research decisions: [research.md](./research.md)
