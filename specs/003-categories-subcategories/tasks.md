# Tasks: Categories and Subcategories

**Input**: Design documents from `/specs/003-categories-subcategories/`

**Approved source**: `docs/features/003-categories-subcategories.md`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories),
`research.md`, `data-model.md`, `contracts/openapi.yaml`, `quickstart.md`

**Tests**: Tests are mandatory implementation work. Keep Feature 003 coverage
grouped into risk-based suites instead of creating one task or one test file
per route, locale, permission, validation rule, or error code.

**Organization**: Tasks are grouped by user story so each story can be
implemented and validated independently while preserving the repository’s
existing Laravel conventions.

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
- Database and model verification: `tests/Feature/Database/`
- Concurrency tests: `tests/Concurrency/`
- Architecture tests: `tests/Architecture/`
- Feature documentation: `specs/003-categories-subcategories/`

---

## Phase 1: Existing-Code Audit and Setup

**Purpose**: Protect the current repository and database before implementation.

- [X] T001 Audit existing category/catalog schema, routes, Models, Controllers,
  Requests, Resources, seeders, translations, tests, and docs, then record
  `Reuse | Update | Replace | Delete-only-when-unreferenced |
  Create-only-when-missing` decisions in
  `specs/003-categories-subcategories/implementation-audit.md`
- [X] T002 Verify existing admin/public route mounting, locale resolution,
  response-envelope, and middleware conventions in `routes/api.php`,
  `routes/api/v1/admin.php`, `bootstrap/app.php`, and
  `app/Support/Api/ApiResponse.php`
- [X] T003 Verify pinned PHP, Laravel, Sanctum, Spatie Permission, Spatie Query
  Builder, Pest, and static-analysis versions in `composer.json`,
  `composer.lock`, `phpunit.xml`, and `README.md`
- [X] T004 [P] Confirm the dedicated MySQL test database and shared Pest
  bootstrap in `phpunit.xml`, `tests/Pest.php`, `.env.example`, and the local
  testing environment without adding SQLite coverage
- [X] T005 [P] Inspect the existing feature-owned permission seeding flow in
  `database/seeders/RolesAndPermissionsSeeder.php` and
  `database/seeders/DatabaseSeeder.php` before adding Feature 003 permissions

**Checkpoint**: Existing artifacts, route constraints, and migration risks are
understood before schema or source-code changes begin.

---

## Phase 2: Foundational Domain Infrastructure

**Purpose**: Establish the shared catalog foundation that blocks all user
stories.

- [X] T006 Create or reconcile the self-referencing `categories` migration with
  `parent_id`, localized fields, `sort_order`, `is_active`, soft deletes,
  unique localized slugs, and the required indexes in
  `database/migrations/*_create_categories_table.php`
- [X] T007 [P] Implement the shared catalog entity, relationships, scopes,
  predicates, casts, and deleted/root/subcategory factory states in
  `app/Models/Category.php` and `database/factories/CategoryFactory.php`
- [X] T008 [P] Implement shared localized slug normalization and create-only
  auto-generation in `app/Services/Categories/LocalizedSlugService.php`
- [X] T009 [P] Add Feature 003 permission registration and seed integration in
  `database/seeders/CategoriesPermissionsSeeder.php`,
  `database/seeders/RolesAndPermissionsSeeder.php`, and
  `database/seeders/DatabaseSeeder.php`
- [X] T010 [P] Add Arabic and English catalog messages and validation text in
  `lang/ar/categories.php` and `lang/en/categories.php`
- [X] T011 [P] Replace the public route placeholder with a concrete public route
  include in `routes/api.php` and add the new route file
  `routes/api/v1/public.php`
- [X] T012 Add schema/model verification for the self-reference, localized slug
  uniqueness, soft deletes, and root/subcategory scopes in
  `tests/Feature/Database/Categories/CategorySchemaTest.php`

**Checkpoint**: Schema, model, permissions, localization, and route mounting
are ready for story-level behavior.

---

## Phase 3: User Story 1 - Admin manages root Categories (Priority: P1) 🎯 MVP

**Goal**: Protected root Category CRUD, lifecycle, localized Admin index
projection, allow-listed Query Builder filtering/sorting, and atomic reorder.

**Independent Test**: Exercise only root Category admin routes and verify
localized create/list/show/update/delete/restore/reorder behavior, slug
stability, permissions, and the shared `200` delete envelope.

### Tests for User Story 1 (MANDATORY)

- [X] T013 [P] [US1] Add one consolidated root Category admin API suite covering
  success, validation, permissions, localized index projection,
  `filter[search]`, `filter[isActive]`, `filter[trashed]`,
  `sort=sortOrder|-sortOrder|name|-name|createdAt|-createdAt|updatedAt|-updatedAt`,
  bilingual search regardless of response locale, slug stability,
  dependency-blocked delete, restore conflicts, and `200` delete envelopes in
  `tests/Feature/Api/V1/Admin/Categories/CategoryApiTest.php`

### Implementation for User Story 1

- [X] T014 [P] [US1] Implement root Category query and mutation Requests in
  `app/Http/Requests/Api/V1/Admin/Categories/ListCategoriesRequest.php`,
  `StoreCategoryRequest.php`, `UpdateCategoryRequest.php`, and
  `ReorderCategoriesRequest.php`
- [X] T015 [P] [US1] Implement localized Admin root Category Resources for index
  and bilingual detail/mutation responses in
  `app/Http/Resources/Api/V1/Admin/Categories/CategoryIndexResource.php` and
  `app/Http/Resources/Api/V1/Admin/Categories/CategoryResource.php`
- [X] T016 [P] [US1] Implement the root Category admin list query with
  `spatie/laravel-query-builder`, allowing only `filter[search]`,
  `filter[isActive]`, `filter[trashed]`, and
  `sort=sortOrder|-sortOrder|name|-name|createdAt|-createdAt|updatedAt|-updatedAt`;
  make `filter[search]` search Arabic and English names, descriptions, and slugs
  regardless of response locale; map `sort=name` to the resolved locale column;
  and include approved counts in
  `app/Queries/Categories/AdminCategoryIndexQuery.php`
- [X] T017 [US1] Implement root Category create and update workflows with
  description-pair normalization and explicit slug-edit behavior in
  `app/Actions/Categories/CreateCategoryAction.php` and
  `app/Actions/Categories/UpdateCategoryAction.php`
- [X] T018 [US1] Implement root Category delete, restore, and reorder workflows
  with transaction boundaries, dependency rechecks, and deterministic locking in
  `app/Actions/Categories/DeleteCategoryAction.php`,
  `app/Actions/Categories/RestoreCategoryAction.php`, and
  `app/Actions/Categories/ReorderCategoriesAction.php`
- [X] T019 [US1] Implement the thin root Category admin endpoint layer in
  `app/Http/Controllers/Api/V1/Admin/Categories/CategoryController.php`
- [X] T020 [US1] Register the exact protected root Category routes in
  `routes/api/v1/admin.php` using the middleware order
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission -> endpoint`; constrain `{category}` to numeric root Category IDs;
  register `PATCH /categories/reorder` before any
  `/categories/{category}` route; and attach the exact independent
  `categories.view|create|update|delete|restore|reorder` permissions

**Checkpoint**: Root Category administration is independently functional and
ready to serve as the catalog foundation.

---

## Phase 4: User Story 2 - Admin manages nested Subcategories safely (Priority: P1)

**Goal**: Protected nested Subcategory CRUD, strict parent ownership, no
third-level hierarchy, parent-aware restore/delete behavior, and scoped reorder.

**Independent Test**: Exercise only nested Subcategory routes for an existing
root Category and verify scope enforcement, restore conflicts, parent
immutability, and scoped reorder behavior.

### Tests for User Story 2 (MANDATORY)

- [X] T021 [P] [US2] Add one consolidated nested Subcategory admin API suite
  covering success, validation, parent ownership `404`, third-level rejection,
  localized index projection, `filter[search]`, `filter[isActive]`,
  `filter[trashed]`,
  `sort=sortOrder|-sortOrder|name|-name|createdAt|-createdAt|updatedAt|-updatedAt`,
  bilingual search regardless of response locale, restore under
  inactive/deleted parent, and scoped reorder behavior in
  `tests/Feature/Api/V1/Admin/Categories/SubcategoryApiTest.php`; also verify
  that Feature 003 creates no Service table, model, relationship, fake
  `servicesCount`, placeholder dependency checker, or executable
  `SUBCATEGORY_HAS_SERVICES` query

### Implementation for User Story 2

- [X] T022 [P] [US2] Implement nested Subcategory Requests in
  `app/Http/Requests/Api/V1/Admin/Categories/ListSubcategoriesRequest.php`,
  `StoreSubcategoryRequest.php`, `UpdateSubcategoryRequest.php`, and
  `ReorderSubcategoriesRequest.php`
- [X] T023 [P] [US2] Implement localized Admin Subcategory Resources for index
  and bilingual detail/mutation responses in
  `app/Http/Resources/Api/V1/Admin/Categories/SubcategoryIndexResource.php` and
  `app/Http/Resources/Api/V1/Admin/Categories/SubcategoryResource.php`
- [X] T024 [P] [US2] Implement the scoped Subcategory admin list query with
  `spatie/laravel-query-builder`, allowing only `filter[search]`,
  `filter[isActive]`, `filter[trashed]`, and
  `sort=sortOrder|-sortOrder|name|-name|createdAt|-createdAt|updatedAt|-updatedAt`;
  make `filter[search]` search both stored languages; map `sort=name` to the
  resolved locale column; and enforce strict route-parent scoping in
  `app/Queries/Categories/AdminSubcategoryIndexQuery.php`
- [X] T025 [US2] Implement nested Subcategory create and update workflows that
  reject `parentId`/`categoryId`, enforce root-parent ownership, and preserve
  explicit slug rules in `app/Actions/Categories/CreateSubcategoryAction.php`
  and `app/Actions/Categories/UpdateSubcategoryAction.php`
- [X] T026 [US2] Implement nested Subcategory delete, restore, and reorder
  workflows with parent-first locking, deleted-parent conflicts, strict nested
  ownership, and scoped reorder in
  `app/Actions/Categories/DeleteSubcategoryAction.php`,
  `app/Actions/Categories/RestoreSubcategoryAction.php`, and
  `app/Actions/Categories/ReorderSubcategoriesAction.php`; do not create a
  Service model, table, migration, relationship, fake `servicesCount`,
  placeholder dependency checker, or runtime `SUBCATEGORY_HAS_SERVICES` query
  in Feature 003—Feature 004 owns the real Service dependency check
- [X] T027 [US2] Implement the thin nested Subcategory admin endpoint layer in
  `app/Http/Controllers/Api/V1/Admin/Categories/SubcategoryController.php`
- [X] T028 [US2] Register the exact protected nested Subcategory routes in
  `routes/api/v1/admin.php` using the middleware order
  `auth:sanctum -> EnsureUserIsAdministrator -> EnsureAdminIsActive ->
  permission -> endpoint`; constrain `{category}` and `{subcategory}` to
  numeric IDs; register
  `PATCH /categories/{category}/subcategories/reorder` before any
  `/categories/{category}/subcategories/{subcategory}` route; and attach the
  exact independent
  `subcategories.view|create|update|delete|restore|reorder` permissions

**Checkpoint**: Nested Subcategory management is independently functional and
structurally safe.

---

## Phase 5: User Story 3 - Public visitors browse only active localized catalog nodes (Priority: P2)

**Goal**: Public localized category/subcategory reads, ancestor visibility
inheritance, wrong-locale slug non-disclosure, and locale-switch metadata.

**Independent Test**: Exercise only public `/api/v1/public/categories*` routes
and verify localized content, hidden-record `404`, headers, deterministic
ordering, and `meta.localeLinks`.

### Tests for User Story 3 (MANDATORY)

- [X] T029 [P] [US3] Add one consolidated public catalog API suite covering
  Arabic/English list/detail responses, wrong-locale slug `404`, ancestor
  visibility inheritance, `Content-Language`, `Vary: Accept-Language`, and
  `meta.localeLinks` in
  `tests/Feature/Api/V1/Public/Categories/PublicCategoryApiTest.php`

### Implementation for User Story 3

- [X] T030 [P] [US3] Implement resolved-locale public Category and Subcategory
  Resources in
  `app/Http/Resources/Api/V1/Public/Categories/PublicCategoryResource.php` and
  `app/Http/Resources/Api/V1/Public/Categories/PublicSubcategoryResource.php`
- [X] T031 [P] [US3] Implement locale-aware public catalog queries, ancestor
  visibility filtering, localized slug lookup, and alternate locale link
  generation in `app/Queries/Categories/PublicCategoryCatalogQuery.php`
- [X] T032 [US3] Implement the public catalog endpoint layer in
  `app/Http/Controllers/Api/V1/Public/CategoryController.php`
- [X] T033 [US3] Register the exact public read-only catalog routes in
  `routes/api/v1/public.php` and confirm they stay mounted under `routes/api.php`

**Checkpoint**: Public catalog browsing is independently functional and respects
localization and visibility rules.

---

## Phase 6: Critical Concurrency Verification

**Purpose**: Verify the approved MySQL race-sensitive hierarchy invariants.

- [X] T034 Add one real-MySQL concurrency suite in
  `tests/Concurrency/Categories/CategoryCriticalConcurrencyTest.php` covering:
  root Category delete vs Subcategory create; parent delete vs Subcategory
  restore; overlapping root reorders; two Subcategory reorders under the same
  parent; and a valid scoped reorder racing with a cross-parent reorder;
  assert the universal lock order `root/parent first -> children by id ASC ->
  revalidate -> mutate -> commit`, no partial order updates, and no
  non-deleted child beneath a deleted root

**Checkpoint**: Hierarchy-sensitive writes hold under concurrency.

---

## Phase 7: Documentation, Architecture, and Quality

**Purpose**: Synchronize contracts, validate boundaries, and run completion
checks across all stories.

- [X] T035 [P] Add one architecture and route-boundary suite in
  `tests/Architecture/CategoriesFeatureArchitectureTest.php` verifying the
  exact `7 Admin Category + 7 Admin Subcategory + 4 Public = 18` operations,
  route ordering for both `reorder` endpoints, numeric route constraints,
  middleware order, no force-delete routes, no third-level routes, no public
  write routes, no separate media-management routes, and no Feature 003 Service persistence
- [X] T036 [P] Synchronize the implemented contract with
  `specs/003-categories-subcategories/contracts/openapi.yaml` and
  `postman/Service-Commerce.postman_collection.json`, verifying exactly 18
  operations with 18 unique `operationId` values, localized Admin Category and
  Subcategory index projections, public `meta.localeLinks`,
  `filter[search]`, `filter[isActive]`, `filter[trashed]`, all allow-listed
  `sort` values, and `200` soft-delete envelopes
- [X] T037 Verify final implementation notes, audit outcomes, and validation
  steps in `specs/003-categories-subcategories/quickstart.md` and
  `specs/003-categories-subcategories/implementation-audit.md`
- [X] T038 Run the focused Feature 003 suites in
  `tests/Feature/Api/V1/Admin/Categories/`,
  `tests/Feature/Api/V1/Public/Categories/`,
  `tests/Feature/Database/Categories/`,
  `tests/Concurrency/Categories/`, and
  `tests/Architecture/CategoriesFeatureArchitectureTest.php`
- [X] T039 Run final repository quality gates with `vendor/bin/pint --test`,
  `vendor/bin/phpstan analyse`, and `php artisan test`

---

## Dependencies and Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies and must run first.
- **Phase 2 (Foundational)**: Depends on Phase 1 and blocks all story work.
- **Phase 3 (US1)**: Depends on Phase 2.
- **Phase 4 (US2)**: Depends on Phase 2 and reuses the root-category foundation
  from US1.
- **Phase 5 (US3)**: Depends on Phase 2 and on the shared catalog model, route,
  and resource behavior delivered by US1 and US2.
- **Phase 6 (Concurrency)**: Depends on US1 and US2 write workflows.
- **Phase 7 (Polish)**: Depends on all selected story work.

### User Story Dependencies

- **US1**: Can start after Foundational phase completion and is the MVP slice.
- **US2**: Can start after Foundational phase completion, but practically builds
  on the root-category primitives delivered in US1.
- **US3**: Builds on the shared catalog persistence and benefits from the
  admin-facing hierarchy behavior established in US1 and US2.

### Within Each Story

- Consolidated tests must be implemented with their corresponding behavior.
- Requests and Resources precede controller wiring.
- Actions and Queries precede endpoints that invoke them.
- The `Category` root row is always locked before relevant child rows ordered by
  `id ASC`.
- Reorder routes are registered before dynamic model-binding routes, and route
  IDs are numerically constrained.
- Admin index filters use `filter[search]`, `filter[isActive]`, and
  `filter[trashed]`; sorting uses the allow-listed `sort` parameter.
- No executed migration may be edited.
- Feature 003 must not create any Service persistence, relationship, fake count,
  placeholder dependency checker, or runtime `SUBCATEGORY_HAS_SERVICES` query.

### Parallel Opportunities

- T004 and T005 can run in parallel.
- T007 through T011 can run in parallel after the migration direction is clear.
- T014 through T016 can run in parallel.
- T022 through T024 can run in parallel.
- T030 and T031 can run in parallel.
- T035 and T036 can run in parallel after the route and response contracts are stable.

---

## Parallel Example: User Story 1

```bash
# Launch the root Category test suite while API building starts:
Task: "Consolidated root Category admin API suite in tests/Feature/Api/V1/Admin/Categories/CategoryApiTest.php"

# Launch independent request/resource/query work together:
Task: "List/store/update/reorder Requests in app/Http/Requests/Api/V1/Admin/Categories/"
Task: "Category index/detail Resources in app/Http/Resources/Api/V1/Admin/Categories/"
Task: "AdminCategoryIndexQuery in app/Queries/Categories/AdminCategoryIndexQuery.php"
```

## Parallel Example: User Story 2

```bash
# Launch the nested Subcategory suite while scoped plumbing is prepared:
Task: "Consolidated nested Subcategory admin API suite in tests/Feature/Api/V1/Admin/Categories/SubcategoryApiTest.php"

# Launch independent request/resource/query work together:
Task: "Nested Requests in app/Http/Requests/Api/V1/Admin/Categories/"
Task: "Subcategory index/detail Resources in app/Http/Resources/Api/V1/Admin/Categories/"
Task: "AdminSubcategoryIndexQuery in app/Queries/Categories/AdminSubcategoryIndexQuery.php"
```

## Parallel Example: User Story 3

```bash
# Launch the public API suite while read-only response plumbing is prepared:
Task: "Public catalog API suite in tests/Feature/Api/V1/Public/Categories/PublicCategoryApiTest.php"

# Launch independent public read work together:
Task: "Public resources in app/Http/Resources/Api/V1/Public/Categories/"
Task: "PublicCategoryCatalogQuery in app/Queries/Categories/PublicCategoryCatalogQuery.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1
4. **Stop and validate** root Category admin behavior independently
5. Demo or ship the admin root-catalog MVP if desired

### Incremental Delivery

1. Setup + Foundational → catalog backbone ready
2. Add US1 → validate root Category admin management
3. Add US2 → validate nested Subcategory management
4. Add US3 → validate public catalog browsing
5. Add concurrency, architecture, and contract verification

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. After Foundational:
   - Developer A: US1 root Categories
   - Developer B: US2 nested Subcategories
   - Developer C: US3 public catalog reads
3. Finish with shared concurrency, contract, and quality validation

---

## Notes

- Total tasks: 39
- User story task counts:
  - **US1**: 8 tasks
  - **US2**: 8 tasks
  - **US3**: 5 tasks
- Parallelizable tasks marked `[P]`: 20
- Suggested MVP scope: **User Story 1**
- All tasks follow the required checklist format with task ID, optional `[P]`,
  required story labels for story phases, and exact file paths
- The approved source remains
  `docs/features/003-categories-subcategories.md`

---

## Phase 8: Printing Catalogue Seed Data

- [X] T040 Add an idempotent `PrintingCatalogSeeder` with six real
  printing-domain Categories, twelve nested Subcategories, bilingual content,
  deterministic ordering, validated external image downloads stored on the
  public disk, failure compensation, normal `DatabaseSeeder` integration, and
  focused automated coverage in `database/seeders/PrintingCatalogSeeder.php`,
  `database/seeders/DatabaseSeeder.php`, and
  `tests/Feature/Database/Seeders/PrintingCatalogSeederTest.php`.
