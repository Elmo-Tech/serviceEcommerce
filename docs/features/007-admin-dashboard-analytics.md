# Feature 007 — Admin Dashboard Analytics

## 1. Overview

This feature provides a compact read-only analytics endpoint for the Admin Dashboard.

It exposes:

- Total sales.
- Sales created today.
- Sales created within a selected date range.
- Total collected sales.
- Collected sales completed today.
- Collected sales completed within a selected date range.
- Total uncollected sales.
- Uncollected sales for orders created today.
- Order count for today, the current week, the current month, or a custom period.
- Optional order-status filtering for the order count.
- A six-month performance chart containing monthly sales and order counts.

The feature reads from the existing Orders module. It does not introduce dashboard-specific database tables or write endpoints.

---

## 2. Module Name

```text
007-admin-dashboard-analytics
```

Canonical reference:

```text
docs/features/007-admin-dashboard-analytics.md
```

---

## 3. Goals

The feature must:

1. Provide the exact sales metrics required by the Admin Dashboard.
2. Clearly separate sales, collected sales, and uncollected sales.
3. Support UTC-based today, week, month, and custom date calculations.
4. Support an optional order-status filter for the order count only.
5. Return the current UTC month plus the previous five calendar months.
6. Return zero-valued chart points for months without data.
7. Use decimal strings for all money values.
8. Reuse the existing order status and money columns.
9. Add only the minimum order timestamp required for accurate collected-period calculations.
10. Avoid cache, exports, scheduled jobs, and additional infrastructure.

---

## 4. Out of Scope

The following are outside this feature:

- Dashboard write operations.
- Dashboard-specific tables.
- Saved reports.
- PDF or Excel export.
- Scheduled or emailed reports.
- Cache or Redis.
- Queues or workers.
- Revenue forecasting.
- Profit or tax calculations.
- Customer statistics.
- Service popularity statistics.
- Payment transaction history.
- Additional dashboard charts.
- Backend timezone conversion.
- User-specific timezone settings.
- Filtering the performance chart by order status.

---

## 5. Actor

### Administrator

An authenticated active administrator with `dashboard.view` may retrieve the dashboard analytics.

There is no Public endpoint for this feature.

---

## 6. Permission

The feature introduces exactly one permission:

```text
dashboard.view
```

Rules:

- The permission is added through the existing idempotent permission Seeder.
- `super-admin` receives it through the normal permission flow.
- There is no hidden authorization bypass.

Required middleware order:

```text
auth:sanctum
EnsureUserIsAdministrator
EnsureAdminIsActive
permission:dashboard.view
endpoint
```

---

## 7. API Endpoint

```http
GET /api/v1/admin/dashboard
```

The endpoint is read-only and must use:

- The shared `ApiResponse` envelope.
- The existing `StatusCode::*` enum.
- Existing Admin authentication and authorization middleware.
- Existing API localization middleware.

---

## 8. Time Standard

All backend calculations use UTC only.

The backend must not apply:

```text
Africa/Cairo
browser timezone
administrator timezone
frontend timezone
```

The frontend is responsible for any display conversion.

Canonical UTC day boundaries:

```text
00:00:00.000000 UTC
through
23:59:59.999999 UTC
```

All stored timestamps remain UTC.

---

## 9. Order Timestamp Amendment

Accurate collected-period calculations require a completion timestamp.

Add to `orders`:

```text
completed_at nullable
```

Recommended type:

```text
timestamp nullable
```

API/model name:

```text
completedAt
```

Rules:

1. When an order transitions to `COMPLETED`, set `completed_at` to the current UTC timestamp.
2. The timestamp is backend-controlled.
3. The client cannot submit or update it directly.
4. If a completed order is later cancelled, preserve `completed_at`.
5. A cancelled order is excluded from collected-sales queries because its current status is no longer `COMPLETED`.
6. Existing completed orders require a documented backfill strategy.
7. Feature 005 documentation, migration plan, model, resource contract, and tests must be synchronized with this amendment.

Because the approved status flow does not return a completed order to an active state, the first completion timestamp remains authoritative.

---

## 10. Money Rules

Relevant columns:

```text
orders.total
orders.paid_amount
```

All response money values are decimal strings:

```json
"12500.00"
```

The API must not return money as floating-point JSON numbers.

All aggregation must use database decimal arithmetic.

---

## 11. Sales Sections

The response contains three independent sections:

```text
sales
collectedSales
uncollectedSales
```

They have different business meanings and must not be merged.

---

## 12. Sales

Definition:

```text
sales = SUM(orders.total)
```

Included statuses:

```text
PENDING
CONFIRMED
IN_PROGRESS
COMPLETED
```

Excluded status:

```text
CANCELLED
```

The current order status determines inclusion.

Fields:

```text
sales.total
sales.today
sales.period
```

### `sales.total`

Total order value since the beginning of the system for all currently non-cancelled orders.

### `sales.today`

Total order value for currently non-cancelled orders created during the current UTC day.

Date column:

```text
orders.created_at
```

### `sales.period`

Total order value for currently non-cancelled orders created inside the effective sales period.

Date column:

```text
orders.created_at
```

The range is inclusive.

---

## 13. Collected Sales

Definition:

```text
collectedSales = SUM(orders.paid_amount)
```

Included status:

```text
COMPLETED only
```

Excluded statuses:

```text
PENDING
CONFIRMED
IN_PROGRESS
CANCELLED
```

A paid amount on a non-completed order is not counted as collected sales in this dashboard.

Fields:

```text
collectedSales.total
collectedSales.today
collectedSales.period
```

### `collectedSales.total`

Sum of `paid_amount` for all orders whose current status is `COMPLETED`.

### `collectedSales.today`

Sum of `paid_amount` for currently completed orders whose `completed_at` falls inside the current UTC day.

### `collectedSales.period`

Sum of `paid_amount` for currently completed orders whose `completed_at` falls inside the effective sales period.

The range is inclusive.

### Overpayment

Feature 005 permits overpayment. Therefore collected sales use the full stored `paid_amount`, even when it is greater than `total`.

---

## 14. Uncollected Sales

Per-order calculation:

```text
GREATEST(total - paid_amount, 0)
```

Aggregate definition:

```text
uncollectedSales = SUM(GREATEST(total - paid_amount, 0))
```

Included statuses:

```text
PENDING
CONFIRMED
IN_PROGRESS
COMPLETED
```

Excluded status:

```text
CANCELLED
```

Using a zero floor prevents negative uncollected values when an order is overpaid.

Fields:

```text
uncollectedSales.total
uncollectedSales.today
```

There is intentionally no:

```text
uncollectedSales.period
```

### `uncollectedSales.total`

Total outstanding amount across all currently non-cancelled orders.

### `uncollectedSales.today`

Outstanding amount across currently non-cancelled orders created during the current UTC day.

Date column:

```text
orders.created_at
```

---

## 15. Effective Sales Period

Query parameters:

```text
dateFrom
dateTo
```

Format:

```text
YYYY-MM-DD
```

Rules:

1. Both are optional.
2. They must be submitted together.
3. Sending only one returns `422 VALIDATION_ERROR`.
4. `dateFrom` must be earlier than or equal to `dateTo`.
5. Maximum inclusive custom range: 366 days.
6. Dates are interpreted directly as UTC calendar dates.
7. `dateFrom` starts at `00:00:00.000000 UTC`.
8. `dateTo` ends at `23:59:59.999999 UTC`.
9. When omitted, the effective sales period is the current UTC calendar month.

This period controls:

```text
sales.period
collectedSales.period
```

It does not create an uncollected-sales period field.

---

## 16. Order Count

The response contains:

```text
orders.count
```

The count uses:

```text
orders.created_at
```

Period selector:

```text
ordersPeriod
```

Supported values:

```text
today
this_week
this_month
custom
```

Default:

```text
today
```

---

## 17. Order Period Rules

### `today`

Current UTC calendar day.

### `this_week`

The current UTC week starts on Saturday and ends on Friday.

```text
Saturday 00:00:00.000000 UTC
through
Friday 23:59:59.999999 UTC
```

### `this_month`

Current UTC calendar month.

### `custom`

Uses `dateFrom` and `dateTo`.

Rules:

- Both dates are required for `ordersPeriod=custom`.
- Range is inclusive.
- Maximum range: 366 days.
- UTC boundaries are used.

The same date pair is reused for:

- Custom order count.
- `sales.period`.
- `collectedSales.period`.

When `ordersPeriod` is not `custom`, the date pair may still be supplied to control the sales period independently.

---

## 18. Order Status Filter

Optional query parameter:

```text
status
```

Allowed values use the existing integer-backed `OrderStatus` enum:

```text
0 = PENDING
1 = CONFIRMED
2 = IN_PROGRESS
3 = COMPLETED
4 = CANCELLED
```

The filter affects only:

```text
orders.count
```

It must not affect:

```text
sales
collectedSales
uncollectedSales
performance
```

When omitted, `orders.count` includes all statuses, including cancelled orders.

When supplied, only orders with that exact current status are counted.

---

## 19. Six-Month Performance Chart

Response key:

```text
performance
```

The chart returns exactly six UTC calendar-month points:

```text
current UTC month
plus the previous five UTC calendar months
```

Example for August 2026:

```text
2026-03
2026-04
2026-05
2026-06
2026-07
2026-08
```

This is not a rolling 180-day window.

---

## 20. Performance Point

Each point contains:

```text
month
label
sales
ordersCount
```

Example:

```json
{
  "month": "2026-03",
  "label": "March 2026",
  "sales": "125000.00",
  "ordersCount": 32
}
```

### `month`

Stable machine key in `YYYY-MM` format.

### `label`

Localized display label based on `Accept-Language`.

### `sales`

Monthly `SUM(orders.total)` for currently non-cancelled orders created inside the UTC calendar month.

Date column:

```text
orders.created_at
```

### `ordersCount`

Count of currently non-cancelled orders created inside the UTC calendar month.

Date column:

```text
orders.created_at
```

### Status Filter

The request `status` filter does not affect the chart.

### Empty Months

All six points must always be returned.

```json
{
  "month": "2026-05",
  "label": "May 2026",
  "sales": "0.00",
  "ordersCount": 0
}
```

---

## 21. Query Parameters

```text
ordersPeriod
dateFrom
dateTo
status
```

### `ordersPeriod`

```text
type: string
allowed: today, this_week, this_month, custom
default: today
```

### `dateFrom`

```text
type: date
format: YYYY-MM-DD
required with dateTo
required when ordersPeriod=custom
```

### `dateTo`

```text
type: date
format: YYYY-MM-DD
required with dateFrom
required when ordersPeriod=custom
greater than or equal to dateFrom
maximum inclusive range: 366 days
```

### `status`

```text
type: integer
allowed: 0, 1, 2, 3, 4
nullable
```

---

## 22. Request Examples

### Today order count and current-month sales period

```http
GET /api/v1/admin/dashboard
```

### Current week completed-order count

```http
GET /api/v1/admin/dashboard?ordersPeriod=this_week&status=3
```

### Current month all-order count

```http
GET /api/v1/admin/dashboard?ordersPeriod=this_month
```

### Custom count and sales period

```http
GET /api/v1/admin/dashboard?ordersPeriod=custom&dateFrom=2026-07-01&dateTo=2026-07-31
```

### Today cancelled-order count with a separate custom sales period

```http
GET /api/v1/admin/dashboard?ordersPeriod=today&status=4&dateFrom=2026-06-01&dateTo=2026-06-30
```

In the final example:

- `orders.count` counts today's cancelled orders.
- `sales.period` uses June 1–30 UTC.
- `collectedSales.period` uses June 1–30 UTC.
- The status filter does not affect sales or performance.

---

## 23. Response Contract

Example successful `data`:

```json
{
  "salesPeriod": {
    "dateFrom": "2026-07-01",
    "dateTo": "2026-07-31"
  },
  "sales": {
    "total": "850000.00",
    "today": "12500.00",
    "period": "193000.00"
  },
  "collectedSales": {
    "total": "520000.00",
    "today": "8000.00",
    "period": "117000.00"
  },
  "uncollectedSales": {
    "total": "280000.00",
    "today": "4500.00"
  },
  "orders": {
    "period": "this_week",
    "dateFrom": "2026-08-01",
    "dateTo": "2026-08-07",
    "status": 3,
    "count": 27
  },
  "performance": [
    {
      "month": "2026-03",
      "label": "March 2026",
      "sales": "125000.00",
      "ordersCount": 32
    },
    {
      "month": "2026-04",
      "label": "April 2026",
      "sales": "149500.00",
      "ordersCount": 41
    },
    {
      "month": "2026-05",
      "label": "May 2026",
      "sales": "0.00",
      "ordersCount": 0
    },
    {
      "month": "2026-06",
      "label": "June 2026",
      "sales": "176000.00",
      "ordersCount": 47
    },
    {
      "month": "2026-07",
      "label": "July 2026",
      "sales": "193000.00",
      "ordersCount": 52
    },
    {
      "month": "2026-08",
      "label": "August 2026",
      "sales": "12500.00",
      "ordersCount": 4
    }
  ]
}
```

Important:

```text
uncollectedSales.period
```

must never appear.

---

## 24. Response Field Rules

### `salesPeriod`

Returns the effective UTC range used by `sales.period` and `collectedSales.period`.

### `sales`

Always contains:

```text
total
today
period
```

### `collectedSales`

Always contains:

```text
total
today
period
```

### `uncollectedSales`

Always contains only:

```text
total
today
```

### `orders`

Contains:

```text
period
dateFrom
dateTo
status
count
```

### `performance`

Always contains exactly six chronologically ascending points.

---

## 25. Localization

The Admin data keys remain stable English machine keys.

`Accept-Language` affects:

- Response messages.
- Validation errors.
- `performance[].label`.

It does not affect:

```text
month
money formatting
numeric values
status values
date values
```

Required headers:

```http
Content-Language: ar|en
Vary: Accept-Language
```

Arabic point example:

```json
{
  "month": "2026-03",
  "label": "مارس 2026",
  "sales": "125000.00",
  "ordersCount": 32
}
```

---

## 26. Query and Performance Requirements

No cache is used.

The implementation must:

- Use aggregate SQL queries.
- Avoid loading Order models into PHP for summation.
- Avoid N+1 queries.
- Use UTC range boundaries.
- Use indexed timestamp/status columns.
- Use decimal database aggregation.
- Fill missing chart months in bounded application logic or a bounded calendar strategy.
- Return exactly six chart points.
- Keep query count bounded and documented.

Indexes must be verified against actual MySQL query plans, including possible use of:

```text
orders(status, created_at)
orders(created_at)
orders(status, completed_at)
orders(completed_at)
```

Final index choices must follow `EXPLAIN` results and avoid duplicating existing Feature 005 indexes.

---

## 27. Suggested Components

```text
app/Http/Controllers/Api/V1/Admin/Dashboard/DashboardController.php
app/Http/Requests/Api/V1/Admin/Dashboard/ShowDashboardRequest.php
app/Http/Resources/Api/V1/Admin/Dashboard/DashboardResource.php
app/Queries/Dashboard/DashboardAnalyticsQuery.php
app/Services/Dashboard/DashboardDateRangeResolver.php
```

### `ShowDashboardRequest`

- Validate query parameters.
- Enforce paired dates.
- Enforce custom-period requirements.
- Enforce the 366-day maximum.
- Validate `OrderStatus`.

### `DashboardDateRangeResolver`

- Resolve UTC today.
- Resolve UTC Saturday–Friday week.
- Resolve UTC current month.
- Resolve custom inclusive range.
- Resolve the six UTC calendar months.

### `DashboardAnalyticsQuery`

- Aggregate sales.
- Aggregate collected sales.
- Aggregate uncollected sales.
- Count orders.
- Build monthly performance aggregates.

### `DashboardResource`

- Format money as decimal strings.
- Return the exact contract.
- Localize chart labels.
- Guarantee six ordered points.
- Exclude `uncollectedSales.period`.

---

## 28. Order Completion Integration

Feature 005 must be amended as follows.

### Migration

```text
orders.completed_at nullable
```

### Model

Add a datetime cast for `completed_at`.

### Status Transition Action

When transitioning to `COMPLETED`:

```text
completed_at = current UTC timestamp
```

When transitioning from `COMPLETED` to `CANCELLED`:

```text
preserve completed_at
```

### Client Input

`completedAt` is never accepted from:

- Create Order.
- Update Order.
- Change Status request body.
- Payment update.
- Item update.

### Existing Data

The implementation plan must document a backfill rule for existing completed orders.

Recommended approximation:

```text
completed_at = updated_at
```

for existing rows whose current status is `COMPLETED` and `completed_at` is null.

---

## 29. Error Codes

Applicable shared codes:

```text
VALIDATION_ERROR
UNAUTHENTICATED
FORBIDDEN
```

Optional feature code:

```text
INVALID_DASHBOARD_PERIOD
```

Status behavior:

- Unauthenticated: `401`.
- Missing permission: `403`.
- Invalid query parameters: `422`.
- Unexpected database failure: project-standard safe server error.
- Internal SQL or schema details are never exposed.

---

## 30. Required Tests

### Authorization

- Unauthenticated request returns `401`.
- Missing `dashboard.view` returns `403`.
- Authorized Admin receives `200`.
- Super-admin receives the permission from Seeder.

### Time Boundaries

- UTC today boundaries.
- UTC current-month boundaries.
- UTC Saturday–Friday current week.
- Custom inclusive range.
- Leap-year handling.
- 366-day range accepted.
- More than 366 days rejected.
- Paired-date validation.

### Sales

- Cancelled orders excluded.
- Pending, confirmed, in-progress, and completed included.
- `sales.total` correct.
- `sales.today` uses `created_at`.
- `sales.period` uses `created_at`.
- Default period is the current UTC month.

### Collected Sales

- Completed orders included.
- Non-completed paid orders excluded.
- Cancelled orders excluded.
- `collectedSales.total` correct.
- `collectedSales.today` uses `completed_at`.
- `collectedSales.period` uses `completed_at`.
- Overpayment is fully included.

### Uncollected Sales

- Non-cancelled orders included.
- Cancelled orders excluded.
- Uses `GREATEST(total - paid_amount, 0)`.
- Overpayment never produces a negative value.
- `uncollectedSales.total` correct.
- `uncollectedSales.today` uses `created_at`.
- Response has no `uncollectedSales.period`.

### Order Count

- Today count.
- Saturday–Friday week count.
- Month count.
- Custom count.
- No status filter includes every status.
- Each status filter works.
- Status filter affects only the count.
- Cancelled status can be counted.

### Performance

- Exactly six points.
- Current month plus previous five.
- Chronological order.
- UTC calendar months.
- Sales exclude cancelled orders.
- Order count excludes cancelled orders.
- Empty month returns zero values.
- Status filter does not affect the chart.
- Arabic and English labels.

### Completion Timestamp

- Transition to completed sets `completed_at`.
- Timestamp is UTC.
- Cancellation preserves it.
- Client cannot submit `completedAt`.
- Existing completed-order backfill is covered.

### Contract

- Money values are strings.
- Status is integer or null.
- `Content-Language` and `Vary` headers.
- Shared response envelope.
- Existing `StatusCode::*` usage.
- No internal SQL or implementation details.

### Performance Verification

- Aggregate query count remains bounded.
- No N+1 behavior.
- Relevant `EXPLAIN` plans are documented.
- Tests use the dedicated MySQL test database.

---

## 31. Acceptance Criteria

The feature is complete when:

1. `GET /api/v1/admin/dashboard` is available.
2. `dashboard.view` is seeded and enforced.
3. All backend date calculations use UTC.
4. `sales.total`, `sales.today`, and `sales.period` are correct.
5. Sales exclude currently cancelled orders.
6. `collectedSales` includes only currently completed orders.
7. Collected today and period use `completed_at`.
8. `uncollectedSales` includes all currently non-cancelled orders.
9. Uncollected values never become negative.
10. `uncollectedSales` contains no `period` key.
11. Orders can be counted for today, Saturday–Friday week, month, or custom range.
12. Status filtering affects order count only.
13. The chart is unaffected by status filtering.
14. The chart returns exactly six UTC calendar months.
15. Missing months return zero values.
16. Money is returned as decimal strings.
17. No dashboard tables, cache, queue, export, or write endpoint is added.
18. Feature 005 is synchronized with `completed_at`.
19. Pest passes against MySQL.
20. Pint passes.
21. PHPStan/Larastan passes at the configured project level.

---

## 32. Final Approved Scope

```text
Admin-only read endpoint
dashboard.view permission
UTC-only calculations

sales:
- total
- today
- period

collectedSales:
- total
- today
- period

uncollectedSales:
- total
- today
- no period

order count:
- today
- current Saturday-to-Friday week
- current month
- custom date range
- optional exact status filter

performance chart:
- current UTC month plus previous five months
- monthly non-cancelled sales
- monthly non-cancelled order count
- zero-filled missing months
- unaffected by status filter

orders.completed_at amendment
no new dashboard tables
no cache
no exports
no scheduled reports
no write operations
```
