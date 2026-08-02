# Implementation Plan: Admin Dashboard Analytics

**Branch**: `007-admin-dashboard-analytics` | **Date**: 2026-08-02 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/007-admin-dashboard-analytics/spec.md`

## Summary

Feature 007 adds one read-only Admin dashboard analytics endpoint,
`GET /api/v1/admin/dashboard`, plus the minimum supporting Order-domain
amendment required for accurate collected-sales time windows.

The implementation will:

- add `dashboard.view` permission and one protected Admin route
- introduce an exact `filter[...]` query contract for `ordersPeriod`,
  `dateFrom`, `dateTo`, and `status`
- compute all analytics through bounded SQL aggregation instead of per-order
  loading
- add nullable backend-controlled `orders.completed_at` with a safe backfill for
  existing completed rows
- expose `completedAt` in approved Admin order resources only
- localize messages and chart labels while preserving stable machine keys and
  money strings
- verify fixed query budget, `EXPLAIN` plans, and Feature 005 synchronization

## Technical Context

| Area | Decision |
|---|---|
| Runtime | Implementation is blocked until `php -v` and `composer check-platform-reqs` confirm the actual runtime satisfies the installed lockfile; the verified result must be recorded before coding begins |
| Framework | Laravel 13 (from `composer.lock`) |
| Primary Dependencies | Laravel Sanctum, `spatie/laravel-permission`, `spatie/laravel-query-builder`, Pest, Larastan/PHPStan, Laravel Pint |
| Storage | MySQL for analytics and order state; Laravel Filesystem unchanged for this feature |
| Project Type | Backend-only, API-first conventional Laravel monolith |
| Target Platform | Hostinger-compatible PHP/MySQL deployment; no new worker, scheduler, Redis, cache, or export dependency |
| Authentication | Existing Admin Sanctum Bearer flow with `admin.auth.headers`, `auth:sanctum`, `admin.user_type`, and `admin.active` middleware stack |
| Authorization | New exact permission `dashboard.view` enforced by route middleware; no hidden bypass |
| Query Strategy | One dedicated dashboard query/read layer, typed DashboardFilterData, and focused composable query-filter classes; API dates remain inclusive while SQL uses half-open UTC ranges (`>= startAt`, `< exclusiveEndAt`); no controller filtering or per-order loops |
| Performance Goals | Dashboard analytics may execute at most five Order-data SELECT statements per request, excluding shared authentication/authorization queries; implementation should target three aggregate reads, keep output fixed at six months, and use only verified indexes |
| Constraints | UTC-only boundaries; exact shared API envelope; `HttpStatusCode` enum usage; Arabic/English localization; no write endpoints, cache, queues, exports, or dashboard tables |
| Scale/Scope | One new dashboard endpoint, one `filter` deep-object with four optional scalar members, six chart points, one new Order timestamp column/backfill, and synchronized Feature 005 contracts/regressions |

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Pre-design result

PASS.

- **Authority, precedence, and traceability**: Every planned behavior maps to
  Feature 007 requirements and the synchronized Feature 005 completion-timestamp
  amendment.
- **Conflict and exception gate**: No new governance exception is required.
  Feature 007 explicitly follows the already-approved Feature 005 exception
  boundaries without widening them.
- **Repository boundary**: Backend-only Laravel work; no frontend scope.
- **Architecture**: Thin controller + dedicated Form Request + Resource +
  focused Query/Service additions only where justified.
- **API contract**: One versioned GET endpoint, stable envelope, bounded query
  keys, and stable `camelCase` response keys.
- **Authentication and authorization**: Existing Admin stack is preserved and
  extended only by `permission:dashboard.view`.
- **Trust and content safety**: Backend owns time boundaries, money
  aggregation, completion timestamps, and query allow-listing.
- **Database integrity**: `completed_at` is nullable, backend-controlled,
  uses the same temporal precision as the existing Order timestamps, is
  assigned atomically on completion, and is backfilled idempotently without
  modifying legacy `updated_at` values.
- **Files**: No upload or file-surface change.
- **Localization**: Arabic/English messages and labels only; machine keys stay
  stable English.
- **Testing and quality**: MySQL-backed Pest coverage, query-budget checks,
  Pint, and Larastan/PHPStan remain mandatory.
- **Scope and operations**: No speculative infrastructure or extra analytics
  surfaces are introduced.

### Post-design result

PASS.

The produced design artifacts keep the feature inside the approved monolith,
use only justified abstractions, avoid contract creep, and document the exact
query/index verification and completion-timestamp migration/backfill strategy
required before implementation is complete.

## Project Structure

### Documentation (this feature)

```text
specs/007-admin-dashboard-analytics/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── openapi.yaml
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Data/
│   └── Dashboard/
│       └── DashboardFilterData.php
├── Actions/
│   └── Orders/
│       └── ChangeOrderStatusAction.php
├── Enums/
│   ├── HttpStatusCode.php
│   └── Orders/
│       └── OrderStatus.php
├── Http/
│   ├── Controllers/Api/V1/Admin/
│   │   ├── Dashboard/
│   │   │   └── DashboardController.php
│   │   └── Orders/
│   ├── Requests/Api/V1/Admin/
│   │   ├── Dashboard/
│   │   │   └── ShowDashboardRequest.php
│   │   └── Orders/
│   └── Resources/Api/V1/Admin/
│       ├── Dashboard/
│       │   └── DashboardResource.php
│       └── Orders/
│           ├── AdminOrderIndexResource.php
│           └── AdminOrderResource.php
├── Models/
│   └── Order.php
├── Queries/
│   ├── Dashboard/
│   │   ├── DashboardAnalyticsQuery.php
│   │   └── Filters/
│   │       ├── CreatedAtRangeQueryFilter.php
│   │       ├── CompletedAtRangeQueryFilter.php
│   │       ├── CurrentStatusQueryFilter.php
│   │       └── NonCancelledQueryFilter.php
│   └── Orders/
│       └── AdminOrderIndexQuery.php
└── Services/
    ├── Dashboard/
    │   ├── DashboardDateRangeResolver.php
    │   └── DashboardFilterShapeGuard.php
    └── Orders/
        └── OrderStatusTransitionService.php

database/
├── factories/
├── migrations/
│   └── *_add_completed_at_to_orders_table.php
└── seeders/
    ├── RolesAndPermissionsSeeder.php
    └── Permissions/

routes/
└── api/v1/
    └── admin.php

tests/
├── Architecture/
│   ├── DashboardRouteContractTest.php
│   └── DashboardOpenApiContractTest.php
├── Feature/
│   ├── Api/V1/Admin/
│   │   ├── Dashboard/
│   │   └── Orders/
│   └── Database/
│       ├── Dashboard/
│       │   ├── DashboardQueryPlanTest.php
│       │   └── DashboardQueryBudgetTest.php
│       └── Orders/
│           └── CompletedAtMigrationBackfillTest.php
├── Unit/
│   └── Dashboard/
└── Regression/
    └── Orders/
```

**Structure Decision**: Reuse the existing Orders stack and route grouping,
introduce one dedicated Dashboard controller/request/resource/query-filter/service
vertical, and amend existing Order model/resource/action code in place for
`completedAt`. The migration must inspect the existing Order timestamp
precision and create `completed_at` with matching precision. Feature 005
synchronization covers Admin Order index and show resources, every mutation
request exactness test, its OpenAPI contract, Postman examples, and affected
regressions. No module system, repository layer, queue, or cache layer is added.

## Phase 0 Research

Research decisions are recorded in [research.md](./research.md) and resolve:

- runtime compatibility gate using `php -v` and `composer check-platform-reqs`
- query-filter request syntax and raw repeated-member detection
- fixed analytics query budget of at most five Order-data reads, with a target of three aggregate reads
- UTC Saturday-to-Friday week resolution
- first-completion timestamp semantics and cancellation preservation
- idempotent backfill approach for existing completed orders
- exact query-budget and `EXPLAIN` verification expectations

## Phase 1 Design Outputs

- [data-model.md](./data-model.md)
- [contracts/openapi.yaml](./contracts/openapi.yaml)
- [quickstart.md](./quickstart.md)

## Complexity Tracking

No new Constitution exception is proposed for Feature 007.


## Planning Decisions Required Before Tasks

The following decisions are frozen for task generation:

1. **Runtime gate**: record successful `php -v` and
   `composer check-platform-reqs` output before implementation.
2. **SQL boundaries**: API dates remain inclusive `YYYY-MM-DD`, but every
   datetime predicate uses `column >= startAt AND column < exclusiveEndAt`.
3. **Timestamp precision**: inspect the existing `orders.created_at` and
   `orders.updated_at` definitions and create `completed_at` with identical
   precision.
4. **Query budget**: the dashboard analytics layer may issue at most five
   Order-data SELECT statements per request; shared Sanctum/Admin/permission
   queries are measured separately. The implementation target is three reads:
   one conditional financial aggregate query, one order-count query, and one
   six-month grouped performance query.
5. **Index evidence**: record existing indexes and representative MySQL
   `EXPLAIN` output before approving any new index.
6. **Deployment gate**: Feature 007 is not deployment-complete until the
   `completed_at` schema change and idempotent legacy backfill succeed.
7. **Filter contract**: the only top-level query key is `filter`; allowed
   nested members are `ordersPeriod`, `dateFrom`, `dateTo`, and `status`.
8. **Filter pipeline**: normalize valid input into `DashboardFilterData` and
   compose focused query-filter classes on the three aggregate builders.
9. **Repeated nested keys**: run `DashboardFilterShapeGuard` against the
   original query string before Form Request normalization so duplicate
   `filter[...]` members are rejected rather than silently collapsed.
