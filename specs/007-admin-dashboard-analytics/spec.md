# Feature Specification: Admin Dashboard Analytics

**Feature Branch**: `[007-admin-dashboard-analytics]`

**Created**: 2026-08-02

**Status**: Ready for Planning

**Input**: Build the Admin Dashboard Analytics feature from
`docs/features/007-admin-dashboard-analytics.md` and preserve every approved
business rule in that reference.

## Scope and Governing Context *(mandatory)*

**Approved source**: `docs/features/007-admin-dashboard-analytics.md`

**In scope**:

- Exactly one new read-only Admin dashboard endpoint.
- The exact sales, collected-sales, uncollected-sales, order-count, and
  six-month performance projections approved by the feature reference.
- Independent UTC sales-period and order-count-period resolution.
- Optional exact current-status filtering for order count only.
- One `dashboard.view` permission enforced through the existing Admin access
  boundary.
- A backend-controlled order completion timestamp required for accurate
  collected-sales period calculations.
- Synchronization of Feature 005 documentation, order contracts, model
  behavior, transition behavior, migration/backfill behavior, and tests with
  the completion-timestamp amendment.
- Arabic and English response-message, validation-message, and performance
  label localization.
- MySQL-backed aggregate, date-boundary, authorization, contract, query-plan,
  and regression verification.

**Out of scope**:

- Dashboard write operations or a Public dashboard endpoint.
- Dashboard-specific tables or persisted dashboard snapshots.
- Customer statistics, service popularity statistics, payment transaction
  history, profit, tax, forecasting, or additional charts.
- Saved reports, PDF/Excel export, scheduled reports, emailed reports, queues,
  workers, cache, or Redis.
- Backend conversion to Cairo, browser, administrator, or user-specific
  timezones.
- Status filtering of sales, collected sales, uncollected sales, or the
  performance chart.
- Frontend dashboard code.

**Governing documents reviewed**:

- `.specify/memory/constitution.md`
- `AGENTS.md`
- `docs/00-project-overview/project-overview.md`
- `docs/01-architecture/backend-architecture.md`
- `docs/02-standards/api-standards.md`
- `docs/02-standards/authorization-standards.md`
- `docs/02-standards/database-standards.md`
- `docs/02-standards/localization-standards.md`
- `docs/02-standards/security-standards.md`
- `docs/02-standards/testing-standards.md`
- `docs/features/005-orders-management.md`
- `docs/features/007-admin-dashboard-analytics.md`
- `specs/005-orders-management/spec.md`

**Known conflicts**:

- No blocking product conflict exists. Where the approved reference says
  `StatusCode::*`, this specification uses the repository-authoritative
  `App\Enums\HttpStatusCode` convention without changing HTTP behavior.
- Feature 005 does not yet define `orders.completed_at`; Feature 007 explicitly
  authorizes and requires that synchronized amendment before implementation is
  complete.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Review Accurate Financial Summary (Priority: P1)

An authenticated active administrator with `dashboard.view` reviews total,
today, and selected-period sales, collected sales, and outstanding amounts in
one compact response.

**Why this priority**: These figures provide the primary operational and
financial overview required by the Admin Dashboard.

**Independent Test**: Create orders across all approved statuses, payment
amounts, creation dates, and completion dates; request the dashboard and verify
each money section against the approved independent calculation.

**Acceptance Scenarios**:

1. **Given** non-cancelled and cancelled orders, **When** the dashboard is
   requested, **Then** `sales` includes only currently non-cancelled order
   totals.
2. **Given** completed and non-completed paid orders, **When** the dashboard is
   requested, **Then** `collectedSales` includes the full paid amount of only
   currently completed orders.
3. **Given** unpaid, partially paid, fully paid, and overpaid non-cancelled
   orders, **When** the dashboard is requested, **Then** `uncollectedSales`
   sums each non-negative remaining amount and never becomes negative.
4. **Given** no matching orders, **When** the dashboard is requested, **Then**
   every money field returns the decimal string `"0.00"`.

---

### User Story 2 - Select Sales and Order Periods (Priority: P1)

An authorized administrator selects today, the current Saturday-to-Friday
week, the current month, or an inclusive custom UTC range for order count and
may independently supply a custom range for period sales.

**Why this priority**: Operational order volume and financial period totals
answer different questions and must remain independently controllable.

**Independent Test**: Freeze the current UTC time, place orders on range
boundaries, submit each approved `filter[...]` query combination, and verify the effective
ranges and results.

**Acceptance Scenarios**:

1. **Given** no query parameters, **When** the endpoint is requested, **Then**
   order count uses the current UTC day and sales period uses the current UTC
   month.
2. **Given** `filter[ordersPeriod]=this_week`, **When** the endpoint is requested,
   **Then** count uses the current UTC Saturday through Friday inclusive.
3. **Given** `filter[ordersPeriod]=custom` and a valid `filter[dateFrom]` / `filter[dateTo]` pair, **When** the endpoint
   is requested, **Then** the same inclusive UTC pair controls order count,
   `sales.period`, and `collectedSales.period`.
4. **Given** a non-custom order period plus a valid date pair, **When** the
   endpoint is requested, **Then** the order period controls count while the
   date pair independently controls sales-period metrics.
5. **Given** one date, reversed dates, or a range longer than 366 inclusive
   days, **When** the endpoint is requested, **Then** the shared localized
   `422 VALIDATION_ERROR` response is returned.

---

### User Story 3 - Filter Order Count by Status (Priority: P1)

An authorized administrator optionally filters only the selected-period order
count by one exact current order status.

**Why this priority**: Status-specific workload counts are useful while the
financial and trend sections must remain stable and comparable.

**Independent Test**: Request identical datasets with each `filter[status]` value and
without the filter, verifying only `orders.status` and `orders.count` change.

**Acceptance Scenarios**:

1. **Given** no status, **When** the dashboard is requested, **Then** the order
   count includes every current status, including cancelled.
2. **Given** one approved integer status, **When** the dashboard is requested,
   **Then** only orders with that exact current status are counted.
3. **Given** any status filter, **When** responses are compared, **Then** sales,
   collected sales, uncollected sales, and performance remain unaffected.
4. **Given** an unsupported status value, **When** the endpoint is requested,
   **Then** the shared localized `422 VALIDATION_ERROR` response is returned.

---

### User Story 4 - Review Six-Month Performance (Priority: P2)

An authorized administrator reviews exactly six chronologically ascending UTC
calendar months containing non-cancelled monthly sales and order counts.

**Why this priority**: The chart provides trend context after the primary
current summary and period metrics are available.

**Independent Test**: Freeze time, distribute orders across and outside the six
months, leave at least one month empty, and verify the exact ordered localized
projection.

**Acceptance Scenarios**:

1. **Given** the current UTC month, **When** the dashboard is requested,
   **Then** performance contains that month plus the previous five calendar
   months, not a rolling 180-day window.
2. **Given** a month without qualifying orders, **When** the chart is returned,
   **Then** its sales is `"0.00"` and order count is `0`.
3. **Given** cancelled orders, **When** the chart is returned, **Then** they
   contribute neither sales nor count.
4. **Given** Arabic or English locale resolution, **When** the chart is
   returned, **Then** only each display label and user-facing messages are
   localized while month keys and numeric values remain stable.

---

### User Story 5 - Preserve Accurate Completion History (Priority: P1)

When an order first transitions to completed, the backend records the UTC
completion time used by collected-sales metrics, and a later approved
completed-to-cancelled transition preserves that historical timestamp.

**Why this priority**: Collected today and period totals cannot be accurate
from creation or general update timestamps.

**Independent Test**: Transition an order to completed at a frozen UTC instant,
cancel it later, and verify the timestamp is backend-controlled, preserved,
excluded from current collected totals after cancellation, and visible only in
approved order output.

**Acceptance Scenarios**:

1. **Given** an eligible order, **When** it transitions to completed, **Then**
   its first completion timestamp is set to the current UTC instant in the same
   atomic status change.
2. **Given** a completed order, **When** it transitions to cancelled, **Then**
   the completion timestamp is preserved but the order is excluded from
   current collected-sales calculations.
3. **Given** any client order mutation request, **When** `completedAt` is
   submitted, **Then** it is rejected as an undocumented/backend-controlled
   field.
4. **Given** an existing completed order without a completion timestamp,
   **When** the approved backfill runs, **Then** its existing `updatedAt` value
   is used once as the completion-time approximation.

### Edge Cases

- The current UTC instant lies exactly at day, month, year, leap-day, Saturday,
  or Friday boundaries.
- A custom range contains exactly 366 inclusive calendar days or exceeds it by
  one day.
- A date pair is supplied with a non-custom order period.
- `filter[ordersPeriod]=custom` is supplied without both date filters.
- An order lies exactly at the inclusive start or end microsecond.
- An order has a `null` total because pricing is incomplete.
- An order has a `null` paid amount.
- An order has `total = null` and a positive paid amount.
- An order has a final total while `paid_amount` is null.
- An order is overpaid, so `paid_amount` exceeds `total`.
- A paid non-completed order must not be collected sales.
- A completed order is later cancelled and keeps `completed_at` while becoming
  excluded from sales, collected sales, uncollected sales, and performance.
- A month has no qualifying orders, or all its orders are cancelled.
- The six-month window crosses a calendar year.
- The requested status is cancelled; it may be counted even though cancelled
  orders remain excluded from financial and performance metrics.
- The database contains no orders.

## Requirements *(mandatory)*

### Functional Requirements

#### Endpoint and permissions

- **FR-001**: Feature 007 MUST introduce exactly one new endpoint:
  `GET /api/v1/admin/dashboard`.
- **FR-002**: Feature 007 MUST introduce no Public endpoint and no dashboard
  mutation endpoint.
- **FR-003**: The endpoint MUST require an authenticated active administrator
  with `dashboard.view`.
- **FR-004**: The permission Seeder MUST add exactly `dashboard.view`
  idempotently and the normal super-admin synchronization MUST grant it.
- **FR-005**: The response MUST use the shared API success/error envelopes and
  application-controlled HTTP statuses MUST use `HttpStatusCode`.
- **FR-006**: The feature MUST read current authoritative Order data and MUST
  not persist dashboard snapshots or create dashboard tables.

#### UTC ranges and query semantics

- **FR-007**: Every backend dashboard date calculation MUST use UTC without
  applying Cairo, browser, administrator, frontend, or user-specific timezone
  conversion.
- **FR-008**: A UTC day MUST cover `00:00:00.000000` through
  `23:59:59.999999` inclusively.
- **FR-009**: The only accepted top-level query key MUST be `filter`.
  `filter` MUST be a query-filter object containing only `ordersPeriod`,
  `dateFrom`, `dateTo`, and `status`. Unknown top-level keys and unknown nested
  filter keys MUST be rejected. Every supplied nested filter value MUST be one
  non-empty scalar value; repeated, array-shaped, empty, and whitespace-only
  values MUST be rejected.
- **FR-010**: `filter[ordersPeriod]` MUST accept only `today`,
  `this_week`, `this_month`, or `custom`, and MUST default to `today`.
- **FR-011**: `today` MUST resolve to the current UTC calendar day.
- **FR-012**: `this_week` MUST resolve to the current UTC Saturday at day start
  through Friday at day end.
- **FR-013**: `this_month` MUST resolve to the current UTC calendar month.
- **FR-014**: `filter[dateFrom]` and `filter[dateTo]` MUST use exact
  `YYYY-MM-DD` UTC calendar dates and MUST be submitted together.
- **FR-015**: `filter[dateFrom]` MUST be earlier than or equal to
  `filter[dateTo]`.
- **FR-016**: A custom date pair MUST span no more than 366 inclusive calendar
  days; exactly 366 days MUST be accepted.
- **FR-017**: `filter[ordersPeriod]=custom` MUST require the date pair and
  MUST reuse it
  for order count and the effective sales period.
- **FR-018**: When the order period is not custom, an optional valid date pair
  MUST control only `sales.period` and `collectedSales.period`.
- **FR-019**: When no date pair is supplied, the effective sales period MUST be
  the current UTC calendar month regardless of the non-custom order period.
- **FR-020**: All reported range dates MUST be inclusive and returned as stable
  `YYYY-MM-DD` values.
- **FR-020A**: Validated filter input MUST be normalized into one typed
  dashboard filter object. SQL filtering MUST be applied through focused,
  reusable query-filter classes composed by the analytics query layer; filter
  conditions MUST NOT be scattered through the controller or Resource.

#### Sales

- **FR-021**: `sales` MUST equal the decimal sum of `orders.total` for orders
  whose current status is pending, confirmed, in progress, or completed.
- **FR-022**: Currently cancelled orders MUST be excluded from every sales
  value.
- **FR-023**: `sales.total` MUST cover all qualifying orders since the system
  began.
- **FR-024**: `sales.today` MUST cover qualifying orders whose `createdAt` is
  inside the current UTC day.
- **FR-025**: `sales.period` MUST cover qualifying orders whose `createdAt` is
  inside the effective sales period.
- **FR-026**: A null order total MUST not corrupt aggregates and MUST contribute
  zero until a final total exists; sales aggregation MUST use null-safe
  fixed-precision semantics equivalent to `COALESCE(total, 0)`.

#### Collected sales

- **FR-027**: `collectedSales` MUST equal the decimal sum of the full stored
  `paid_amount` for orders whose current status is completed only. A null
  `paid_amount` MUST contribute zero through null-safe fixed-precision
  semantics equivalent to `COALESCE(paid_amount, 0)`.
- **FR-028**: Paid pending, confirmed, in-progress, and cancelled orders MUST
  not contribute to collected sales.
- **FR-029**: `collectedSales.total` MUST cover all currently completed orders.
- **FR-030**: `collectedSales.today` MUST use `completedAt` inside the current
  UTC day.
- **FR-031**: `collectedSales.period` MUST use `completedAt` inside the
  effective sales period.
- **FR-032**: Overpayment MUST contribute the entire stored paid amount to
  collected sales while the order remains completed.

#### Uncollected sales

- **FR-033**: Each qualifying order's uncollected amount MUST use null-safe
  fixed-precision semantics equivalent to
  `max(COALESCE(total, 0) - COALESCE(paidAmount, 0), 0)`.
- **FR-034**: Uncollected sales MUST include all currently non-cancelled order
  statuses and MUST exclude currently cancelled orders.
- **FR-035**: `uncollectedSales.total` MUST cover all qualifying orders.
- **FR-036**: `uncollectedSales.today` MUST cover qualifying orders created in
  the current UTC day.
- **FR-037**: Overpayment MUST never produce a negative uncollected amount.
- **FR-038**: The response MUST never contain `uncollectedSales.period`.

#### Order count and status filter

- **FR-039**: `orders.count` MUST use `orders.createdAt` inside the effective
  order-count period.
- **FR-040**: Without a status filter, order count MUST include every current
  status, including cancelled.
- **FR-041**: `filter[status]` MUST be optional and, when present, MUST
  accept only the
  scalar query representations `"0"`, `"1"`, `"2"`, `"3"`, and `"4"`,
  normalized to integer-backed `OrderStatus` values `0` through `4`. The value
  `"0"` MUST be treated as present and valid, never as empty or false.
- **FR-042**: A supplied status MUST filter order count by exact current status.
- **FR-043**: The status filter MUST NOT affect sales, collected sales,
  uncollected sales, the effective sales period, or performance.
- **FR-043A**: Empty, whitespace-only, signed, decimal, zero-padded,
  boolean, named, array-shaped, or repeated status values such as
  `filter[status]=`, `filter[status]=03`, `filter[status]=3.0`,
  `filter[status]=+3`, `filter[status]=true`,
  `filter[status]=cancelled`, `filter[status][]=3`, or repeated
  `filter[status]=1&filter[status]=2` MUST be rejected with the shared
  localized validation outcome.

#### Six-month performance

- **FR-044**: `performance` MUST always contain exactly six chronologically
  ascending UTC calendar-month points: the current month and previous five.
- **FR-045**: The performance range MUST be calendar months and MUST not be a
  rolling 180-day window.
- **FR-046**: Each point MUST contain exactly `month`, `label`, `sales`, and
  `ordersCount`. The label MUST use the full localized month name followed by
  the four-digit year, for example `August 2026` and `أغسطس 2026`.
- **FR-047**: `month` MUST be a stable `YYYY-MM` key.
- **FR-048**: Point sales MUST sum qualifying currently non-cancelled order
  totals by `createdAt` within that UTC month.
- **FR-049**: Point order count MUST count currently non-cancelled orders by
  `createdAt` within that UTC month.
- **FR-050**: Every missing month MUST be zero-filled as `sales: "0.00"` and
  `ordersCount: 0`.
- **FR-051**: The request status filter and sales-period date pair MUST not
  alter the six-month performance result.

#### Completion timestamp amendment

- **FR-052**: Orders MUST gain one nullable, backend-controlled completion
  timestamp exposed in approved API output as `completedAt`.
- **FR-053**: The first transition to completed MUST set `completedAt` to the
  current UTC instant inside the same atomic status transition.
- **FR-054**: The approved completed-to-cancelled transition MUST preserve the
  existing completion timestamp.
- **FR-055**: Current cancelled status MUST exclude an order from collected
  sales even when its preserved completion timestamp is non-null.
- **FR-056**: No create, update, status-change, payment, item, or other client
  mutation contract may accept `completedAt`.
- **FR-057**: Existing rows whose current status is completed and whose
  completion timestamp is null MUST be backfilled once using their existing
  `updatedAt` timestamp. The backfill MUST preserve that source value and MUST
  NOT modify the row's existing `updated_at` timestamp.
- **FR-058**: Feature 005 documentation, migration plan, order model/resource
  contracts, transition tests, and request exactness tests MUST be synchronized
  with `completedAt`. Feature 007 MUST NOT be considered deployment-complete
  until the schema change and approved idempotent backfill have succeeded.

#### Money, query, and infrastructure boundaries

- **FR-059**: Every money value MUST be returned as a two-decimal string and
  never as a floating-point JSON number.
- **FR-060**: Aggregation MUST preserve fixed-precision decimal meaning.
- **FR-061**: Dashboard calculations MUST remain bounded and MUST not load all
  matching Order records to calculate sums or counts.
- **FR-062**: The number of data reads required for one response MUST remain
  bounded regardless of the total number of orders, with no per-order reads.
  Planning MUST document one fixed maximum query budget and verification MUST
  enforce that approved budget.
- **FR-063**: Missing chart months MAY be filled only through bounded logic
  over the fixed six-point output.
- **FR-064**: The implementation MUST verify relevant MySQL query plans against
  actual existing order indexes before adding any new index and MUST not
  duplicate an equivalent Feature 005 index.
- **FR-065**: The feature MUST not add cache, Redis, queue work, workers,
  schedules, exports, saved reports, dashboard tables, or write operations.

### Actors and Authorization *(mandatory for protected behaviour)*

- **AR-001**: Only an authenticated active administrator with
  `dashboard.view` may retrieve dashboard analytics.
- **AR-002**: An unauthenticated request MUST return the shared `401
  UNAUTHENTICATED` outcome.
- **AR-003**: An authenticated inactive administrator or non-administrator MUST
  be rejected by the existing Admin boundary before permission evaluation.
- **AR-004**: An active administrator without `dashboard.view` MUST receive the
  shared `403 FORBIDDEN` outcome.
- **AR-005**: Super-admin permission assignment MUST use the normal idempotent
  Seeder flow and MUST not rely on a hidden bypass.
- **AR-006**: Middleware order MUST remain authentication, administrator type,
  active state, then `dashboard.view` permission before the endpoint.

### Trust, Security, and Content Boundaries *(mandatory)*

- **TR-001**: All four query keys MUST be treated as untrusted and validated
  against their exact approved scalar shape, lexical form, values, pairing,
  ordering, and range.
- **TR-002**: The backend clock, UTC boundaries, order statuses, totals,
  payments, timestamps, counts, and aggregates MUST remain authoritative.
- **TR-003**: Client-supplied totals, calculated metrics, timezone values,
  completion timestamps, labels, SQL fragments, column names, sort names, or
  grouping instructions MUST not be accepted.
- **TR-004**: Query values MUST never become raw column, SQL expression, or
  arbitrary ordering input.
- **TR-004A**: Because PHP request normalization may collapse repeated nested
  keys, the original query string MUST be inspected before normal filter
  validation to reject repeated approved filter members deterministically.
- **TR-005**: Responses and errors MUST not expose SQL, schema, query plans,
  stack traces, internal configuration, or other implementation details.
- **TR-006**: The endpoint MUST return only the exact approved analytics keys
  and shared envelope fields.
- **TR-007**: Feature 007 performs no upload, outbound network request, email,
  or other external side effect.

### Data Integrity and Concurrency *(mandatory when data changes)*

- **DI-001**: `completedAt` MUST remain nullable until an order first reaches
  completed status; clients cannot assign it.
- **DI-002**: Status and first completion timestamp MUST change atomically
  under the existing locked order transition workflow.
- **DI-003**: A later completed-to-cancelled transition MUST not erase or
  replace the first completion timestamp.
- **DI-004**: Backfill MUST affect only currently completed orders with a null
  completion timestamp, MUST be idempotent, and MUST not advance or rewrite
  `updated_at`.
- **DI-005**: Existing historical order snapshots, totals, paid amounts,
  creation timestamps, cancellation timestamps, and other Feature 005 fields
  MUST remain unchanged by the backfill.
- **DI-006**: Dashboard reads MUST not mutate orders or depend on cache or
  process-local state.
- **DI-007**: Concurrent dashboard reads and order transitions MUST produce
  only committed database states; no response may combine a partially written
  status and completion timestamp.

### API Contract and Localization *(mandatory for API behaviour)*

- **API-001**: The endpoint MUST be `GET /api/v1/admin/dashboard` with no body.
- **API-002**: The accepted query contract MUST be one optional deep-object
  parameter named `filter` with exactly these optional scalar members:
  `filter[ordersPeriod]?: today|this_week|this_month|custom`,
  `filter[dateFrom]?: YYYY-MM-DD`, `filter[dateTo]?: YYYY-MM-DD`, and
  `filter[status]?: "0"|"1"|"2"|"3"|"4"`. Unknown members and supplied
  array-shaped, repeated, empty, or whitespace-only values are not part of the
  contract.
- **API-003**: A successful response MUST return the shared success envelope
  with `data` containing exactly `salesPeriod`, `sales`, `collectedSales`,
  `uncollectedSales`, `orders`, and `performance`.
- **API-004**: `salesPeriod` MUST contain exactly `dateFrom` and `dateTo` for
  the effective sales range.
- **API-005**: `sales` MUST contain exactly `total`, `today`, and `period`.
- **API-006**: `collectedSales` MUST contain exactly `total`, `today`, and
  `period`.
- **API-007**: `uncollectedSales` MUST contain exactly `total` and `today`.
- **API-008**: `orders` MUST contain exactly `period`, `dateFrom`, `dateTo`,
  `status`, and `count`; `status` is the requested integer or `null`.
- **API-009**: Each `performance` point MUST contain exactly `month`, `label`,
  `sales`, and `ordersCount`.
- **API-010**: Every currently approved invalid period or query combination
  MUST return the shared localized `422 VALIDATION_ERROR`. Feature 007 does
  not introduce a dashboard-specific period error code.
- **API-011**: Application-controlled statuses MUST use the shared
  `HttpStatusCode` enum, including `HttpStatusCode::OK`,
  `HttpStatusCode::UNAUTHORIZED`, `HttpStatusCode::FORBIDDEN`, and
  `HttpStatusCode::UNPROCESSABLE_ENTITY` where controlled by Feature 007.
- **API-012**: Approved Admin order detail and index projections MUST expose
  backend-controlled `completedAt` as an ISO-8601 UTC timestamp or `null` and
  MUST not add it to any client mutation input.

- **LOC-001**: `Accept-Language` MUST resolve to Arabic or English through the
  existing API locale rules.
- **LOC-002**: Response messages, validation errors, and
  `performance[].label` MUST be localized to the resolved locale.
- **LOC-003**: `Content-Language: ar|en` and `Vary: Accept-Language` MUST be
  returned.
- **LOC-004**: JSON keys, `month`, decimal money strings, numeric counts,
  integer status values, query values, and date values MUST remain stable and
  must not be translated.
- **LOC-005**: Arabic and English month labels MUST describe the same UTC month
  represented by the stable `YYYY-MM` key and MUST follow the approved full
  month-name plus four-digit-year format.

### Verification Requirements *(mandatory)*

- **VR-001**: API tests MUST cover success, unauthenticated, inactive,
  non-administrator, forbidden, exact permission, middleware order, shared
  envelopes, and `HttpStatusCode` usage.
- **VR-002**: Validation tests MUST cover unknown query keys, scalar-shape
  enforcement, repeated and array-shaped parameters, empty and whitespace-only
  values, each approved order period, every valid status including `filter[status]=0`,
  invalid lexical status forms, paired dates, required custom dates, exact date
  format, reversed dates, leap year, exactly 366 inclusive days, and more than
  366 days.
- **VR-003**: Sales tests MUST cover all statuses, cancelled exclusion, null
  totals, total, today, period, default current-month sales period, and
  inclusive UTC boundaries.
- **VR-004**: Collected-sales tests MUST cover current completed status,
  non-completed payment exclusion, cancellation exclusion, null paid amount,
  total, today, period, completion-time boundaries, and overpayment.
- **VR-005**: Uncollected-sales tests MUST cover all non-cancelled statuses,
  cancelled exclusion, null total, null paid amount, null total with positive
  paid amount, total, today, zero floor, overpayment, and the absence of a
  period key.
- **VR-006**: Order-count tests MUST cover every period, every exact status,
  omitted-status inclusion of cancelled orders, and proof that status affects
  only count.
- **VR-007**: Performance tests MUST cover six points, chronological order,
  UTC calendar months, year crossing, cancelled exclusion, zero-filled months,
  status independence, and Arabic/English labels.
- **VR-008**: Completion-timestamp tests MUST cover atomic UTC assignment,
  preservation on cancellation, current-status collected exclusion,
  client-field rejection across every mutation contract, and idempotent
  existing-data backfill.
- **VR-009**: Contract tests MUST prove exact response keys, money strings,
  integer-or-null status, headers, absence of `uncollectedSales.period`, and no
  internal detail leakage.
- **VR-010**: Database verification MUST use the dedicated MySQL test database,
  inspect actual relevant `EXPLAIN` plans, enforce the fixed query budget
  approved in planning, prove no N+1 behavior, compare query count across small
  and large datasets, and confirm no duplicate index is introduced.
- **VR-011**: Full regression, Pint, and configured PHPStan/Larastan gates MUST
  pass before completion.
- **VR-012**: Feature 005 specifications, plan, tasks, contracts, Postman
  examples, and tests affected by `completedAt` MUST be synchronized and
  traceable before Feature 007 is accepted.

### Key Entities *(include if feature involves data)*

- **Order**: Existing authoritative order record containing current status,
  total, paid amount, creation time, and the new nullable first-completion time.
  Cancelled status controls exclusion from financial and performance metrics;
  completed status controls collected-sales inclusion.
- **Dashboard Analytics Projection**: Non-persisted read result containing the
  exact financial summary, effective ranges, filtered order count, and fixed
  six-month performance series.
- **Dashboard Date Range**: Resolved inclusive UTC start/end dates for sales,
  order count, today, current month, current Saturday-to-Friday week, and the
  six calendar months.
- **Performance Point**: One non-persisted UTC calendar-month projection with a
  stable month key, localized label, non-cancelled sales, and non-cancelled
  order count.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An authorized administrator can retrieve every approved dashboard
  metric in one read operation, while unauthorized actors receive the approved
  access outcome 100% of the time.
- **SC-002**: Across representative datasets, every returned financial value
  exactly matches its approved definition and is always represented with two
  decimal places.
- **SC-003**: Cancelled orders contribute zero to sales, collected sales,
  uncollected sales, and performance in 100% of verification cases, while they
  remain countable when order count is unfiltered or filtered to cancelled.
- **SC-004**: Today, current-month, Saturday-to-Friday week, leap-year, and
  custom ranges include all records on both approved UTC boundaries and no
  records outside them.
- **SC-005**: Valid ranges of up to 366 inclusive days succeed, and every
  incomplete, reversed, malformed, or longer range is rejected consistently.
- **SC-006**: Status filtering changes only order count; all financial and
  performance values remain identical for the same committed dataset.
- **SC-007**: Every successful response returns exactly six chronologically
  ascending calendar months, including explicit zero values for every empty
  month.
- **SC-008**: Uncollected sales never return a negative value, including for
  fully paid and overpaid orders.
- **SC-009**: Every completed order gains one authoritative UTC completion
  timestamp, and 100% of later approved cancellations preserve it.
- **SC-010**: The analytics response performs a bounded number of data reads
  independent of order volume and performs no per-order reads.
- **SC-011**: No dashboard table, write route, cache dependency, queue, export,
  schedule, or external side effect is introduced.
- **SC-012**: All Feature 007 tests, affected Feature 005 regressions, full
  application tests, formatting, and static analysis pass before release, and
  deployment is incomplete until the `completed_at` schema change and approved
  backfill succeed.

## Assumptions

- The current time used for all calculations is the authoritative backend UTC
  clock.
- Existing order monetary columns retain Feature 005 decimal semantics; a null
  final total contributes zero to dashboard sums until pricing is complete.
- The approved backfill approximation for an existing currently completed
  order without `completedAt` is that row's existing `updatedAt` value.
- The first completion timestamp is authoritative because the approved
  lifecycle never returns a completed order to an active state; the only
  outgoing transition is the Feature 005 exception to cancelled.
- The existing Admin locale middleware supplies Arabic/English locale metadata;
  this feature adds only its own translated messages and month labels.
- Index additions are not assumed. Planning must inspect existing Feature 005
  indexes and actual MySQL `EXPLAIN` results before approving any new index.
