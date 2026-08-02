# Tasks: Admin Dashboard Analytics

**Input**: Design documents from `/specs/007-admin-dashboard-analytics/`

**Approved source**: `docs/features/007-admin-dashboard-analytics.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`, and `quickstart.md`

**Tests**: Tests are mandatory implementation work. Feature 007 coverage must
include success, exact query-filter validation, authentication, authorization,
localization, persistence, contract, query-budget, query-plan, migration,
backfill, and atomic status-transition behaviour required by the approved
specification and constitution.

**Organization**: Tasks are grouped by user story and implementation layer.
The dashboard route must not be exposed until the complete approved response
contract is implemented. Story-level query and database behaviour may be
developed incrementally, but the endpoint is released only in the final
integration phase.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel only after all declared dependencies are
  complete and when changed files do not overlap.
- **[Story]**: User story mapping, for example `[US1]`.
- Every task includes the primary file paths it changes.
- Existing compatible artifacts must be reused or updated instead of recreated.
- Tests may be scaffolded early, but a test task is complete only when its
  corresponding behaviour passes.
- The repository-standard `App\Enums\HttpStatusCode` enum remains the approved
  application-controlled status enum for this feature.
- All dashboard filters use the approved `filter[...]` query contract and
  focused query-filter classes. Controllers and Resources must not contain
  database filtering conditions.

## Path Conventions

- Laravel application code: `app/`
- Versioned API routes: `routes/api.php` and `routes/api/v1/`
- Database artifacts: `database/migrations/`, `database/factories/`, and
  `database/seeders/`
- API Feature Tests: `tests/Feature/Api/V1/`
- Database and query verification: `tests/Feature/Database/`
- Concurrency tests: `tests/Concurrency/`
- Architecture and contract tests: `tests/Architecture/`
- Feature documentation: `specs/007-admin-dashboard-analytics/`

---

## Phase 1: Setup — Repository and Runtime Verification

**Purpose**: Confirm the exact extension points, runtime compatibility, query
filter conventions, and existing Order/Admin behaviour before implementation.

- [X] T001 Audit the existing Admin route registration, middleware order,
  permission flow, Order status workflow, Order resources, shared API response
  helpers, and query classes in `routes/api/v1/admin.php`,
  `app/Actions/Orders/`, `app/Services/Orders/`,
  `app/Http/Resources/Api/V1/Admin/Orders/`, `app/Queries/`, and
  `app/Support/Api/`
- [X] T002 Execute `php -v` and `composer check-platform-reqs`, verify the
  installed PHP/Laravel/Sanctum/Spatie/Pest/Pint/Larastan surface against
  `composer.json` and `composer.lock`, record the result, and block
  implementation if either runtime command fails
- [X] T003 [P] Confirm the dedicated MySQL testing configuration, UTC
  time-freezing helpers, locale-testing helpers, raw query-string request
  helpers, SQL-listener utilities, and query-count assertions in
  `.env.example`, `phpunit.xml`, `tests/Pest.php`, and `tests/Support/`
- [X] T004 [P] Inspect the existing Order columns, timestamp precision,
  indexes, Feature 005 OpenAPI contract, and Postman extension points in
  `database/migrations/`, `specs/005-orders-management/contracts/openapi.yaml`,
  and `postman/Service-Commerce.postman_collection.json`
- [X] T005 [P] Verify the repository's existing query-filter conventions,
  Spatie Query Builder usage, allow-listed filter patterns, and naming
  standards before creating dashboard filters in `app/Queries/`,
  `app/Http/Requests/`, and existing feature tests

**Checkpoint**: Runtime compatibility, repository conventions, timestamp
precision, indexes, and query-filter patterns are verified.

---

## Phase 2: Foundational — Blocking Prerequisites

**Purpose**: Establish the Order completion invariant, permission, typed filter
input, UTC ranges, translations, and contract guardrails required by all
dashboard calculations.

**⚠️ CRITICAL**: No dashboard aggregate implementation begins until this phase
passes. The Order transition workflow must already maintain `completed_at`
before collected-sales queries are introduced.

- [X] T006 Create the `orders.completed_at` schema amendment using the same
  precision as the existing Order timestamps and implement the approved
  idempotent legacy backfill without rewriting `updated_at` in
  `database/migrations/*_add_completed_at_to_orders_table.php`
- [X] T007 [P] Register the exact `dashboard.view` permission and integrate it
  idempotently into the normal super-admin seeding flow in
  `database/seeders/RolesAndPermissionsSeeder.php`,
  `database/seeders/Permissions/`, and `database/seeders/DatabaseSeeder.php`
- [X] T008 [P] Implement the typed dashboard filter and UTC date-range value
  objects in `app/Data/Dashboard/DashboardFilterData.php` and
  `app/Data/Dashboard/DashboardDateRange.php`
- [X] T009 [P] Implement raw query-shape validation for the single top-level
  `filter` object, allowed nested members, scalar-only values, unknown keys,
  array-shaped values, and repeated nested members by inspecting the original
  query string in `app/Services/Dashboard/DashboardFilterShapeGuard.php`
- [X] T010 Implement the UTC date-range resolver after T008, returning inclusive
  API dates and half-open SQL boundaries for today, Saturday-to-Friday week,
  current month, custom ranges, and the six-month window in
  `app/Services/Dashboard/DashboardDateRangeResolver.php`
- [X] T011 [P] Add Arabic and English dashboard validation, authorization,
  success-message, and full-month chart-label translations in
  `lang/ar/dashboard.php` and `lang/en/dashboard.php`
- [X] T012 Implement the Order model amendment for `completed_at` casting and
  approved analytics-safe behaviour in `app/Models/Order.php`
- [X] T013 Update the existing locked Order status workflow after T006 and T012
  so the first transition to completed sets `completed_at` atomically, later
  `completed -> cancelled` preserves it, and no other transition overwrites it
  in `app/Actions/Orders/ChangeOrderStatusAction.php` and
  `app/Services/Orders/OrderStatusTransitionService.php`
- [X] T014 Add real MySQL schema and legacy backfill coverage after T006 for
  matching timestamp precision, exact backfill source values, unchanged
  `updated_at`, idempotent re-runs, and no completed legacy row remaining null
  in `tests/Feature/Database/Orders/CompletedAtMigrationBackfillTest.php`
- [X] T015 Add Order transition regression coverage after T013 for first UTC
  completion assignment, preservation on cancellation, and prevention of
  overwrite in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderCompletedAtTransitionTest.php`
- [X] T016 Add real MySQL concurrency coverage after T013 for atomic status and
  completion-time changes under the existing locked workflow in
  `tests/Concurrency/Orders/CompletedAtTransitionConcurrencyTest.php`
- [X] T017 Add the frozen OpenAPI 3.1 contract test only—without requiring a
  route that is not registered yet—for one dashboard path, one operation,
  `filter` deep-object syntax, resolved local references, exact objects,
  localization headers, money strings, and the Feature 005 `completedAt`
  amendment in `tests/Architecture/DashboardOpenApiContractTest.php`

**Checkpoint**: The database and Order lifecycle already guarantee accurate
completion history, and the filter/range foundations are ready.

---

## Phase 3: User Story 1 — Financial Summary Query Layer (Priority: P1)

**Goal**: Implement the exact sales, collected-sales, and uncollected-sales
calculations without exposing a partial endpoint.

**Independent Test**: Execute the financial query layer against MySQL with
orders covering every status, null amount, payment, date boundary, and
overpayment case, then verify the exact fixed-precision result.

### Tests for User Story 1 — Mandatory

- [X] T018 [P] [US1] Add real MySQL financial aggregate coverage for all
  statuses, cancelled exclusion, null totals, null paid amounts, overpayment,
  zero-floor semantics, all-time/today/period windows, and decimal output in
  `tests/Feature/Database/Dashboard/DashboardFinancialAggregationTest.php`
- [X] T019 [P] [US1] Add focused money result-normalization coverage for
  converting database decimal/null aggregate results to exact two-decimal
  strings in `tests/Unit/Dashboard/DashboardMoneyResultNormalizerTest.php`

### Implementation for User Story 1

- [X] T020 [P] [US1] Implement the reusable query filters after T008 and T010
  in `app/Queries/Dashboard/Filters/NonCancelledQueryFilter.php`,
  `app/Queries/Dashboard/Filters/CreatedAtRangeQueryFilter.php`, and
  `app/Queries/Dashboard/Filters/CompletedAtRangeQueryFilter.php`
- [X] T021 [US1] Implement one conditional aggregate SELECT after T020 that
  returns every approved `sales`, `collectedSales`, and `uncollectedSales`
  total/today/period value together, using null-safe fixed-precision MySQL
  expressions and no per-order loading in
  `app/Queries/Dashboard/DashboardAnalyticsQuery.php`
- [X] T022 [US1] Implement the focused money-result normalizer used by the
  dashboard projection in
  `app/Services/Dashboard/DashboardMoneyResultNormalizer.php`

**Checkpoint**: The financial query layer is correct and bounded, but no
incomplete dashboard route has been exposed.

---

## Phase 4: User Story 2 — Sales and Order Period Resolution (Priority: P1)

**Goal**: Resolve and apply exact UTC period rules independently for sales and
order count.

**Independent Test**: Freeze UTC time, create records on half-open boundaries,
and verify all valid and invalid `filter[...]` period combinations through the
resolver and MySQL query layer.

### Tests for User Story 2 — Mandatory

- [X] T023 [P] [US2] Add UTC resolver coverage for today,
  Saturday-to-Friday week, current month, custom date pairing, leap years,
  exactly 366 inclusive days, 367-day rejection, and independent sales/order
  ranges in `tests/Unit/Dashboard/DashboardDateRangeResolverTest.php`
- [X] T024 [P] [US2] Add real MySQL half-open boundary coverage for
  `created_at` and `completed_at` at exact start/end instants in
  `tests/Feature/Database/Dashboard/DashboardDateRangeQueryTest.php`

### Implementation for User Story 2

- [X] T025 [US2] Complete `DashboardFilterData` and
  `DashboardDateRangeResolver` after T023 for default `today`, default
  current-month sales range, custom pairing, non-custom date-pair behaviour,
  and stable response metadata in `app/Data/Dashboard/DashboardFilterData.php`
  and `app/Services/Dashboard/DashboardDateRangeResolver.php`
- [X] T026 [US2] Apply the resolved date ranges only through the approved
  query-filter classes and extend the financial conditional aggregate query
  without adding controller-level filtering in
  `app/Queries/Dashboard/DashboardAnalyticsQuery.php` and
  `app/Queries/Dashboard/Filters/`

**Checkpoint**: Date behaviour is complete at the DTO, resolver, query-filter,
and database levels.

---

## Phase 5: User Story 3 — Status-Filtered Order Count (Priority: P1)

**Goal**: Count orders for the effective order period with one optional exact
current-status filter that affects no other metric.

**Independent Test**: Execute the count query with every accepted status and
without a status, then verify repeated, malformed, unknown, and array-shaped
query-filter input is rejected.

### Tests for User Story 3 — Mandatory

- [X] T027 [P] [US3] Add raw query-shape coverage for unknown top-level keys,
  unknown filter members, arrays, empty values, whitespace-only values, and
  repeated nested members such as
  `filter[status]=1&filter[status]=2` in
  `tests/Feature/Api/V1/Admin/Dashboard/DashboardFilterShapeGuardTest.php`
- [X] T028 [P] [US3] Add real MySQL order-count query coverage for omitted
  status, every exact status `0..4`, cancelled inclusion when unfiltered,
  half-open period bounds, and proof that status affects count only in
  `tests/Feature/Database/Dashboard/DashboardOrderCountQueryTest.php`

### Implementation for User Story 3

- [X] T029 [P] [US3] Implement the exact current-status query filter in
  `app/Queries/Dashboard/Filters/CurrentStatusQueryFilter.php`
- [X] T030 [US3] Implement `ShowDashboardRequest` after T008, T009, T010, and
  T029 so it runs the raw shape guard before normalized validation, accepts
  only the approved `filter[...]` deep-object contract, preserves
  `filter[status]=0`, and creates `DashboardFilterData` in
  `app/Http/Requests/Api/V1/Admin/Dashboard/ShowDashboardRequest.php`
- [X] T031 [US3] Implement the order-count aggregate after T026 and T029,
  applying `CreatedAtRangeQueryFilter` plus optional
  `CurrentStatusQueryFilter` only to the count builder in
  `app/Queries/Dashboard/DashboardAnalyticsQuery.php`

**Checkpoint**: Exact query-filter validation and status-scoped order counting
are complete before route integration.

---

## Phase 6: User Story 5 — Feature 005 Completion Contract Synchronization (Priority: P1)

**Goal**: Expose the backend-controlled completion timestamp on approved Admin
read surfaces and reject it from every client mutation contract.

**Independent Test**: Verify Admin Order index/show output and every affected
Admin/Public mutation request against the synchronized Feature 005 contract.

### Tests for User Story 5 — Mandatory

- [X] T032 [P] [US5] Add Admin Order index/show response coverage for
  ISO-8601 UTC `completedAt|null` and exact request rejection coverage across
  create, update, status, payment, and item mutation endpoints in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderCompletedAtContractTest.php` and
  affected Public Order request tests
- [X] T033 [P] [US5] Add a Feature 005 OpenAPI synchronization test proving the
  complete authoritative Admin Order index/show schemas include read-only
  `completedAt` and no mutation schema accepts it in
  `tests/Architecture/OrdersCompletedAtOpenApiContractTest.php`

### Implementation for User Story 5

- [X] T034 [US5] Expose read-only `completedAt` in the existing complete Admin
  Order index/show resources and explicitly reject it from every affected
  Admin/Public mutation request in
  `app/Http/Resources/Api/V1/Admin/Orders/AdminOrderIndexResource.php`,
  `app/Http/Resources/Api/V1/Admin/Orders/AdminOrderResource.php`,
  `app/Http/Requests/Api/V1/Admin/Orders/`, and
  `app/Http/Requests/Api/V1/Public/Orders/CreatePublicOrderRequest.php`
- [X] T035 [US5] Synchronize all affected Feature 005 artifacts in
  `docs/features/005-orders-management.md`,
  `specs/005-orders-management/spec.md`,
  `specs/005-orders-management/plan.md`,
  `specs/005-orders-management/tasks.md`,
  `specs/005-orders-management/contracts/openapi.yaml`, and
  `postman/Service-Commerce.postman_collection.json`

**Checkpoint**: Feature 005 remains the authoritative complete Order contract
and is synchronized before Feature 007 release.

---

## Phase 7: User Story 4 — Six-Month Performance Query Layer (Priority: P2)

**Goal**: Produce exactly six chronological UTC calendar-month points with
localized labels and explicit zero filling.

**Independent Test**: Run the performance query across a year boundary with
cancelled and empty months and verify the exact six-point projection in both
locales.

### Tests for User Story 4 — Mandatory

- [X] T036 [P] [US4] Add real MySQL grouped-performance coverage for the current
  UTC month plus previous five, chronological ordering, year crossing,
  non-cancelled filtering, null totals, and out-of-window exclusion in
  `tests/Feature/Database/Dashboard/DashboardPerformanceQueryTest.php`
- [X] T037 [P] [US4] Add zero-fill and Arabic/English full-month label coverage
  in `tests/Unit/Dashboard/DashboardPerformanceProjectionTest.php`

### Implementation for User Story 4

- [X] T038 [US4] Implement the grouped six-month aggregate query after T020 and
  T025, applying only the six-month `CreatedAtRangeQueryFilter` and
  `NonCancelledQueryFilter`, then build six zero-filled chronological points
  with localized labels in
  `app/Queries/Dashboard/DashboardAnalyticsQuery.php` and
  `app/Services/Dashboard/DashboardPerformanceProjector.php`

**Checkpoint**: Every section required by the final dashboard response now
exists before the endpoint is registered.

---

## Phase 8: Complete Endpoint Integration — Exact Contract Release

**Purpose**: Combine all completed query layers into the exact response and
only now expose the Admin route.

- [X] T039 Implement the complete `DashboardResource` after T021, T031, and
  T038 with exactly `salesPeriod`, `sales`, `collectedSales`,
  `uncollectedSales`, `orders`, and `performance`; exclude all undocumented
  fields and serialize all money values as two-decimal strings in
  `app/Http/Resources/Api/V1/Admin/Dashboard/DashboardResource.php`
- [X] T040 Implement the thin dashboard controller after T030 and T039, invoke
  the complete analytics query, and return the shared success envelope using
  `HttpStatusCode::OK` in
  `app/Http/Controllers/Api/V1/Admin/Dashboard/DashboardController.php`
- [X] T041 Register `GET /api/v1/admin/dashboard` only after T040 using the
  established middleware order and exact `dashboard.view` permission in
  `routes/api/v1/admin.php`
- [X] T042 Add the route and middleware contract test after T041 for exact path,
  GET-only registration, no Public route, authentication, administrator type,
  active-state, and permission order in
  `tests/Architecture/DashboardRouteContractTest.php`
- [X] T043 Add the complete Admin dashboard endpoint suite after T041 covering
  `200`, `401`, inactive/non-Admin rejection, `403`, exact response keys,
  financial sections, periods, status isolation, six performance points,
  Arabic/English labels, `Content-Language`, `Vary`, and shared envelopes in
  `tests/Feature/Api/V1/Admin/Dashboard/AdminDashboardReadTest.php`

**Checkpoint**: The route is exposed for the first time with the complete
approved contract—never as a partial MVP response.

---

## Phase 9: Query Plans, Conditional Index Decision, and Quality Gates

**Purpose**: Prove bounded performance, make evidence-based index decisions,
synchronize verification assets, and run all repository gates.

- [X] T044 Add real MySQL query-budget and representative `EXPLAIN` coverage
  after T043, enforcing at most five Order-data SELECT statements, targeting
  three, comparing small and large datasets, and recording plans for financial,
  count, and performance queries in
  `tests/Feature/Database/Dashboard/DashboardQueryBudgetTest.php` and
  `tests/Feature/Database/Dashboard/DashboardQueryPlanTest.php`
- [X] T045 Record the existing-index and `EXPLAIN` decision for each dashboard
  query family in
  `specs/007-admin-dashboard-analytics/checklists/index-decision.md`; only if
  evidence proves a required non-duplicate index, create a forward-only
  migration in `database/migrations/`, rerun `EXPLAIN`, and update the record
- [X] T046 Update Feature 007 Postman requests, exact `filter[...]` examples,
  query-shape rejection examples, query-budget notes, and every manual
  verification scenario in
  `postman/Service-Commerce.postman_collection.json` and
  `specs/007-admin-dashboard-analytics/quickstart.md`
- [X] T047 Run every Feature 007 and affected Feature 005 suite, including
  `tests/Feature/Api/V1/Admin/Dashboard/`,
  `tests/Feature/Api/V1/Admin/Orders/`,
  `tests/Feature/Database/Dashboard/`,
  `tests/Feature/Database/Orders/`,
  `tests/Concurrency/Orders/`,
  `tests/Architecture/*Dashboard*`,
  `tests/Architecture/*CompletedAt*`, and `tests/Unit/Dashboard/`
- [X] T048 Run the full application regression suite with
  `php artisan test`
- [X] T049 Execute every manual scenario in
  `specs/007-admin-dashboard-analytics/quickstart.md` and record only approved
  environment-specific notes without weakening the frozen contract
- [X] T050 Run `vendor/bin/pint --test`
- [X] T051 Run `vendor/bin/phpstan analyse`

---

## Dependencies and Execution Order

### Phase Dependencies

- **Phase 1** has no dependencies.
- **Phase 2** depends on Phase 1 and blocks all aggregate work.
- **Phase 3** depends on Phase 2.
- **Phase 4** depends on T008, T010, T020, and T021.
- **Phase 5** depends on T008–T010 and the date/query-filter foundations.
- **Phase 6** depends on the completed-at lifecycle from T006, T012, and T013.
- **Phase 7** depends on T020 and T025.
- **Phase 8** depends on all four dashboard sections and the complete request
  contract; the route must not be registered earlier.
- **Phase 9** depends on the complete integrated endpoint.

### Foundational Dependencies

```text
T006 → T012 → T013
T008 → T010
T006 + T012 + T013 → T014 + T015 + T016
T008 + T009 + T010 → T030
```

T009 may run in parallel with T008 because it does not consume the DTO.
T010 begins only after T008.

### Query-Layer Dependencies

```text
T008 + T010 → T020 → T021
T023 → T025
T020 + T025 → T026
T029 + T026 → T031
T020 + T025 → T038
```

### Endpoint Integration Dependencies

```text
T021 + T031 + T038 → T039
T030 + T039 → T040 → T041
T041 → T042 + T043
T043 → T044
```

### Parallel Opportunities

- T003, T004, and T005 may run in parallel after T001.
- T007, T008, T009, and T011 may run in parallel after Phase 1.
- T014, T015, and T016 may run in parallel after T013.
- T018, T019, and T020 may be developed in parallel after Foundation.
- T023 and T024 may run in parallel.
- T027, T028, and T029 may run in parallel after the relevant foundations.
- T032 and T033 may run in parallel.
- T036 and T037 may run in parallel.
- T042 and T043 may run in parallel after T041.
- T044 contract/performance verification and T046 documentation updates may
  proceed in parallel after endpoint integration.

---

## Implementation Strategy

### Contract-Complete First Release

1. Complete runtime and repository verification.
2. Establish `completed_at` and atomic transition behaviour before analytics.
3. Build financial, period, count, and performance query layers independently.
4. Synchronize the Feature 005 completion contract.
5. Integrate the exact complete Dashboard Resource and controller.
6. Register the route only after every required response section is accurate.
7. Verify query plans and add an index only when measured evidence requires it.
8. Run Feature-specific, full-regression, quickstart, formatting, and static
   analysis gates.

### Release Boundary

The recommended release unit is the complete Feature 007 contract. User Story 1
alone is not a releasable endpoint because the approved response always
requires `orders` and `performance`.
