# Phase 0 Research: Admin Dashboard Analytics

## Decision 1: Verify the actual runtime before implementation

- Decision: Treat runtime compatibility as a blocking implementation gate.
  Before coding, run `php -v` and `composer check-platform-reqs`, then record
  the verified PHP runtime and installed dependency compatibility.
- Rationale: `composer.json`, `composer.lock`, the host runtime, and the local
  runtime may diverge. Planning must not silently choose one source while the
  executable environment contradicts it.
- Alternatives considered:
  - Trust `composer.json` only. Rejected because installed packages may require
    a different runtime.
  - Trust `composer.lock` only. Rejected because the deployment runtime can
    still fail platform requirements.
  - Continue without verification. Rejected because this creates avoidable
    implementation and deployment risk.


## Decision 2: Keep Dashboard analytics as one read-only Admin endpoint with a dedicated query class

- Decision: Implement `GET /api/v1/admin/dashboard` with a thin controller,
  dedicated `ShowDashboardRequest`, `DashboardAnalyticsQuery`, and
  `DashboardResource`.
- Rationale: The response combines multiple aggregates, two independent date
  periods, status-filtered count logic, and a six-point chart. That is too much
  query logic for a controller, but it does not justify a broader service or
  workflow action because the endpoint is read-only and bounded.
- Alternatives considered:
  - Put aggregate queries directly in the controller. Rejected because it would
    violate the thin-controller rule and make future verification harder.
  - Use an Action. Rejected because there is no multi-step mutation workflow.

## Decision 3: Resolve time windows in one focused UTC-only service

- Decision: Create a focused `DashboardDateRangeResolver` service that produces
  the effective order-count period, effective sales period, current UTC day,
  current UTC month, and the six calendar-month chart window. Public response
  dates remain inclusive, while database predicates use half-open UTC ranges:
  `column >= startAt` and `column < exclusiveEndAt`.
- Rationale: Feature 007 has several overlapping but distinct range rules:
  today, Saturday-to-Friday week, current month, custom 366-day range, and a
  six-month calendar window. Centralizing this avoids scattered date math and
  keeps tests deterministic.
- Alternatives considered:
  - Inline Carbon calculations in the Form Request and query class. Rejected
    because the same rules would be duplicated.
  - Use SQL-only date resolution. Rejected because the feature needs exact
    response range metadata and fixed month labels in addition to filtering.

## Decision 4: Add nullable backend-controlled `orders.completed_at` and preserve it after cancellation

- Decision: Add `orders.completed_at` as a nullable UTC timestamp with the
  same precision as the existing `orders.created_at` and `orders.updated_at`
  columns, assign it on the first transition to completed inside the existing
  locked status-change workflow, and preserve it for the approved
  `completed -> cancelled` transition.
- Rationale: `collectedSales.today` and `collectedSales.period` must reflect
  when collection became final under the completed status, not merely order
  creation time or any later update time. Preserving the timestamp after
  cancellation keeps historical completion knowledge without letting cancelled
  orders contribute to current collected totals.
- Alternatives considered:
  - Reuse `created_at`. Rejected because collection can happen later.
  - Reuse `updated_at`. Rejected because any update would corrupt collected time
    meaning for active data.
  - Add a separate status-history table. Rejected by feature scope and project
    rules.

## Decision 5: Backfill existing completed orders once using their current `updated_at`

- Decision: Backfill only rows whose current status is completed and whose
  `completed_at` is null, setting `completed_at = updated_at` without changing
  `updated_at`.
- Rationale: This is the approved fallback in the spec and is the least
  invasive approximation available without introducing historical status
  records. The operation is idempotent because it only touches null
  `completed_at` rows.
- Alternatives considered:
  - Leave legacy completed rows null. Rejected because collected-period
    analytics would become inconsistent after deployment.
  - Use `created_at`. Rejected because it is a poorer approximation than the
    last known status-changing timestamp.
  - Run a long external command-based backfill. Rejected because the feature can
    be satisfied through a focused schema/data migration strategy.

## Decision 6: Use a fixed budget of at most five Order-data reads

- Decision: The dashboard analytics layer may execute at most five SELECT
  statements against Order data per request. Shared Sanctum authentication,
  administrator-boundary, and permission queries are measured separately.
  The implementation target is three Order-data reads:
  1. one financial query using conditional aggregates for sales,
     collected-sales, and uncollected-sales across total/today/period windows
  2. one order-count query for the effective count range and optional status
  3. one grouped six-month performance query
- Rationale: The budget is fixed, testable, independent of order volume, and
  avoids vague optional lookups. Conditional SQL aggregates prevent separate
  reads for each financial section.
- Alternatives considered:
  - Six reads with an optional supporting lookup. Rejected because the optional
    read was not tied to an approved requirement.
  - One giant raw SQL statement. Rejected because it unnecessarily reduces
    readability and testability.
  - Load orders into PHP collections. Rejected by bounded-read and memory
    requirements.


## Decision 7: Verify indexes via `EXPLAIN` before adding any new ones

- Decision: Inspect current Order indexes and representative MySQL `EXPLAIN`
  plans before approving any new index; record each relevant query, existing
  candidate index, observed plan, and final add/no-add decision. Only add an
  index that measurably supports the new access pattern and is not equivalent
  to a Feature 005 index.
- Rationale: The spec forbids duplicating equivalent indexes and requires
  evidence-based index decisions. Dashboard reads mainly depend on current
  status plus `created_at`/`completed_at`, so index additions should be small
  and justified.
- Alternatives considered:
  - Preemptively add `status, created_at` and `status, completed_at` indexes.
    Rejected until actual plans confirm a need.
  - Add no index review step. Rejected by the feature requirements.

## Decision 8: Expose `completedAt` only through approved Admin order resources

- Decision: Add `completedAt` to Admin order detail/index projections and keep
  it absent from all mutation contracts.
- Rationale: The dashboard feature needs the field to become part of the
  authoritative Admin read model, but the spec explicitly forbids clients from
  sending it. Existing Admin resources are the narrowest approved output surface
  for this amendment.
- Alternatives considered:
  - Hide `completedAt` from all APIs. Rejected because Feature 007 requires
    synchronization of approved order contracts.
  - Add it to public order creation output. Rejected because it is not part of
    the public guest contract and may be null or operationally sensitive.


## Decision 9: Synchronize the complete Feature 005 read and mutation contract

- Decision: Add `completedAt` to the existing Feature 005 Admin Order index and
  show schemas/resources, reject it across every create/update/status/payment/
  item mutation contract, and update Feature 005 specification, plan, tasks,
  OpenAPI, Postman examples, and regression tests.
- Rationale: A partial standalone Order schema inside Feature 007 would conflict
  with the authoritative Feature 005 contract.
- Alternatives considered:
  - Document a partial Order endpoint in Feature 007 OpenAPI. Rejected because
    it would appear to replace the complete Feature 005 resource.
  - Update only the Laravel Resource. Rejected because the API contract and
    exact request tests would drift.


## Decision 10: Use one deep-object query filter contract

- Decision: Accept dashboard input only through:
  - `filter[ordersPeriod]`
  - `filter[dateFrom]`
  - `filter[dateTo]`
  - `filter[status]`
- Rationale: This matches the project's established query-filter convention,
  keeps future filter extension bounded, and avoids mixing filter input with
  unrelated top-level query parameters.
- Alternatives considered:
  - Keep four top-level query parameters. Rejected because it diverges from the
    existing filtering convention.
  - Accept both syntaxes. Rejected because it creates two contracts for one
    endpoint.

## Decision 11: Apply filters through composable query-filter classes

- Decision: Normalize the validated request into `DashboardFilterData`, then
  compose focused query filters on each dedicated aggregate builder:
  `CreatedAtRangeQueryFilter`, `CompletedAtRangeQueryFilter`,
  `CurrentStatusQueryFilter`, and `NonCancelledQueryFilter`.
- Rationale: Financial, count, and performance queries intentionally receive
  different filters. Composable filter classes make those differences explicit
  and testable while keeping the controller and Resource free of query logic.
- Alternatives considered:
  - Apply all request filters globally through one builder. Rejected because
    status must affect count only and date filters must not affect performance.
  - Write conditional `where` clauses inline in the controller/query method.
    Rejected because filtering rules would become scattered and harder to
    verify.

## Decision 12: Guard the raw query shape before PHP normalization

- Decision: Inspect the original query string before Form Request validation to
  reject repeated approved members such as
  `filter[status]=1&filter[status]=2`. After the shape guard passes, validate the
  normalized `filter` object and build `DashboardFilterData`.
- Rationale: PHP/Symfony may collapse repeated scalar keys to the last value,
  making duplicates invisible through the normalized query bag.
- Alternatives considered:
  - Ignore repeated scalar keys. Rejected by the exact query contract.
  - Require array syntax for multiple values. Rejected because every dashboard
    filter is intentionally single-valued.
