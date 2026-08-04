# Tasks: Services Catalog

**Input**: Design documents from `/specs/004-services-catalog/`

**Approved source**: `docs/features/004-services-catalog.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`, `quickstart.md`

**Tests**: Tests are mandatory implementation work. Keep Feature 004 coverage
grouped into risk-based Pest suites covering the applicable success, validation,
authorization, localization, persistence, file, contract, and concurrency
behaviour required by the specification and constitution.

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
- Feature documentation: `specs/004-services-catalog/`

---

## Phase 1: Setup (Repository Verification and Feature-Specific Preparation)

**Purpose**: Verify governing constraints, preserve the worktree, and confirm
the exact baseline that Feature 004 must extend.

- [X] T001 Audit the existing service-related codebase, routes, models, enums,
  resources, tests, translations, Postman artifacts, and open documentation,
  then record
  `Reuse | Update | Replace | Delete-only-when-unreferenced | Create-only-when-missing`
  decisions in `specs/004-services-catalog/implementation-audit.md`
- [X] T002 Verify the installed PHP, Laravel, Sanctum, Spatie Permission,
  Spatie Query Builder, Pest, Pint, and Larastan/PHPStan versions in
  `composer.json`, `composer.lock`, `README.md`, and `phpunit.xml`
- [X] T003 [P] Confirm the current Admin/Public route mounting, middleware
  aliases, shared response helpers, API locale middleware, and existing
  `StatusCode` enum usage in `routes/api.php`, `routes/api/v1/admin.php`,
  `routes/api/v1/public.php`, `bootstrap/app.php`, and `app/Enums/`
- [X] T004 [P] Inspect the current category and subcategory deletion workflows,
  lock order, and tests in `app/Actions/Categories/`,
  `app/Models/Category.php`, and `tests/Concurrency/Categories/` before adding
  real service dependency checks
- [X] T005 [P] Confirm the dedicated MySQL testing configuration, transaction
  test utilities, and existing file-storage test patterns in `.env.example`,
  `phpunit.xml`, `tests/Pest.php`, `tests/Support/`, and
  `docs/02-standards/file-storage-standards.md`

**Checkpoint**: Governing rules, current repository patterns, and safe extension
points are understood before schema or implementation changes begin.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish the shared schema, enums, models, reusable domain
helpers, permission registration, and translations that block all user stories.

**⚠️ CRITICAL**: No user story implementation begins until this phase passes.

- [X] T006 Create the foundational schema for `services`,
  `service_slug_reservations`, `service_specifications`, `service_order_fields`,
  `service_pricing_options`, `service_pricing_option_values`, and
  `service_media` with the approved foreign keys, `ON DELETE` behaviour,
  nullable classification rules, precise decimals, soft deletes, cross-locale
  slug reservation constraints, query indexes, and race-supporting indexes in
  `database/migrations/*_create_services_table.php`,
  `database/migrations/*_create_service_slug_reservations_table.php`,
  `database/migrations/*_create_service_specifications_table.php`,
  `database/migrations/*_create_service_order_fields_table.php`,
  `database/migrations/*_create_service_pricing_options_table.php`,
  `database/migrations/*_create_service_pricing_option_values_table.php`, and
  `database/migrations/*_create_service_media_table.php`
- [X] T007 [P] Implement the exact integer-backed domain enums and mappings in
  `app/Enums/Services/ServicePriceType.php`,
  `app/Enums/Services/ServicePricingInputType.php`,
  `app/Enums/Services/ServicePricingOptionType.php`,
  `app/Enums/Services/ServiceOrderFieldType.php`, and
  `app/Enums/Services/ServiceMediaType.php`
- [X] T008 [P] Implement the foundational Eloquent models, relationships,
  casts, active/non-deleted scopes, parent-scoping helpers, and factories in
  `app/Models/Service.php`, `app/Models/ServiceSlugReservation.php`,
  `app/Models/ServiceSpecification.php`,
  `app/Models/ServiceOrderField.php`,
  `app/Models/ServicePricingOption.php`,
  `app/Models/ServicePricingOptionValue.php`,
  `app/Models/ServiceMedia.php`, and `database/factories/*Service*.php`
- [X] T009 Implement reusable slug normalization and reservation,
  classification validation, media storage/compensation, child-count guards,
  and service activation validation after T007 and T008 in
  `app/Services/Services/LocalizedServiceSlugService.php`,
  `app/Services/Services/ServiceSlugReservationService.php`,
  `app/Services/Services/ServiceClassificationValidator.php`,
  `app/Services/Services/ServiceChildLimitGuard.php`,
  `app/Services/Services/ServiceActivationValidator.php`, and
  `app/Services/Files/ServiceMediaStorageService.php`
- [X] T010 [P] Register every Feature 004 permission and integrate it
  idempotently with the main permission and super-admin seeding flow in
  `database/seeders/ServicesPermissionsSeeder.php`,
  `database/seeders/RolesAndPermissionsSeeder.php`, and
  `database/seeders/DatabaseSeeder.php`
- [X] T011 [P] Add Arabic and English translations for service operations,
  child resources, media validation, hierarchy conflicts, and stable
  business-rule errors in `lang/ar/services.php`, `lang/en/services.php`,
  `lang/ar/service_media.php`, and `lang/en/service_media.php`
- [X] T012 Add database/model integrity tests covering schema types, enum casts,
  foreign-key behaviour, indexes, cross-locale slug reservations, child
  ownership, active scopes, and child soft-delete visibility in
  `tests/Feature/Database/Services/ServiceSchemaTest.php`

**Checkpoint**: The database, domain types, models, permissions, translations,
and reusable guards are ready for story-level behaviour.

---

## Phase 3: User Story 1 — Admin Manages Core Services (Priority: P1) 🎯 Core MVP

**Goal**: Deliver core Admin CRUD, localized Admin listing, service
classification, slug stability, activation rules, soft delete, restore, and
service-aware hierarchy deletion protection.

**Independent Test**: Create services in each approved classification state,
list and show them through Admin APIs, update service-level fields, activate and
deactivate them, soft delete and restore them, and verify invalid hierarchy
assignments, slug stability, filters, sorts, and restore cleanup.

### Tests for User Story 1 (MANDATORY)

- [X] T013 [P] [US1] Add one consolidated Admin core service API suite covering
  create, list, show, update, delete, restore, all three classification states,
  invalid hierarchy assignments, activation validation, localized index
  projection, bilingual search, approved filters/sorts, fixed-price rules, slug
  stability, trashed modes, restore cleanup, and proof that service soft delete
  retains child records, media rows, and physical media files in
  `tests/Feature/Api/V1/Admin/Services/ServiceApiTest.php`
- [X] T014 [P] [US1] Add one focused Admin core route and authorization suite
  covering exact route inventory, numeric constraints, middleware order,
  `StatusCode` enum-backed responses, and
  `services.view|create|update|delete|restore` permission mapping in
  `tests/Architecture/ServicesAdminRouteContractTest.php`

### Implementation for User Story 1

- [X] T015 [P] [US1] Implement Admin service Form Requests for list, multipart
  store, update, and restore, including top-level service validation, approved
  filters/sorts, bilingual-pair rules, classification rules, integer enums,
  `basePrice > 0`, and nested-array validation shapes in
  `app/Http/Requests/Api/V1/Admin/Services/ListServicesRequest.php`,
  `StoreServiceRequest.php`, `UpdateServiceRequest.php`, and
  `RestoreServiceRequest.php`
- [X] T016 [P] [US1] Implement Admin service Resources for localized index rows
  and bilingual detail/create/update/restore responses in
  `app/Http/Resources/Api/V1/Admin/Services/ServiceIndexResource.php` and
  `app/Http/Resources/Api/V1/Admin/Services/ServiceResource.php`
- [X] T017 [P] [US1] Implement the Admin service index query with exactly the
  approved Spatie Query Builder filters and sorts, bilingual search, resolved
  locale projection, active classification shaping, pagination limits, and
  deterministic default ordering in
  `app/Queries/Services/AdminServiceIndexQuery.php`
- [X] T018 [US1] Implement the core transactional create, update, soft-delete,
  and restore workflows for service-level data, including root/subcategory lock
  order, automatic subcategory clearing on category change, cross-locale slug
  reservation synchronization, restore classification cleanup, and
  `isActive = false` on restore; service soft delete must retain all child
  records, media rows, and physical media files without cascading or cleanup in
  `app/Actions/Services/CreateServiceAction.php`,
  `app/Actions/Services/UpdateServiceAction.php`,
  `app/Actions/Services/DeleteServiceAction.php`, and
  `app/Actions/Services/RestoreServiceAction.php`
- [X] T019 [US1] Integrate `ServiceActivationValidator` into every service-level
  transition that can result in `isActive = true`; require complete bilingual
  service data, valid price type and positive base price, and—when existing
  start-from pricing options are present—at least one active non-deleted value
  for each non-deleted option in
  `app/Actions/Services/CreateServiceAction.php`,
  `app/Actions/Services/UpdateServiceAction.php`, and
  `app/Services/Services/ServiceActivationValidator.php`
- [X] T020 [US1] Implement the thin Admin service controller using the approved
  Form Requests, Actions, Query, Resources, shared response envelope, and
  existing `StatusCode` enum in
  `app/Http/Controllers/Api/V1/Admin/Services/ServiceController.php`
- [X] T021 [US1] Register the exact protected core service routes in
  `routes/api/v1/admin.php`, place static commands before numeric resource
  parameters, apply numeric constraints, and map the exact service permissions
- [X] T022 [US1] Extend Feature 003 category/subcategory deletion workflows to
  enforce `CATEGORY_HAS_SERVICES` for direct category assignments and
  `SUBCATEGORY_HAS_SERVICES` for subcategory assignments, ignore soft-deleted
  services, and preserve the approved lock order in
  `app/Actions/Categories/DeleteCategoryAction.php`,
  `app/Actions/Categories/DeleteSubcategoryAction.php`, and focused category
  dependency tests under `tests/Feature/Api/V1/Admin/Categories/`

**Checkpoint**: Core service administration and hierarchy dependency rules work
independently. This is the **Core MVP**, but not yet the complete approved
Create Service contract.

---

## Phase 4: User Story 2 — Admin Manages Components and Media Safely (Priority: P1)

**Goal**: Deliver specifications, order fields, pricing options with
transactional nested value mutations, media workflows, and the complete atomic
multipart Create Service contract.

**Independent Test**: Create a service with all approved nested arrays and
files, then manage each child resource through its independent Admin APIs while
verifying permissions, ownership, limits, soft-delete rules, activation
invariants, and filesystem safety.

### Tests for User Story 2 (MANDATORY)

- [X] T023 [P] [US2] Add one consolidated component API suite covering
  specification CRUD, order-field CRUD, pricing-option CRUD, value
  `actionStatus` mutations, separate permissions, nested ownership, limits,
  soft-delete visibility, no child trashed filters, no child restore routes,
  deterministic ordering, and the exact no-op rules:
  omitted `values` leaves values unchanged, `values: []` leaves values
  unchanged, and omitted or empty `actionStatus` performs no action in
  `tests/Feature/Api/V1/Admin/Services/ServiceComponentsApiTest.php`
- [X] T024 [P] [US2] Add one consolidated media API suite covering multipart
  upload, extension/MIME/size/count rules, multiple-main rejection,
  first-image main selection, bilingual alt-text updates, set-main, one-video
  enforcement, hard delete, filesystem deletion, and fallback main selection
  in `tests/Feature/Api/V1/Admin/Services/ServiceMediaApiTest.php`
- [X] T025 [P] [US2] Add one focused atomic nested-create suite covering
  bracket-notation multipart input, specifications, order fields, pricing
  options with values, media, conditional nested permissions, activation
  validation after nested persistence, database rollback, and uploaded-file
  compensation in
  `tests/Feature/Api/V1/Admin/Services/CreateServiceWithNestedDataTest.php`

### Implementation for User Story 2

- [X] T026 [P] [US2] Implement independent child and media Form Requests for
  specification CRUD, order-field CRUD, pricing-option CRUD and value actions,
  media upload, media alt-text update, and set-main in
  `app/Http/Requests/Api/V1/Admin/Services/`
- [X] T027 [P] [US2] Implement Admin child Resources for specifications, order
  fields, pricing options/values, and media while excluding internal
  `fieldType`, internal `optionType`, storage paths, and deleted rows in
  `app/Http/Resources/Api/V1/Admin/Services/ServiceSpecificationResource.php`,
  `ServiceOrderFieldResource.php`, `ServicePricingOptionResource.php`,
  `ServicePricingOptionValueResource.php`, and `ServiceMediaResource.php`
- [X] T028 [P] [US2] Implement unpaginated child index queries with strict
  parent scoping, active/non-deleted visibility, capped collection assumptions,
  `sort_order ASC, id ASC` component ordering, and approved media ordering in
  `app/Queries/Services/ServiceComponentsIndexQuery.php` and
  `app/Queries/Services/ServiceMediaIndexQuery.php`
- [X] T029 [P] [US2] Implement specification and order-field nested CRUD in
  thin controllers with Form Requests, strict route-parent ownership,
  soft-delete behaviour, child-count guards, deterministic resources, and no
  unnecessary per-operation Action classes in
  `app/Http/Controllers/Api/V1/Admin/Services/ServiceSpecificationController.php`
  and
  `app/Http/Controllers/Api/V1/Admin/Services/ServiceOrderFieldController.php`
- [X] T030 [P] [US2] Implement pricing-option create/update/delete workflows,
  including option/value limits, fixed-price prohibition, transactional
  `actionStatus` processing, duplicate/conflicting ID rejection, option/value
  soft deletion, strict parent ownership, and revalidation of active
  start-from services after create, update, disable, or delete operations;
  omitted `values` and `values: []` must leave existing values unchanged, while
  omitted or empty `actionStatus` must perform no action for that item in
  `app/Actions/Services/CreateServicePricingOptionAction.php`,
  `app/Actions/Services/UpdateServicePricingOptionAction.php`,
  `app/Actions/Services/DeleteServicePricingOptionAction.php`, and
  `app/Http/Controllers/Api/V1/Admin/Services/ServicePricingOptionController.php`
- [X] T031 [P] [US2] Implement media upload, alt-text update, set-main, and hard
  delete workflows with service-row locking, one-main-image and one-video
  invariants, first-image auto-main, oldest-image fallback, physical-file
  deletion, and rollback compensation in
  `app/Actions/Services/UploadServiceMediaAction.php`,
  `app/Actions/Services/SetServiceMainMediaAction.php`,
  `app/Actions/Services/DeleteServiceMediaAction.php`,
  `app/Http/Controllers/Api/V1/Admin/Services/ServiceMediaController.php`, and
  `app/Services/Files/ServiceMediaStorageService.php`
- [X] T032 [US2] Complete `StoreServiceRequest` and extend
  `CreateServiceAction` to process `specifications`, `orderFields`,
  `pricingOptions.values`, and `media` in one atomic multipart workflow;
  enforce `services.create` plus each conditional nested-resource permission,
  validate all limits and ownership before commit, run activation validation
  after nested records are staged, and compensate every newly uploaded file on
  rollback in `app/Http/Requests/Api/V1/Admin/Services/StoreServiceRequest.php`,
  `app/Actions/Services/CreateServiceAction.php`, and
  `app/Services/Files/ServiceMediaStorageService.php`
- [X] T033 [US2] Register the exact nested Admin routes and separate permissions
  for specifications, order fields, pricing options, and media in
  `routes/api/v1/admin.php`, including `service-media.set-main`, numeric nested
  constraints, static-route ordering, and no pricing-option-value endpoints

**Checkpoint**: The **contract-complete Admin MVP** is available after US1 and
US2, including the approved atomic Create Service request.

---

## Phase 5: User Story 3 — Public Visitors Browse Active Localized Services (Priority: P2)

**Goal**: Deliver the two approved Public service endpoints with localized
summary/detail projections, exact filter allow-lists, hidden inactive
classifications, and stable integer/boolean machine values.

**Independent Test**: Create active and inactive services across classification
states, then call the Public list and detail routes in Arabic and English using
every approved filter and verify output, fallback, headers, and validation.

### Tests for User Story 3 (MANDATORY)

- [X] T034 [P] [US3] Add one consolidated Public services API suite covering
  active/non-deleted visibility, unavailable-service visibility, localized
  list/detail, bilingual search, category/subcategory slug filters with
  alternate-locale fallback, inactive-classification empty filtering,
  `priceFrom`/`priceTo` validation, pagination limits, fixed internal order,
  absence of Public sort, and `Content-Language`/`Vary` headers in
  `tests/Feature/Api/V1/Public/Services/PublicServiceApiTest.php`

### Implementation for User Story 3

- [X] T035 [P] [US3] Implement Public localized Resources for list summaries and
  detail responses, preserving integer enums and booleans, hiding internal
  types and inactive classifications, applying SEO fallbacks, and exposing
  pricing `inputType` in detail in
  `app/Http/Resources/Api/V1/Public/Services/PublicServiceListItemResource.php`
  and `PublicServiceResource.php`
- [X] T036 [P] [US3] Implement Public list and detail queries using exact
  Spatie Query Builder filter allow-lists, bilingual search, current-locale
  slug lookup with alternate-locale fallback, base-price range validation,
  active/non-deleted visibility, hidden inactive classification shaping,
  approved eager loading, and deterministic `created_at DESC, id DESC` order in
  `app/Queries/Services/PublicServiceIndexQuery.php` and
  `app/Queries/Services/PublicServiceLookupQuery.php`
- [X] T037 [US3] Implement the thin Public service controller with shared
  envelopes, localized response headers, pagination metadata, and
  non-disclosing not-found behaviour in
  `app/Http/Controllers/Api/V1/Public/Services/ServiceController.php`
- [X] T038 [US3] Register only
  `GET /api/v1/public/services` and
  `GET /api/v1/public/services/{serviceSlug}` in
  `routes/api/v1/public.php`, ensure they remain unauthenticated and read-only,
  and confirm mounting through `routes/api.php`

**Checkpoint**: Public service browsing independently satisfies the approved
list/detail contract.

---

## Phase 6: User Story 4 — Critical Integrity and Concurrency (Priority: P2)

**Goal**: Prove and enforce race-safe hierarchy assignments, slug reservations,
main-image selection, and video uniqueness under real MySQL concurrency.

**Independent Test**: Run concurrent delete-versus-create/update/restore,
cross-locale slug claim, set-main, and video-upload requests and verify that no
invalid committed state survives.

### Tests for User Story 4 (MANDATORY)

- [X] T039 [P] [US4] Add one real-MySQL critical concurrency suite covering
  category delete versus direct service create, subcategory delete versus
  service create, category/subcategory delete versus service move, restore
  versus hierarchy deletion, cross-locale slug reservation races, competing
  set-main operations, and competing video uploads in
  `tests/Concurrency/Services/ServiceCriticalConcurrencyTest.php` and
  `tests/Support/ServiceConcurrencyRunner.php`

### Implementation for User Story 4

- [X] T040 [US4] Implement and apply an explicit hierarchy lock coordinator with
  the universal order
  `root category -> subcategory when applicable -> services by id ASC ->
  revalidate -> mutate -> commit` across service create/update/restore and
  category/subcategory delete workflows in
  `app/Services/Services/ServiceHierarchyLockCoordinator.php`,
  `app/Actions/Services/CreateServiceAction.php`,
  `app/Actions/Services/UpdateServiceAction.php`,
  `app/Actions/Services/RestoreServiceAction.php`,
  `app/Actions/Categories/DeleteCategoryAction.php`, and
  `app/Actions/Categories/DeleteSubcategoryAction.php`
- [X] T041 [US4] Implement deterministic cross-locale slug reservation locking,
  duplicate-key conflict translation, and bounded retry-safe coordination in
  `app/Services/Services/ServiceSlugReservationService.php`,
  `app/Services/Services/LocalizedServiceSlugService.php`, and the service
  create/update actions
- [X] T042 [US4] Implement service-parent-row locking and revalidation for
  set-main, main-image deletion fallback, and video upload uniqueness in
  `app/Actions/Services/UploadServiceMediaAction.php`,
  `app/Actions/Services/SetServiceMainMediaAction.php`, and
  `app/Actions/Services/DeleteServiceMediaAction.php`

**Checkpoint**: Every race-sensitive Feature 004 invariant holds under real
MySQL execution.

---

## Phase 7: Polish and Cross-Cutting Concerns

**Purpose**: Synchronize routes, API contracts, documentation, architecture
checks, and final repository quality gates.

- [X] T043 [P] Add one architecture boundary suite covering exactly 26 Admin
  and 2 Public operations, numeric route constraints, static-route order,
  middleware order, complete permission mapping, no pricing-option-value
  routes, no child restore routes, no Public writes, and no Public sort in
  `tests/Architecture/ServicesFeatureArchitectureTest.php`
- [X] T044 [P] Synchronize the implemented request/response schemas, security,
  response headers, errors, operation IDs, filters, and permissions with
  `specs/004-services-catalog/contracts/openapi.yaml`,
  `postman/Service-Commerce.postman_collection.json`, and the Feature 003
  category contract artifacts affected by `CATEGORY_HAS_SERVICES` and
  `SUBCATEGORY_HAS_SERVICES`; parse the OpenAPI YAML successfully, verify that
  every internal `$ref` resolves, and assert exactly 28 unique operation IDs
  matching the approved 26 Admin and 2 Public operations
- [X] T045 [P] Update implementation audit results, final operational notes,
  exact verification commands, and contract-complete MVP guidance in
  `specs/004-services-catalog/implementation-audit.md` and
  `specs/004-services-catalog/quickstart.md`
- [X] T046 Run the focused Feature 004 suites in
  `tests/Feature/Api/V1/Admin/Services/`,
  `tests/Feature/Api/V1/Public/Services/`,
  `tests/Feature/Database/Services/`,
  `tests/Concurrency/Services/`, and `tests/Architecture/`
- [X] T047 Run final repository quality gates using the repository-supported
  commands for `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, and
  `php artisan test`, plus the repository's OpenAPI validation command or test
  that parses YAML, resolves every internal `$ref`, and verifies 28 unique
  operation IDs; record failures accurately and do not weaken quality rules to
  obtain a pass

---

## Dependencies and Execution Order

### Phase Dependencies

- **Phase 1 — Setup**: No dependencies; runs first.
- **Phase 2 — Foundational**: Depends on Phase 1 and blocks every story.
- **Phase 3 — US1**: Depends on Phase 2.
- **Phase 4 — US2**: Depends on the service entity and core actions from US1.
- **Phase 5 — US3**: Depends on US1 and on the nested data/media delivered by
  US2 for complete detail responses.
- **Phase 6 — US4**: Depends on all write workflows from US1 and US2.
- **Phase 7 — Polish**: Depends on every selected story.

### User Story Dependencies

- **US1**: Starts after Foundational and delivers the Core MVP.
- **US2**: Builds on US1 and completes the approved Admin Create Service
  contract.
- **US3**: Builds on the persisted data and response structures from US1/US2.
- **US4**: Hardens the implemented write workflows and hierarchy integration.

### Within Each User Story

- Tests may be scaffolded before implementation but complete only when passing.
- Form Requests and Resources precede controllers that consume them.
- Query classes precede list/detail endpoints.
- Transactional Actions precede controller wiring.
- T032 runs after child validation/resources and pricing/media workflows exist.
- Service activation validation must run after all relevant mutations are
  staged and before transaction commit.
- Every nested resource ID must be scoped to every parent in the route.
- No executed migration may be edited after application.

### Parallel Opportunities

- T003–T005 can run in parallel.
- T007, T008, T010, and T011 can run in parallel after T006's schema direction
  is frozen; T009 follows the enums/models it consumes.
- T013–T017 can be scaffolded in parallel after Foundational.
- T023–T031 can be split across independent test, request, resource, query,
  component, pricing, and media workstreams; T032 integrates them afterward.
- T034–T036 can proceed in parallel after the Admin data contract is stable.
- T043–T045 can proceed in parallel after routes and response shapes stabilize.

---

## Parallel Example: User Story 1

```bash
Task: "Admin core service API suite in tests/Feature/Api/V1/Admin/Services/ServiceApiTest.php"
Task: "Admin core route contract suite in tests/Architecture/ServicesAdminRouteContractTest.php"
Task: "Core service Form Requests in app/Http/Requests/Api/V1/Admin/Services/"
Task: "Admin service Resources in app/Http/Resources/Api/V1/Admin/Services/"
Task: "AdminServiceIndexQuery in app/Queries/Services/AdminServiceIndexQuery.php"
```

## Parallel Example: User Story 2

```bash
Task: "Component API suite in tests/Feature/Api/V1/Admin/Services/ServiceComponentsApiTest.php"
Task: "Media API suite in tests/Feature/Api/V1/Admin/Services/ServiceMediaApiTest.php"
Task: "Nested create suite in tests/Feature/Api/V1/Admin/Services/CreateServiceWithNestedDataTest.php"
Task: "Child Form Requests in app/Http/Requests/Api/V1/Admin/Services/"
Task: "Child Resources in app/Http/Resources/Api/V1/Admin/Services/"
Task: "Pricing-option workflows in app/Actions/Services/"
Task: "Media workflows in app/Actions/Services/"
```

## Parallel Example: User Story 3

```bash
Task: "Public services API suite in tests/Feature/Api/V1/Public/Services/PublicServiceApiTest.php"
Task: "Public service Resources in app/Http/Resources/Api/V1/Public/Services/"
Task: "Public service Queries in app/Queries/Services/"
```

---

## Implementation Strategy

### Core MVP

1. Complete Setup.
2. Complete Foundational.
3. Complete US1.
4. Validate core service administration independently.

### Contract-Complete Admin MVP

1. Complete the Core MVP.
2. Complete US2.
3. Validate atomic nested service creation and all child/media APIs.
4. Treat **US1 + US2**, not US1 alone, as the complete approved Admin contract.

### Incremental Delivery

1. Setup + Foundational → service backbone.
2. US1 → core service administration.
3. US2 → complete Admin contract, nested configuration, and media.
4. US3 → Public localized browsing.
5. US4 → critical concurrency hardening.
6. Polish → contracts, documentation, and quality gates.

### Parallel Team Strategy

After Setup and Foundational:

- Developer A: US1 core services.
- Developer B: US2 component/media test and resource scaffolding, then
  integration after US1.
- Developer C: US3 Public read scaffolding after response contracts stabilize.
- Team: US4 concurrency and Phase 7 synchronization.

---

## Notes

- Total tasks: **49**
- User story task counts:
  - **US1**: 10
  - **US2**: 11
  - **US3**: 5
  - **US4**: 4
- Parallelizable tasks marked `[P]`: **28**
- Core MVP: **US1**
- Contract-complete Admin MVP: **US1 + US2**
- Every task follows the required checklist format and includes concrete file
  paths
- Pricing-option value no-op semantics, service soft-delete retention, and
  OpenAPI structural validation are explicit implementation and verification
  requirements
- The approved source remains `docs/features/004-services-catalog.md`

## Attachment requirement amendment

- [x] T048 Add the optional-by-default service attachment requirement flag to
  persistence, Admin create/update, Admin/Public resources, and service tests.
- [x] T049 Enforce required attachments for every new Public/Admin order item
  and synchronize Feature 004/005 contracts and Postman examples.
