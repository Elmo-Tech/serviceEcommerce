# Data Model: Admin Dashboard Analytics

## 1. Existing entity amendment: `Order`

### Purpose

The existing `orders` table remains the single authoritative source for Feature
007 analytics. The feature adds the minimum persisted field required to compute
collected-sales time windows accurately.

### Persisted fields used by analytics

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | unsigned big integer | existing | Internal key only |
| `status` | int-backed enum | existing | `0..4` mapped by `OrderStatus` |
| `total` | decimal(?,2) nullable | existing | Null-safe aggregate input for sales/uncollected |
| `paid_amount` | decimal(?,2) nullable | existing | Null-safe aggregate input for collected/uncollected |
| `created_at` | timestamp UTC | existing | Drives sales today/period, order count, and performance |
| `updated_at` | timestamp UTC | existing | Used only as the one-time backfill source for legacy completed rows |
| `completed_at` | timestamp UTC nullable, matching existing Order timestamp precision | new | First completion timestamp; backend-controlled |
| `cancelled_at` | timestamp UTC nullable | existing | Operational metadata only; not used for analytics inclusion |

### New field rules

| Rule | Requirement |
|---|---|
| Nullability | `completed_at` is nullable until the first transition to completed |
| Assignment | Only backend status-transition code may set it |
| First completion | The first transition to completed sets it to the current UTC instant |
| Later cancellation | `completed_at` is preserved when the order transitions `completed -> cancelled` |
| Client writes | No request payload may accept `completedAt` |
| Backfill | Existing completed rows with null `completed_at` receive the pre-existing `updated_at` once; backfill does not rewrite `updated_at` |

### State interaction

| Current status | Sales | Collected sales | Uncollected sales | Performance | `completed_at` behavior |
|---|---|---|---|---|---|
| `pending` | included | excluded | included | included | null |
| `confirmed` | included | excluded | included | included | null |
| `in_progress` | included | excluded | included | included | null |
| `completed` | included | included | included with zero-floor formula | included | non-null after first completion |
| `cancelled` | excluded | excluded | excluded | excluded | preserved if order had previously completed |

## 2. New non-persisted projection: `DashboardAnalyticsProjection`

### Purpose

This is the exact response payload returned by `GET /api/v1/admin/dashboard`.
It is never stored in the database.

### Shape

| Field | Type | Notes |
|---|---|---|
| `salesPeriod` | object | Effective UTC range for `sales.period` and `collectedSales.period` |
| `sales` | object | Total, today, period |
| `collectedSales` | object | Total, today, period |
| `uncollectedSales` | object | Total, today only |
| `orders` | object | Effective count period, optional status filter, count |
| `performance` | array of 6 `PerformancePoint` objects | Always six chronological UTC month points |

## 3. Value object: `DashboardDateRange`

### Purpose

Represents one inclusive UTC calendar-date range for API output and one
equivalent half-open datetime range for SQL filtering.

### Fields

| Field | Type | Notes |
|---|---|---|
| `dateFrom` | string `YYYY-MM-DD` | Stable inclusive API start date |
| `dateTo` | string `YYYY-MM-DD` | Stable inclusive API end date |
| `startAt` | UTC datetime | Inclusive start at the first date's `00:00:00` |
| `exclusiveEndAt` | UTC datetime | Exclusive start of the UTC day immediately after `dateTo` |
| `source` | enum-like string | `today`, `this_week`, `this_month`, or `custom` |

### Validation and resolution rules

| Rule | Requirement |
|---|---|
| Pairing | `dateFrom` and `dateTo` must be submitted together |
| Ordering | `dateFrom <= dateTo` |
| Max span | At most 366 inclusive days |
| Default sales range | Current UTC month when date pair is omitted |
| Default order-count period | `today` when `ordersPeriod` is omitted |
| Week definition | Saturday through Friday in UTC |
| SQL predicate | `timestamp >= startAt AND timestamp < exclusiveEndAt` |

## 4. Value object: `MoneyAggregate`

### Purpose

Represents a response-side money block built from SQL decimal aggregation and
serialized as two-decimal strings.

### Shapes

| Block | Fields |
|---|---|
| `sales` | `total`, `today`, `period` |
| `collectedSales` | `total`, `today`, `period` |
| `uncollectedSales` | `total`, `today` |

### Rules

| Rule | Requirement |
|---|---|
| Serialization | Always two-decimal strings, never JSON numbers |
| Null handling | `COALESCE(..., 0)` semantics for nullable totals/paid amounts |
| Overpayment | Allowed; collected uses full `paid_amount`, uncollected floors at zero |
| Cancelled exclusion | Cancelled orders contribute to none of the money blocks |

## 5. Value object: `OrderCountProjection`

### Purpose

Represents the count section of the dashboard response.

### Fields

| Field | Type | Notes |
|---|---|---|
| `period` | string | `today`, `this_week`, `this_month`, or `custom` |
| `dateFrom` | string `YYYY-MM-DD` | Effective order-count range start |
| `dateTo` | string `YYYY-MM-DD` | Effective order-count range end |
| `status` | int or null | Requested exact current status filter |
| `count` | int | Aggregate count |

### Rules

| Rule | Requirement |
|---|---|
| Default period | `today` |
| Status scope | Filters only the count, never money blocks or performance |
| Included statuses without filter | All current statuses, including cancelled |
| Accepted values | Exact scalar representations `"0"` through `"4"` from `filter[status]` |

## 6. Value object: `PerformancePoint`

### Purpose

Represents one dashboard chart bar/point for a UTC calendar month.

### Fields

| Field | Type | Notes |
|---|---|---|
| `month` | string `YYYY-MM` | Stable machine key |
| `label` | localized string | Full month name + four-digit year |
| `sales` | string decimal | Sum of non-cancelled order totals by `created_at` month |
| `ordersCount` | int | Count of non-cancelled orders by `created_at` month |

### Rules

| Rule | Requirement |
|---|---|
| Cardinality | Exactly six points |
| Order | Chronologically ascending |
| Window | Current UTC month plus previous five |
| Missing months | Explicit zero-filled points |
| Status filter | Ignored |

## 7. Query input value object: `DashboardFilterData`

### Purpose

Represents the validated, normalized dashboard filter contract. It is built
only after raw query-shape guarding and Form Request validation.

### Fields

| Field | Type | Default / rule |
|---|---|---|
| `ordersPeriod` | `today|this_week|this_month|custom` | `today` |
| `dateFrom` | `YYYY-MM-DD|null` | paired with `dateTo` |
| `dateTo` | `YYYY-MM-DD|null` | paired with `dateFrom` |
| `status` | `0|1|2|3|4|null` | `null` means all statuses for count |

### Request mapping

| Query member | DTO field |
|---|---|
| `filter[ordersPeriod]` | `ordersPeriod` |
| `filter[dateFrom]` | `dateFrom` |
| `filter[dateTo]` | `dateTo` |
| `filter[status]` | `status` |

Unknown keys, repeated members, arrays, empty values, and whitespace-only values
are rejected before the DTO is constructed.

### Query-filter composition

| Aggregate query | Applied query filters |
|---|---|
| Financial totals | non-cancelled; created-at today/period; completed-at today/period |
| Order count | created-at effective order range; optional exact current status |
| Performance | non-cancelled; created-at six-month range |

The status query filter is never applied to financial or performance queries.

## 8. Permission entity usage

### Permission

| Field | Value |
|---|---|
| Name | `dashboard.view` |
| Guard | Existing configured Admin guard |
| Assignment | Granted to `super-admin` via the normal idempotent seeding flow |

### Access rule

Authenticated active administrators with `dashboard.view` may read the dashboard
endpoint. No public actor and no unauthenticated actor may access it.


## 9. Deployment and backfill state

Feature 007 is deployment-complete only when all conditions hold:

1. `orders.completed_at` exists with the same precision as the existing Order
   timestamps.
2. The idempotent backfill has completed successfully.
3. No currently completed legacy Order remains with `completed_at = null`.
4. Backfilled rows retain their original `updated_at`.
5. Re-running the backfill changes no already-processed row.

## 10. Index decision record

Planning does not pre-approve an index. Implementation must complete a record
with this shape before adding one:

| Query family | Existing candidate index | Representative `EXPLAIN` result | Final decision |
|---|---|---|---|
| Non-cancelled financial aggregates by `created_at` | To inspect | To record | none / approved index |
| Completed collected-sales aggregates by `completed_at` | To inspect | To record | none / approved index |
| Order count by `created_at` and optional status | To inspect | To record | none / approved index |
| Six-month performance by `created_at` | To inspect | To record | none / approved index |

Equivalent or left-prefix-covered Feature 005 indexes must not be duplicated.
