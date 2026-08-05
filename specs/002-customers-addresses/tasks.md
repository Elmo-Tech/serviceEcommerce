# Tasks: Customers and Addresses

- [x] T066 Remove duplicated phone fields from customer-address persistence,
  requests, Resources, tests, OpenAPI, Postman, and governing documentation;
  preserve the customer's primary phone and historical order snapshots.

**Input**: Design documents from `/specs/002-customers-addresses/`

**Approved source**: `docs/features/002-customers-addresses.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`

**Tests**: Tests are mandatory, but they MUST remain consolidated into
approximately five risk-based groups. Do not create one task or test file per
route, permission, validation rule, locale, error code, or security assertion.
Feature 002 MUST NOT duplicate Feature 001 token-rotation, token-reuse,
browser-storage, CSP, cookie, CSRF, entropy, throttling, or authentication-log
security suites.

**Organization**: Tasks are grouped by user story to enable independent
implementation and testing while preserving the existing codebase and data.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel when files and dependencies do not overlap
- **[Story]**: User story mapping, for example `[US1]`
- Every task includes the main file paths it changes
- Existing compatible artifacts are reused or updated instead of recreated

## Path Conventions

- Laravel application code: `app/`
- Versioned API routes: `routes/api.php` and `routes/api/v1/`
- Database artifacts: `database/migrations/`, `database/factories/`, and
  `database/seeders/`
- API Feature Tests: `tests/Feature/Api/V1/`
- Focused domain tests: `tests/Feature/Domain/`
- Concurrency tests: `tests/Concurrency/`
- Architecture tests: `tests/Architecture/`
- Feature documentation: `specs/002-customers-addresses/`

---

## Phase 1: Existing-Code Audit and Setup

**Purpose**: Protect the current repository and database before implementation.

- [X] T001 Create a Git checkpoint, inspect the current MySQL schema and
  migration history, audit existing customer/address Models, migrations,
  routes, Controllers, Requests, Resources, Actions, Services, Queries,
  permissions, seeders, translations, tests, Postman/OpenAPI artifacts, and
  cross-module references, then record `Reuse | Update | Replace |
  Delete-only-when-unreferenced | Create-only-when-missing` decisions in
  `specs/002-customers-addresses/implementation-audit.md`
- [X] T002 Verify existing admin API, response-envelope, locale, middleware, and
  permission conventions in `routes/api.php`, `routes/api/v1/admin.php`,
  `routes/api/v1/auth.php`, `app/Support/Api/ApiResponse.php`, and
  `app/Http/Controllers/Api/V1/Admin/Auth/`
- [X] T003 Verify pinned PHP, Laravel, Sanctum, Spatie, Query Builder, Pest, and
  static-analysis versions in `composer.json`, `composer.lock`, `phpunit.xml`,
  and `README.md`
- [X] T004 [P] Add `giggsey/libphonenumber-for-php` only when it is not already
  available, updating `composer.json` and `composer.lock`
- [X] T005 [P] Confirm the dedicated MySQL test database and shared Pest
  bootstrap in `phpunit.xml`, `tests/Pest.php`, `.env.example`, and the local
  testing environment without adding SQLite coverage

**Checkpoint**: Existing artifacts and migration risks are understood before
schema or source-code changes begin.

---

## Phase 2: Foundational Domain Infrastructure

**Purpose**: Reconcile the existing repository with the approved target model.
This phase blocks all user stories.

- [X] T006 Reconcile the existing `customers` table and migration history:
  create a baseline migration only when the table is absent; otherwise create a
  forward-only, data-preserving alteration migration that backfills normalized
  email/phone values, resolves legacy conflicts before unique indexes, preserves
  external references, and never edits an already-executed migration in
  `database/migrations/`
- [X] T007 Reconcile the existing `customer_addresses` table and migration
  history: create a baseline migration only when the table is absent; otherwise
  create a forward-only, data-preserving alteration migration that backfills
  normalized phone/address identity, preserves existing rows and foreign keys,
  and adds only the approved indexes and soft-delete/default fields in
  `database/migrations/`
- [X] T008 [P] Reuse or update the guest customer entities, relationships,
  casts, scopes, fillable/guarded rules, and soft deletes in
  `app/Models/Customer.php` and `app/Models/CustomerAddress.php`
- [X] T009 [P] Reuse or update feature factories and deleted/default states in
  `database/factories/CustomerFactory.php` and
  `database/factories/CustomerAddressFactory.php`
- [X] T010 [P] Implement or reconcile backend-owned phone and address
  normalization in `app/Services/Customers/PhoneNumberService.php` and
  `app/Services/Customers/AddressNormalizationService.php`
- [X] T011 [P] Reconcile independent customer and address permissions without
  removing permissions owned by other features in
  `database/seeders/CustomerPermissionsSeeder.php`,
  `database/seeders/RolesAndPermissionsSeeder.php`, and
  `database/seeders/DatabaseSeeder.php`
- [X] T012 [P] Add or reconcile Arabic and English customer/address messages in
  `lang/en/customers.php`, `lang/ar/customers.php`,
  `lang/en/customer_addresses.php`, and `lang/ar/customer_addresses.php`
- [X] T013 Verify the existing admin route mount and middleware stack in
  `routes/api.php` and `routes/api/v1/admin.php`; add missing mounts only and do
  not duplicate route groups or Feature 001 middleware

**Checkpoint**: Schema, Models, normalization, permissions, localization, and
route mounting are safe and ready.

---

## Phase 3: User Story 1 - Admin Manages Canonical Customers (P1)

**Goal**: Protected customer CRUD, soft delete, restore, canonical phone/email
identity, safe Resources, filters, sorting, and pagination.

**Independent Test**: Exercise only customer administration routes and verify
canonical identity, lifecycle, response safety, representative authorization,
and localized errors.

### Consolidated Test Group 1

- [X] T014 [P] [US1] Add one consolidated customer API suite covering success,
  representative validation, normalized phone/email uniqueness, list
  filters/sorts/pagination, soft delete/restore, safe Resources, and one
  representative `401 UNAUTHENTICATED`, `403 USER_INACTIVE`, and
  `403 FORBIDDEN` boundary in
  `tests/Feature/Api/V1/Admin/Customers/CustomerApiTest.php`

### Implementation

- [X] T015 [P] [US1] Reuse or implement customer query and mutation Requests in
  `app/Http/Requests/Api/V1/Admin/Customers/ListCustomersRequest.php`,
  `StoreCustomerRequest.php`, and `UpdateCustomerRequest.php`, rejecting
  unsupported identity/authentication fields and requiring at least one mutable
  field for PATCH
- [X] T016 [P] [US1] Reuse or implement explicit customer list/detail Resources
  in `app/Http/Resources/Api/V1/Admin/Customers/CustomerListItemResource.php`
  and `CustomerDetailResource.php`, including `isDeleted`,
  `addressesCount`, and approved nested addresses while excluding
  `phoneNormalized`, token data, and internal fields
- [X] T017 [P] [US1] Implement the allow-listed customer index query with
  `filter[search]`, `filter[status]`, `filter[hasAddresses]`,
  `filter[createdFrom]`, `filter[createdTo]`, `sort`, `page`, and `perPage`
  defaults and limits in
  `app/Queries/Customers/CustomerIndexQuery.php`
- [X] T018 [US1] Implement canonical customer create/update workflows with
  backend email/phone normalization, duplicate-key recovery, and safe conflict
  codes in `app/Actions/Customers/CreateCustomerAction.php` and
  `app/Actions/Customers/UpdateCustomerAction.php`
- [X] T019 [US1] Implement transactional customer soft delete and restore,
  preserving existing data and restoring the customer without automatically
  restoring addresses in `app/Actions/Customers/DeleteCustomerAction.php` and
  `app/Actions/Customers/RestoreCustomerAction.php`
- [X] T020 [US1] Reuse or implement thin protected customer endpoints in
  `app/Http/Controllers/Api/V1/Admin/Customers/CustomerController.php` and any
  focused restore controller already required by repository conventions
- [X] T021 [US1] Register or reconcile the exact customer routes and independent
  permissions in `routes/api/v1/admin.php` without duplicating existing route
  names, prefixes, or middleware

**Checkpoint**: Customer management is independently functional.

---

## Phase 4: User Story 2 - Admin Manages Customer Addresses (P1)

**Goal**: Nested address CRUD, ownership protection, duplicate prevention,
restore, 20-active-address limit, and exactly one active default.

**Independent Test**: Exercise only nested address routes for an existing
customer and verify lifecycle, ownership, limit, duplicate, and default rules.

### Consolidated Test Group 2

- [X] T022 [P] [US2] Add one consolidated nested address API suite covering
  success, representative validation, ownership `404`, independent
  permissions, 20-address limit, duplicate identity, restore behavior, default
  switching, deletion fallback, Resource safety, and representative
  localization in
  `tests/Feature/Api/V1/Admin/Customers/CustomerAddressApiTest.php`

### Implementation

- [X] T023 [P] [US2] Reuse or implement nested address Requests in
  `app/Http/Requests/Api/V1/Admin/Customers/ListCustomerAddressesRequest.php`,
  `StoreCustomerAddressRequest.php`, and `UpdateCustomerAddressRequest.php`,
  including `filter[status]=active|deleted|all`, strict field allow-lists, and
  PATCH `minProperties` behavior
- [X] T024 [P] [US2] Reuse or implement explicit address Resources and
  collections in
  `app/Http/Resources/Api/V1/Admin/Customers/CustomerAddressResource.php` and
  `CustomerAddressCollection.php`, including deletion metadata and excluding
  `phoneNormalized`, `addressHash`, and foreign-owner disclosure
- [X] T025 [US2] Implement or reconcile `CustomerAddressService` in
  `app/Services/Customers/CustomerAddressService.php` using the shared lock
  order: lock the customer row first, then relevant address rows ordered by
  `id`, then enforce duplicate, active-limit, restore, and default invariants
- [X] T026 [US2] Implement address create/update/delete/restore/set-default
  Actions in `app/Actions/CustomerAddresses/`, preserving the deterministic
  fallback order `created_at DESC, id DESC` and using
  `PUT /admin/customers/{customer}/addresses/{address}/default`
- [X] T027 [US2] Reuse or implement thin nested address Controllers in
  `app/Http/Controllers/Api/V1/Admin/Customers/CustomerAddressController.php`
  and focused restore/default controllers required by repository conventions
- [X] T028 [US2] Register or reconcile the exact nested address routes and
  independent permissions in `routes/api/v1/admin.php`, including the dedicated
  set-default permission and customer-scoped address lookup

**Checkpoint**: Address management is independently functional.

---

## Phase 5: User Story 3 - Guest Resolution Contracts (P2)

**Goal**: Reusable customer/address resolution for future guest orders without
overwriting saved identity details.

**Independent Test**: Exercise internal resolution workflows without requiring
the Orders feature.

### Consolidated Test Group 3

- [X] T029 [P] [US3] Add focused customer and address matching coverage for
  reuse, restore, create, non-overwrite behavior, active-limit handling, and
  snapshot-ready submitted values in
  `tests/Feature/Domain/Customers/CustomerMatchingTest.php` and
  `tests/Feature/Domain/Customers/CustomerAddressMatchingTest.php`

### Implementation

- [X] T030 [US3] Implement reusable customer matching and restore-safe
  resolution with unique-key race recovery in
  `app/Services/Customers/CustomerMatchingService.php` and
  `app/Actions/Customers/ResolveGuestCustomerAction.php`
- [X] T031 [US3] Implement reusable address matching and restore-safe resolution
  through the shared deterministic lock order in
  `app/Actions/CustomerAddresses/ResolveGuestCustomerAddressAction.php` and
  `app/Services/Customers/CustomerAddressService.php`
- [X] T032 [US3] Reconcile the internal customer/address resolution return
  contract and Model relationships in `app/Models/Customer.php`,
  `app/Models/CustomerAddress.php`, and the matching service/action files
  without introducing public guest CRUD or authentication routes

**Checkpoint**: Future Orders can consume the resolution contracts safely.

---

## Phase 6: Critical Concurrency Verification

**Purpose**: Verify only the three approved MySQL race-sensitive invariants.

### Consolidated Test Group 4

- [X] T033 Add one real-MySQL concurrency suite covering same-phone customer
  creation resolving to one customer, concurrent default-address changes
  leaving exactly one active default, and concurrent duplicate-address
  resolution leaving one active canonical address in
  `tests/Concurrency/Customers/CustomerCriticalConcurrencyTest.php`

**Checkpoint**: Critical database invariants hold under concurrency.

---

## Phase 7: Documentation, Architecture, and Quality

### Consolidated Test Group 5

- [X] T034 [P] Add one minimal architecture/localization/contract suite that
  verifies exact routes, middleware order, independent permissions, no public
  customer auth/CRUD, safe Resource fields, one Arabic and one English response,
  and no Feature 002 token input/output in
  `tests/Architecture/CustomerFeatureArchitectureTest.php`

### Documentation and Completion

- [X] T035 [P] Update the admin customer/address Postman collection and local
  environment examples in `postman/Service-Commerce.postman_collection.json`
  and `postman/Service-Commerce.local.postman_environment.json.example`
- [X] T036 Verify implementation against
  `specs/002-customers-addresses/contracts/openapi.yaml` and
  `specs/002-customers-addresses/quickstart.md`, including all 13 operations,
  unique `operationId` values, strict `additionalProperties: false`, PATCH
  `minProperties: 1`, separate `CustomerListItem`/`CustomerDetail` schemas,
  query defaults, address `status` filter, `USER_INACTIVE`/`FORBIDDEN`
  examples, and customer restore uniqueness conflicts
- [X] T037 Run focused Pest suites for
  `tests/Feature/Api/V1/Admin/Customers/`,
  `tests/Feature/Domain/Customers/`,
  `tests/Concurrency/Customers/`, and
  `tests/Architecture/CustomerFeatureArchitectureTest.php`
- [X] T038 Run final repository quality gates with
  `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, and
  `php artisan test`, then update
  `specs/002-customers-addresses/implementation-audit.md` with the final
  create/update/reuse decisions and migration evidence

---

## Dependencies and Execution Order

### Phase Dependencies

- Phase 1 has no dependency and must run first.
- Phase 2 depends on the audit and blocks all user stories.
- US1 and US2 depend on Phase 2.
- US3 depends on the customer/address primitives delivered by US1 and US2.
- Concurrency verification depends on the matching and address workflows.
- Documentation and quality depend on all selected user stories.

### Within Each Story

- Consolidated tests must be implemented with their corresponding behavior.
- Requests and Resources precede controller wiring.
- Actions and Services precede endpoints that invoke them.
- Existing compatible code is updated rather than duplicated.
- No executed migration may be edited.
- No obsolete class is deleted until repository references are checked.
- The customer row is always locked before relevant address rows ordered by
  `id`.

### Parallel Opportunities

- T004 and T005 can run in parallel.
- T008 through T012 can run in parallel after schema decisions are recorded.
- T015 through T017 can run in parallel.
- T023 and T024 can run in parallel.
- T029 may begin while US3 implementation interfaces are being finalized.
- T034 and T035 can run in parallel after the route/resource contract is stable.

---

## Implementation Strategy

### Controlled Existing-Code Reimplementation

1. Complete the checkpoint and full audit.
2. Classify each existing artifact.
3. Reuse compatible Models, routes, middleware, Resources, and services.
4. Use forward-only migrations for existing tables.
5. Replace conflicting behavior while preserving approved public contracts.
6. Delete obsolete code only after reference checks and passing regression
   coverage.
7. Create files only when no compatible artifact exists.

### Incremental Delivery

1. Audit + foundation
2. Customer CRUD and lifecycle
3. Address CRUD and invariants
4. Guest matching contracts
5. Critical concurrency verification
6. Documentation and final quality gates

---

## Notes

- Total tasks: 38
- Consolidated test groups: 5
- Feature 001 owns authentication and token-security internals
- Feature 002 only verifies representative authentication boundaries
- Do not add customer authentication, public customer CRUD, background jobs,
  parallel tables, duplicate routes, or speculative abstractions
- The approved source remains
  `docs/features/002-customers-addresses.md`
