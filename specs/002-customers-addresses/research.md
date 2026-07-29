# Feature 002 Research — Customers and Addresses

**Feature:** `002-customers-addresses`  
**Date:** 2026-07-29  
**Status:** Complete

## Decision 1: Use a dedicated libphonenumber-compatible runtime dependency

**Decision**: Use `giggsey/libphonenumber-for-php` behind a local
`PhoneNumberService` for customer and address phone parsing, formatting, E.164
normalization, and extension rejection.

**Rationale**:

- The approved feature reference explicitly requires a libphonenumber-compatible
  implementation.
- Laravel and the current dependency set do not already provide robust
  international phone parsing.
- Wrapping the library in a local service keeps the implementation portable,
  testable, and isolated from transport concerns.
- The feature needs both Egyptian default-country parsing and support for valid
  international numbers, which are exactly the strengths of this dependency.

**Alternatives considered**:

- **Hand-written normalization logic**: rejected because it would be error-prone
  and would not satisfy the approved "libphonenumber-compatible" decision.
- **A higher-level Laravel phone package**: rejected for the plan because the
  feature only needs parsing/formatting and should avoid a broader package layer
  when a focused library plus local wrapper is sufficient.

## Decision 2: Keep customer list querying in one dedicated Query object

**Decision**: Implement `GET /api/v1/admin/customers` through a single
`CustomerIndexQuery` using allow-listed filters, bounded pagination, and
explicit sort defaults.

**Rationale**:

- The route has enough search/filter/sort behavior to justify a Query object.
- The repository already uses `spatie/laravel-query-builder`, which matches the
  approved standards for allow-listed query parameters.
- Centralizing these filters reduces controller logic and makes task generation
  cleaner.
- Feature 002 has one main complex read endpoint; nested address lists remain
  small and customer-scoped, so they do not need a second heavy query layer.

**Alternatives considered**:

- **Controller-built query logic**: rejected because the customer index has
  enough complexity to warrant reuse and centralization.
- **Multiple small query helpers per filter**: rejected as unnecessary
  fragmentation for a single customer index surface.

## Decision 3: Use transactional row-locking for address uniqueness and default invariants

**Decision**: Enforce address duplicate prevention and one-default-address
 behavior through transactions plus row-level locking on the customer row and
 relevant address rows, rather than depending on generated-column uniqueness in
 the first implementation pass.

**Rationale**:

- The per-customer address set is tightly bounded (`20` active addresses), so
  transaction-scoped locking remains practical.
- This avoids portability and rollout complexity around generated columns while
  still satisfying the approved business rules.
- The design still supports real MySQL concurrency tests for duplicate-address
  and default-address races.
- The feature reference explicitly allows transaction + indexed lookup +
  application invariant enforcement as an approved strategy.

**Alternatives considered**:

- **Generated active-hash unique column**: rejected for the first plan because
  it adds more migration and deployment complexity than is necessary to satisfy
  the current invariants.
- **Unsafe pre-check only**: rejected because the feature and shared standards
  explicitly disallow relying on a check without final race protection.

## Decision 4: Enforce nested ownership through customer-scoped address resolution

**Decision**: Resolve customer-address routes through the parent customer
 relationship (`$customer->addresses()...`) and permission middleware, without
 introducing dedicated Policies unless implementation reveals a concrete
 resource-specific rule that middleware plus scoped lookup cannot express
 clearly.

**Rationale**:

- Feature 002 has one administrator role in practice, with access boundaries
  driven by permissions and nested ownership rather than per-tenant or per-user
  scopes.
- Customer-scoped address lookup is the cleanest way to guarantee the approved
  `404` behavior for foreign nested resources.
- Avoiding unnecessary Policies matches the repository rule against speculative
  layers.

**Alternatives considered**:

- **Global address lookup plus manual customer-id comparison**: rejected because
  it is easier to get wrong and weaker for non-disclosure semantics.
- **Policies for every resource immediately**: rejected because they add
  indirection without current scope differentiation.

## Decision 5: Publish a feature-specific OpenAPI contract during planning

**Decision**: Create `contracts/openapi.yaml` now for the protected customer and
address route surface, strict mutation schemas, explicit list/detail Resources,
query defaults, stable `operationId` values, authentication outcomes, restore
conflicts, shared envelopes, and stable error codes.

**Rationale**:

- The project standards require API documentation to track actual tested
  behavior.
- Feature 002 has a concrete external interface even though it is admin-only.
- Capturing the route surface now reduces drift before `/speckit-tasks` and
  implementation.
- Separate list and detail schemas prevent the frontend from guessing
  `addresses`, `addressesCount`, deletion metadata, or nested Resource shapes.
- `additionalProperties: false` and `minProperties: 1` keep mutation contracts
  aligned with backend allow-lists.
- Shared `403` examples document both `USER_INACTIVE` and `FORBIDDEN` without
  creating duplicated security test tasks.

**Alternatives considered**:

- **Delay contract work until implementation**: rejected because the plan phase
  is explicitly responsible for interface contracts.

## Decision 6: Keep tests consolidated into five business groups

**Decision**: Generate tasks later around five grouped verification areas:

1. customer API and permission coverage
2. address API, ownership, and permission coverage
3. customer/address matching coverage
4. critical MySQL concurrency coverage
5. minimal auth-boundary, localization, and architecture coverage

**Rationale**:

- The approved feature reference explicitly requires lean task and test
  grouping.
- This keeps Feature 002 from duplicating the large security matrix already
  owned by Feature 001.
- The grouped structure still protects the business-critical risks of identity,
  uniqueness, default-address behavior, and authorization boundaries.

**Alternatives considered**:

- **One task or one test file per route, permission, or validation rule**:
  rejected because the reference explicitly forbids this explosion of task
  count.

## Decision 7: Audit and adapt the existing implementation before creating files

**Decision**: Begin implementation with a repository and database audit, then
classify existing artifacts as reuse, update, replace, delete only when
unreferenced, or create only when missing.

**Rationale**:

- The feature branch already contains legacy implementation work.
- Blindly creating migrations, routes, Models, or permissions could duplicate
  artifacts or damage existing data.
- The approved Feature specification defines the target behavior, while the
  audit determines the safest path from the current repository state.
- Forward-only altering migrations preserve deployed databases and production
  records.

**Required audit areas**:

- migrations and actual MySQL schema
- Models and relationships
- API routes and middleware
- Controllers, Requests, Resources, Actions, Services, and Queries
- permissions, seeders, localization, Postman/OpenAPI, and tests
- cross-module references to customer and address records

**Alternatives considered**:

- **Delete the old module and regenerate it**: rejected because references and
  production data may already exist.
- **Create parallel version-two tables or routes**: rejected because the
  approved contract requires one canonical customer/address domain.
- **Modify old executed migrations**: rejected because deployed environments
  require forward-only migration history.

## Decision 8: Use one deterministic lock order for address mutations

**Decision**: For address create, update, restore, delete, and set-default
operations, lock the customer row first, then lock relevant address rows ordered
by `id`.

**Rationale**:

- A single lock order reduces deadlock risk.
- The per-customer address set is bounded to 20 active rows.
- The same order can be reused by all Actions that enforce limit, duplicate,
  restore, and default-address invariants.
