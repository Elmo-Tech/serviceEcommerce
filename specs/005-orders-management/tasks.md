# Tasks: Orders Management

**Input**: Design documents from `/specs/005-orders-management/`

**Approved source**: `docs/features/005-orders-management.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`, `quickstart.md`

**Tests**: Tests are mandatory implementation work. Keep Feature 005 coverage
grouped into risk-based Pest suites covering the applicable success,
validation, authorization, localization, persistence, contract, file,
idempotency, and concurrency behaviour required by the specification and
constitution.

**Organization**: Tasks are grouped by user story so each story can be
implemented and validated independently while preserving the repository's
existing Laravel conventions.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel only after its declared dependencies are complete
  and when changed files do not overlap
- **[Story]**: User story mapping, for example `[US1]`
- Every task includes the primary file paths it changes
- Existing compatible artifacts are reused or updated instead of recreated
- Tests may be scaffolded in parallel, but each test task is complete only when
  the corresponding behaviour passes

## Path Conventions

- Laravel application code: `app/`
- Versioned API routes: `routes/api.php` and `routes/api/v1/`
- Database artifacts: `database/migrations/`, `database/factories/`, and
  `database/seeders/`
- API Feature Tests: `tests/Feature/Api/V1/`
- Database and model verification: `tests/Feature/Database/`
- Concurrency tests: `tests/Concurrency/`
- Architecture tests: `tests/Architecture/`
- Feature documentation: `specs/005-orders-management/`

---

## Phase 1: Setup (Repository Verification and Feature-Specific Preparation)

**Purpose**: Verify governing constraints, preserve the worktree, and confirm
the exact baseline that Feature 005 must extend.

- [x] T001 Audit the existing order-adjacent codebase, shared API response
  helpers, customer services, service catalog modules, tests, translations,
  Postman/OpenAPI artifacts, and open documentation, then record
  `Reuse | Update | Replace | Delete-only-when-unreferenced | Create-only-when-missing`
  decisions in `specs/005-orders-management/implementation-audit.md`
- [x] T002 Verify the installed PHP, Laravel, Sanctum, Spatie Permission,
  Spatie Query Builder, Pest, Pint, and Larastan/PHPStan versions in
  `composer.json`, `composer.lock`, `README.md`, and `phpunit.xml`
- [x] T003 [P] Confirm the current Admin/Public route mounting, middleware
  aliases, shared `ApiResponse` helpers, API locale middleware, and existing
  `app/Enums/HttpStatusCode.php` usage in `routes/api.php`,
  `routes/api/v1/admin.php`, `routes/api/v1/public.php`, `bootstrap/app.php`,
  `app/Support/Api/`, and `app/Enums/`
- [x] T004 [P] Inspect the existing customer matching/address services and
  service option/order-field patterns that Feature 005 must reuse in
  `app/Services/Customers/`, `app/Actions/Customers/`,
  `app/Models/Customer*.php`, `app/Models/Service*.php`, and
  `tests/Feature/Api/V1/Admin/Customers/`
- [x] T005 [P] Confirm the dedicated MySQL testing configuration, concurrency
  test utilities, and current file-storage test patterns in `.env.example`,
  `phpunit.xml`, `tests/Pest.php`, `tests/Support/`, and
  `docs/02-standards/file-storage-standards.md`

**Checkpoint**: Governing rules, current repository patterns, and safe
extension points are understood before schema or implementation changes begin.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish the shared schema, enums, models, reusable order-domain
services, permissions, translations, and route skeletons that block all user
stories.

**⚠️ CRITICAL**: No user story implementation begins until this phase passes.

- [x] T006 Synchronize every newly approved implementation constraint before
  schema or application work begins: 30 files/100 MB per create request,
  3 files/30 MB per standalone upload,
  `ORDER_NUMBER_SEQUENCE_EXHAUSTED`, transactional idempotency reservation
  fields, and `orderItemAnswerId` update semantics. Update
  `docs/features/005-orders-management.md`,
  `specs/005-orders-management/spec.md`,
  `specs/005-orders-management/checklists/requirements.md`,
  `specs/005-orders-management/plan.md`,
  `specs/005-orders-management/research.md`,
  `specs/005-orders-management/data-model.md`,
  `specs/005-orders-management/quickstart.md`, and
  `specs/005-orders-management/implementation-audit.md`, then verify with a
  repository-wide search that no contradictory value or rule remains

- [x] T007 Create the foundational schema for `orders`,
  `order_number_sequences`, `order_idempotency_keys`, `order_items`,
  `order_item_selected_options`, `order_item_selected_option_values`,
  `order_item_answers`, and `order_item_attachments` with the approved foreign
  keys, nullability, precise decimals, query indexes, uniqueness rules,
  nullable historical links, and race-supporting sequence/idempotency
  constraints in `database/migrations/*_create_orders_table.php`,
  `*_create_order_number_sequences_table.php`,
  `*_create_order_idempotency_keys_table.php`,
  `*_create_order_items_table.php`,
  `*_create_order_item_selected_options_table.php`,
  `*_create_order_item_selected_option_values_table.php`,
  `*_create_order_item_answers_table.php`, and
  `*_create_order_item_attachments_table.php`
- [x] T008 [P] Implement the exact integer-backed order-domain enums and casts
  in `app/Enums/Orders/OrderStatus.php`,
  `app/Enums/Orders/PaymentStatus.php`,
  `app/Enums/Orders/DiscountType.php`, and
  `app/Enums/Orders/OrderPlace.php`
- [x] T009 [P] Implement the foundational Eloquent models, relationships,
  casts, query scopes, and factories in `app/Models/Order.php`,
  `app/Models/OrderNumberSequence.php`,
  `app/Models/OrderIdempotencyKey.php`, `app/Models/OrderItem.php`,
  `app/Models/OrderItemSelectedOption.php`,
  `app/Models/OrderItemSelectedOptionValue.php`,
  `app/Models/OrderItemAnswer.php`,
  `app/Models/OrderItemAttachment.php`, and
  `database/factories/*Order*.php`
- [x] T010 [P] Implement UTC daily sequence allocation, locking, four-digit
  exhaustion handling, and focused service tests in
  `app/Services/Orders/OrderNumberAllocator.php` and
  `tests/Unit/Services/Orders/OrderNumberAllocatorTest.php`
- [x] T011 [P] Implement deterministic Public/Admin customer resolution,
  Egyptian phone normalization, customer snapshot filling, and focused tests
  in `app/Services/Orders/CustomerOrderResolver.php` and
  `tests/Unit/Services/Orders/CustomerOrderResolverTest.php`
- [x] T012 [P] Implement immutable order, service, option, value, question,
  customer, and address snapshot construction with focused tests in
  `app/Services/Orders/OrderSnapshotFactory.php` and
  `tests/Unit/Services/Orders/OrderSnapshotFactoryTest.php`
- [x] T013 [P] Implement current pricing-option validation, unit/subtotal/
  discount/total calculation, payment-status derivation, remaining-amount
  calculation, and focused tests in
  `app/Services/Orders/OrderPricingService.php`,
  `app/Services/Orders/OrderPaymentSummaryService.php`,
  `tests/Unit/Services/Orders/OrderPricingServiceTest.php`, and
  `tests/Unit/Services/Orders/OrderPaymentSummaryServiceTest.php`
- [x] T014 [P] Implement the complete order-status transition matrix,
  cancellation requirements, available-transition projection, and focused
  tests in `app/Services/Orders/OrderStatusTransitionService.php` and
  `tests/Unit/Services/Orders/OrderStatusTransitionServiceTest.php`
- [x] T015 [P] Implement protected attachment storage, aggregate limits,
  newly-written-file compensation, post-commit deletion logging, and focused
  tests in `app/Services/Orders/OrderAttachmentStore.php` and
  `tests/Unit/Services/Orders/OrderAttachmentStoreTest.php`
- [x] T016 [P] Implement canonical request fingerprinting, transactional
  idempotency reservation/completion, replay/conflict resolution, and focused
  tests in `app/Services/Orders/PublicOrderIdempotencyService.php` and
  `tests/Unit/Services/Orders/PublicOrderIdempotencyServiceTest.php`
- [x] T017 [P] Register every Feature 005 permission and integrate it
  idempotently with the main permission and super-admin seeding flow in
  `database/seeders/OrdersPermissionsSeeder.php`,
  `database/seeders/RolesAndPermissionsSeeder.php`, and
  `database/seeders/DatabaseSeeder.php`
- [x] T018 [P] Add Arabic and English translations for order, payment,
  item, attachment, idempotency, and order-status business rules in
  `lang/ar/orders.php`, `lang/en/orders.php`,
  `lang/ar/order_attachments.php`, and `lang/en/order_attachments.php`
- [x] T019 Verify or create only the Admin/Public order route-group
  extension points, middleware order, prefixes, names, and controller inventory
  without creating empty controller classes or registering incomplete routes.
  Record the exact route-to-controller ownership map in
  `specs/005-orders-management/implementation-audit.md` and update only
  `routes/api/v1/admin.php` and `routes/api/v1/public.php` when a missing route
  group must be established; concrete routes and controllers remain owned by
  their User Story integration tasks. This task depends on T006 because both
  update `specs/005-orders-management/implementation-audit.md`
- [x] T020 Add database/model integrity tests covering schema types, enum
  casts, foreign-key behaviour, nullable historical links, uniqueness rules,
  address snapshot all-or-none invariants, and idempotency reservation
  constraints in `tests/Feature/Database/Orders/OrderSchemaTest.php`

**Foundational dependency note**: The focused service tasks for numbering,
customer resolution, snapshots, pricing/payment, status transitions,
attachments, and idempotency may run in parallel only after the schema, enums,
models, and factories are stable.

**Checkpoint**: Governing documents are synchronized, and the database,
domain types, models, permissions, translations, services, and route structure
are ready for story-level behaviour.

---

## Phase 3: User Story 1 — Guest Submits a Multi-Item Order Safely (Priority: P1) 🎯 MVP

**Goal**: Deliver public guest order creation with idempotency, customer
resolution, backend pricing authority, snapshot persistence, attachments, rate
limiting, and localized summary responses.

**Independent Test**: Submit multipart public orders that create new customers,
reuse matching customers, include valid and invalid selections, and verify
idempotent replay, localization, snapshots, totals, and failure rollback.

### Tests for User Story 1 (MANDATORY)

- [x] T021 [P] [US1] Add one consolidated public order API suite covering
  successful create, invalid service availability, invalid option/value
  selection, missing required answers, optional/required address validation,
  note limits, attachment limits, localized responses, and proof that no
  partial order persists on failure in
  `tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php`
- [x] T022 [P] [US1] Add one focused public idempotency and rate-limit suite
  covering first success, identical replay returning `200`, conflicting key
  reuse, per-minute throttling by IP plus normalized phone, and transaction
  rollback of incomplete reservations in
  `tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php`
- [x] T023 [P] [US1] Add one public route and contract suite covering exact
  route inventory, middleware, header requirements, shared envelope usage, and
  OpenAPI parity for `POST /api/v1/public/orders` in
  `tests/Architecture/PublicOrdersRouteContractTest.php`

### Implementation for User Story 1

- [x] T024 [P] [US1] Implement the public multipart create request validation,
  bracket-notation normalization, item/attachment limits, Egyptian phone
  normalization, and idempotency header validation in
  `app/Http/Requests/Api/V1/Public/Orders/CreatePublicOrderRequest.php`
- [x] T025 [P] [US1] Implement the public order summary resource and any nested
  public response transformers in
  `app/Http/Resources/Api/V1/Public/Orders/PublicOrderSummaryResource.php`
- [x] T026 [US1] Implement the transactional guest-order workflow covering
  idempotency reservation, customer resolution, optional address snapshot,
  current service/option/order-field validation, snapshot persistence,
  attachment storage/compensation, total calculation, and replay behaviour in
  `app/Actions/Orders/CreatePublicOrderAction.php`
- [x] T027 [US1] Integrate the public order controller, route middleware,
  shared `ApiResponse`, and `HttpStatusCode` enum-backed status handling in
  `app/Http/Controllers/Api/V1/Public/Orders/OrderController.php` and
  `routes/api/v1/public.php`
- [x] T028 [US1] Implement or update only the public order rate limiter,
  including the exact `5/min` key derived from client IP plus normalized
  Egyptian phone, and register any required service-container bindings in
  `bootstrap/app.php` and explicitly named support/provider files discovered by
  T001. Reuse the completed fingerprinting, reservation, replay, and conflict
  behavior from T016 without reopening
  `app/Services/Orders/PublicOrderIdempotencyService.php`

**Checkpoint**: Public order creation works independently and is ready as the
first MVP increment.

---

## Phase 4: User Story 2 — Admin Manages the Order Lifecycle and Money Summary (Priority: P1)

**Goal**: Deliver admin order create, list, show, update, status transitions,
payment summary management, and conditional hard delete.

**Independent Test**: Create orders for existing and new customers, edit
approved order-level fields, manage discounts, update paid amount, change
statuses through allowed and forbidden paths, and verify hard-delete rules.

### Tests for User Story 2 (MANDATORY)

- [x] T029 [P] [US2] Add one consolidated admin order API suite covering create
  for existing/new customers, show, patch, discount changes, order-place
  changes, address replacement/clearing, and conditional hard delete. Admin
  Create coverage MUST include initial nested attachment persistence, protected
  metadata, exact permission combinations with and without attachments,
  all-or-nothing rejection when `order-item-attachments.create` is missing,
  and filesystem/database rollback when any initial file fails in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php`
- [x] T030 [P] [US2] Add one focused admin status and payment suite covering
  valid transitions, invalid jumps, cancellation reason rules, paid-amount
  replacement, overpayment behaviour, and permission separation for
  `orders.change-status` and `orders.manage-payment` in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php`
- [x] T031 [P] [US2] Add one admin index/filter/sort contract suite covering
  allowed query parameters, pagination defaults and max, localized detail
  projection, route inventory, and permission mapping in
  `tests/Architecture/AdminOrdersRouteContractTest.php`

### Implementation for User Story 2

- [x] T032 [P] [US2] Implement admin create/update/status/payment/list request
  classes with distinct customer rules: Create MUST require exactly one of
  `customerId` or `customer`; Update MAY omit both when unchanged, MUST accept
  at most one when changing the customer, and MUST reject both together.
  Create/Update MUST reject `customerAddressId` with `address`, while Update
  MAY omit both when the address is unchanged. Include exact enum, discount,
  cancellation, and filter/sort validation in
  `app/Http/Requests/Api/V1/Admin/Orders/ListOrdersRequest.php`,
  `CreateAdminOrderRequest.php`, `UpdateOrderRequest.php`,
  `ChangeOrderStatusRequest.php`, and `UpdateOrderPaymentRequest.php`
- [x] T033 [P] [US2] Implement the complete admin order list/detail/payment
  resources, localized nested snapshot projection, stable English machine
  keys, safe attachment metadata, and `Content-Language` plus
  `Vary: Accept-Language` response behavior in
  `app/Http/Resources/Api/V1/Admin/Orders/AdminOrderIndexResource.php`,
  `AdminOrderResource.php`, and `AdminOrderPaymentResource.php`
- [x] T034 [P] [US2] Implement the complete admin index query with every
  approved Spatie Query Builder filter and sort, empty-result behavior,
  default `created_at DESC, id DESC` ordering, bounded pagination, required
  eager loading, and query-shape support for later EXPLAIN verification in
  `app/Queries/Orders/AdminOrderIndexQuery.php`
- [x] T035 [US2] Implement transactional admin create and order-level update
  workflows with customer/address resolution, snapshot replacement, discount
  recalculation, and status-in-patch delegation. `CreateAdminOrderAction` MUST
  persist initial nested item attachments, store protected attachment metadata,
  require `orders.create` plus `order-items.create`, additionally require
  `order-item-attachments.create` when any initial attachment exists, and use
  `OrderAttachmentStore` compensation so missing permission or any file failure
  leaves no customer, address, order, item, attachment row, or stored file.
  Implement in `app/Actions/Orders/CreateAdminOrderAction.php` and
  `app/Actions/Orders/UpdateOrderAction.php`
- [x] T036 [US2] Implement dedicated status transition, payment summary update,
  and eligible hard-delete workflows in
  `app/Actions/Orders/ChangeOrderStatusAction.php`,
  `app/Actions/Orders/UpdateOrderPaymentAction.php`, and
  `app/Actions/Orders/DeleteOrderAction.php`
- [x] T037 [US2] Implement the thin admin order controller and route mappings
  using shared `ApiResponse`, `HttpStatusCode`, and exact permission middleware
  in `app/Http/Controllers/Api/V1/Admin/Orders/OrderController.php`,
  `OrderStatusController.php`, `OrderPaymentController.php`, and
  `routes/api/v1/admin.php`

**Checkpoint**: Admin order lifecycle management works independently and can be
demoed without nested item changes.

---

## Phase 5: User Story 3 — Admin Manages Nested Order Items and Attachments (Priority: P1)

**Goal**: Deliver nested order-item create/show/update/delete plus attachment
upload/delete/download with strict ownership and editable-state rules.

**Independent Test**: Add items to editable orders, replace selected options
and answers, enforce last-item protection, upload/delete attachments while
editable, and download attachments in any order status.

### Tests for User Story 3 (MANDATORY)

- [x] T038 [P] [US3] Add one consolidated admin order-item suite covering item
  create, show, quantity-only update, full selected-options replacement, full
  answers replacement, service immutability, required-answer preservation, and
  final-item deletion guard. Prove Create/Add Item accepts
  `answers[].orderFieldId`, existing Item Update requires
  `answers[].orderItemAnswerId`, required stored answer snapshots cannot be
  omitted, and an unsnapshotted optional question cannot be introduced in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php`
- [x] T039 [P] [US3] Add one consolidated admin attachment suite covering upload
  while editable, delete while editable, protected download in any status,
  file validation, nested ownership, and hidden raw path rules in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php`
- [x] T040 [P] [US3] Add one focused nested-resource and item recalculation
  suite covering foreign item/attachment IDs, stale pricing selections, and
  item/order total recalculation. Prove a foreign
  `orderItemAnswerId` returns the non-disclosing nested `404`, and answer update
  still succeeds after the live Service Order Field is deleted and
  `service_order_field_id` becomes null, in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php`

### Implementation for User Story 3

- [x] T041 [P] [US3] Implement admin order-item and attachment request classes
  for create, update, upload, and nested ownership validation in
  `app/Http/Requests/Api/V1/Admin/Orders/CreateOrderItemRequest.php`,
  `UpdateOrderItemRequest.php`, and `UploadOrderItemAttachmentsRequest.php`
- [x] T042 [P] [US3] Implement the complete localized admin item and
  attachment resources with stable machine keys, historical answer snapshot
  IDs, safe metadata, hidden storage paths, and protected download endpoints in
  `app/Http/Resources/Api/V1/Admin/Orders/AdminOrderItemResource.php` and
  `AdminOrderAttachmentResource.php`
- [x] T043 [P] [US3] Implement the nested item list query and one explicit
  strict-ownership resolver for Order -> OrderItem -> Attachment lookups,
  returning non-disclosing `404` results for foreign nested IDs, in
  `app/Queries/Orders/OrderItemIndexQuery.php`,
  `app/Services/Orders/OrderNestedResourceResolver.php`, and
  `tests/Unit/Services/Orders/OrderNestedResourceResolverTest.php`
- [x] T044 [US3] Implement transactional add-item, update-item, and delete-item
  workflows with current service validation, snapshot replacement, answer
  replacement by `orderItemAnswerId`, total recalculation, and final-item
  protection in `app/Actions/Orders/AddOrderItemAction.php`,
  `UpdateOrderItemAction.php`, and `DeleteOrderItemAction.php`
- [x] T045 [US3] Implement attachment upload/delete/download workflows with
  editable-state enforcement, file-storage compensation, protected streaming,
  and strict nested ownership in
  `app/Actions/Orders/UploadOrderItemAttachmentsAction.php`,
  `DeleteOrderItemAttachmentAction.php`, and
  `app/Services/Orders/OrderNestedResourceResolver.php`
- [x] T046 [US3] Implement the thin nested admin item and attachment
  controllers plus exact nested routes and permission middleware in
  `app/Http/Controllers/Api/V1/Admin/Orders/OrderItemController.php`,
  `OrderItemAttachmentController.php`, and `routes/api/v1/admin.php`

**Checkpoint**: Nested item and attachment management works independently on top
of completed order lifecycle flows.

---

## Phase 6: User Story 4 — Admin Reviews Localized Searchable Order Data (Priority: P2)

**Goal**: Deliver production-ready searchable, filterable, sortable, localized
admin review flows with stable machine identifiers and non-disclosing nested
resource behaviour.

**Independent Test**: Create a varied set of orders and verify index filters,
sorts, pagination, localized detail projection, and foreign nested-resource
protection without requiring new mutations.

### Tests for User Story 4 (MANDATORY)

- [x] T047 [P] [US4] Extend the admin index suite to cover all approved filters,
  sort combinations, empty-result behaviour, and pagination boundaries in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderIndexQueryTest.php`
- [x] T048 [P] [US4] Add one localization/detail projection suite covering
  Arabic and English snapshot projection, header metadata, and stable English
  machine keys in `tests/Feature/Api/V1/Admin/Orders/AdminOrderLocalizationTest.php`
- [x] T049 [P] [US4] Add one non-disclosure suite covering foreign nested item
  IDs, foreign attachment IDs, and nested `404` behaviour across list/show/
  download flows in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderOwnershipTest.php`

### Implementation ownership for User Story 4

US4 does not create duplicate “finalize” tasks. Its implementation is delivered
by the existing concrete tasks that own those files:

- the Admin index query task owns every approved filter, sort, pagination rule,
  eager-loading decision, and search behavior;
- the Admin order resource task owns localized order detail projection plus
  `Content-Language` and `Vary: Accept-Language`;
- the Admin item/attachment resource task owns localized nested item projection
  and safe attachment metadata.

The US4 tasks below are independent acceptance tests that verify those completed
implementations without reopening or duplicating their scope.

**Checkpoint**: Admin review flows are fully operational, localized, and
bounded for daily use.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Complete documentation, verification, and cross-story hardening.

### Mandatory MySQL concurrency verification

- [x] T050 [P] Add a real-MySQL order-number concurrency suite proving unique
  UTC daily allocation, monotonic committed numbers, `9999` success, `10000`
  rejection with `ORDER_NUMBER_SEQUENCE_EXHAUSTED`, no wraparound, and no
  partial order in
  `tests/Concurrency/Orders/OrderNumberAllocationConcurrencyTest.php`
- [x] T051 [P] Add a real-MySQL idempotency concurrency suite proving that
  concurrent identical requests create one completed reservation and one
  order, replay returns `200`, conflicting payloads produce one order plus one
  `409 IDEMPOTENCY_KEY_REUSED`, failed creation leaves no reservation, and
  committed rows contain both `order_id` and `completed_at` in
  `tests/Concurrency/Orders/PublicOrderIdempotencyConcurrencyTest.php`
- [x] T052 [P] Add a real-MySQL final-item deletion race suite proving two
  concurrent deletions cannot commit an order with zero items and exactly one
  contender receives `ORDER_REQUIRES_AT_LEAST_ONE_ITEM` in
  `tests/Concurrency/Orders/OrderFinalItemDeletionConcurrencyTest.php`
- [x] T053 [P] Add a real-MySQL financial recalculation race suite covering
  concurrent payment, quantity, selected-option, discount, and status/item
  mutations and proving one internally consistent committed subtotal, total,
  payment status, paid amount, and remaining amount in
  `tests/Concurrency/Orders/OrderFinancialRecalculationConcurrencyTest.php`

- [x] T054 [P] Update
  `specs/005-orders-management/contracts/openapi.yaml` and the exact repository
  Postman collection/documentation path recorded by T001 for all 17 operations.
  Require unique `operationId` values, resolvable internal `$ref` values,
  request/response/header/path/query/multipart key descriptions, every integer
  enum meaning, permissions, valid and invalid status examples, payment and
  discount examples, idempotency replay/conflict examples, protected download,
  and applicable `401/403/404/409/422/429/500` examples
- [x] T055 Add query-count assertions for the Admin order index, order detail,
  item list, and protected attachment metadata reads; run `EXPLAIN` for the
  approved status, paymentStatus, orderPlace, customerId, serviceId, created
  range, total range, and search query shapes; record query plans, row estimates,
  selected indexes, and any corrective changes in
  `specs/005-orders-management/implementation-audit.md`; fix every detected
  N+1 issue and prove the corrected bounded query counts in
  `tests/Feature/Api/V1/Admin/Orders/AdminOrderQueryPerformanceTest.php`,
  `app/Queries/Orders/`, and
  `app/Http/Resources/Api/V1/Admin/Orders/`
- [x] T056 Produce a requirement-to-test traceability matrix in
  `specs/005-orders-management/implementation-audit.md` mapping every FR, AR,
  DI, API, LOC, and VR requirement to one or more concrete Pest test files;
  create only the specifically identified missing non-concurrency tests under
  `tests/Feature/Api/V1/Admin/Orders/`,
  `tests/Feature/Api/V1/Public/Orders/`, and
  `tests/Feature/Database/Orders/`. This task depends on T055 because both
  update `specs/005-orders-management/implementation-audit.md`
- [x] T057 Verify security, authorization, localization, file boundaries, and
  exact `HttpStatusCode::*` usage across
  `app/Actions/Orders/`, `app/Http/Controllers/Api/V1/Admin/Orders/`,
  `app/Http/Controllers/Api/V1/Public/Orders/`, and
  `app/Http/Resources/Api/V1/`
- [x] T058 Run the affected Pest suites against the dedicated MySQL test
  database and record the commands plus outcomes in
  `specs/005-orders-management/quickstart.md`
- [x] T059 Run `vendor/bin/pint --test`
- [x] T060 Run `vendor/bin/phpstan analyse`
- [x] T061 Run the end-to-end validation scenarios from
  `specs/005-orders-management/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
  and begins with governing-document synchronization before migrations or code
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - US1 is the MVP and should be completed first
  - US2 depends on the shared order foundation and can start after Phase 2
  - US3 depends on shared order foundation and the completed order lifecycle
    primitives from US2
  - US4 depends on shared order foundation and benefits from completed US2/US3
    read shapes
- **Polish (Phase 7)**: Depends on all desired user stories being complete
- **Concurrency suites in Phase 7**: Depend on the corresponding create,
  lifecycle, item, and payment implementations plus the dedicated MySQL test
  harness; they are mandatory before implementation completion
- **T056 traceability matrix**: Depends on T055 because both update
  `specs/005-orders-management/implementation-audit.md`

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - no dependency
  on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2), but should be
  completed after US1 to preserve MVP-first delivery
- **User Story 3 (P1)**: Depends on US2 order lifecycle primitives and the
  shared foundational order services
- **User Story 4 (P2)**: Depends on the shared foundational order queries and
  finalized read models from US2/US3

### Within Each User Story

- Required tests MUST be completed with their corresponding behaviour
- Migrations, enums, models, permissions, and reusable services before
  controller integration
- Form Requests and Resources before endpoints that invoke them
- Core workflow Actions before controllers and route integration
- Story complete before moving to the next priority when working sequentially

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel after the schema plan
  is stable; T019 is intentionally sequential after T006 because both update
  `implementation-audit.md`
- Within each story, tests, request classes, resources, and queries marked [P]
  can run in parallel when files do not overlap
- US2 and US4 documentation/query refinements can overlap late once US2/US3
  response contracts stabilize

---

## Parallel Example: User Story 1

```bash
# Launch independent public-order tests together:
Task: "Public create behavior in tests/Feature/Api/V1/Public/Orders/PublicOrderCreateTest.php"
Task: "Idempotency and rate limiting in tests/Feature/Api/V1/Public/Orders/PublicOrderIdempotencyTest.php"
Task: "Route contract in tests/Architecture/PublicOrdersRouteContractTest.php"

# Launch non-overlapping request/resource work together:
Task: "Create public request validation in app/Http/Requests/Api/V1/Public/Orders/CreatePublicOrderRequest.php"
Task: "Create public summary resource in app/Http/Resources/Api/V1/Public/Orders/PublicOrderSummaryResource.php"
```

---

## Parallel Example: User Story 2

```bash
# Launch admin-order verification together:
Task: "Admin order API coverage in tests/Feature/Api/V1/Admin/Orders/AdminOrderApiTest.php"
Task: "Status/payment coverage in tests/Feature/Api/V1/Admin/Orders/AdminOrderWorkflowTest.php"
Task: "Route and index contract coverage in tests/Architecture/AdminOrdersRouteContractTest.php"

# Launch non-overlapping request/resource/query work together:
Task: "Admin order request classes in app/Http/Requests/Api/V1/Admin/Orders/"
Task: "Admin order resources in app/Http/Resources/Api/V1/Admin/Orders/"
Task: "Admin order index query in app/Queries/Orders/AdminOrderIndexQuery.php"
```

---

## Parallel Example: User Story 3

```bash
# Launch nested item and attachment tests together:
Task: "Order item API suite in tests/Feature/Api/V1/Admin/Orders/AdminOrderItemApiTest.php"
Task: "Attachment API suite in tests/Feature/Api/V1/Admin/Orders/AdminOrderAttachmentApiTest.php"
Task: "Nested item rules suite in tests/Feature/Api/V1/Admin/Orders/AdminOrderItemRulesTest.php"

# Launch non-overlapping nested implementation together:
Task: "Order item request classes in app/Http/Requests/Api/V1/Admin/Orders/"
Task: "Order item resources in app/Http/Resources/Api/V1/Admin/Orders/"
Task: "Order item query/helpers in app/Queries/Orders/ and app/Services/Orders/"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Confirm public guest order creation works
   independently
5. Demo or ship the public-order MVP increment if desired

### Incremental Delivery

1. Complete Setup + Foundational → shared order foundation ready
2. Add User Story 1 → validate public guest ordering
3. Add User Story 2 → validate admin order lifecycle
4. Add User Story 3 → validate nested items and attachments
5. Add User Story 4 → validate admin review/search/localization refinements
6. Finish Polish and cross-cutting verification

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. After Phase 2:
   - Developer A: User Story 1
   - Developer B: User Story 2 request/resource/query groundwork
   - Developer C: Foundational test hardening and supporting services
3. After US2 stabilizes:
   - Developer B/C split User Story 3
   - Developer A or another teammate handles User Story 4 query/localization refinement

---

## Notes

- [P] tasks = different files, no unresolved dependencies
- [Story] labels map tasks directly to user stories for traceability
- Each user story is expected to be independently completable and testable
- Verify required tests cover the approved behaviour and pass on MySQL
- Use the existing `HttpStatusCode::*` enum values in application-controlled responses
- Avoid vague tasks, overlapping file edits in parallel, and hidden cross-story dependencies

## Feature 007 synchronized amendment

- [x] T062 Expose read-only `completedAt` on Admin Order index/show, reject it from every order mutation, and synchronize Feature 005 docs, OpenAPI, Postman, resource tests, and contract tests.
